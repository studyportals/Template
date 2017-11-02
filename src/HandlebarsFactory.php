<?php
/**
 * @file HandlebarsFactory.php
 *
 * @author Rob van den Hout <vdhout@studyportals.eu>
 * @version 1.0.0
 * @copyright © 2014 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

use SimpleXMLElement;
use StudyPortals\Exception\ExceptionHandler;

/**
 * Class HandlebarsFactory.
 *
 * @package StudyPortals\Framework\Template4
 */

class HandlebarsFactory extends LocalizedFactory{

	/**
	 * Process the translation markers.
	 *
	 * <p>This method takes an array XML translation strings, scans the raw
	 * template data for their respective markers and replaces theses markers
	 * with the translations as defined in the translation XML-file.</p>
	 *
	 * <p>Translation markers can contain "expansion markers" in the form of
	 * <em>%TranslationMarker|replace_1,replace_2%</em>. Special indicators in
	 * the translation string are converted into the specified replace
	 * statements. This functionality is similar to that offered by
	 * {@link sprintf()}.</p>
	 *
	 * <p>In this example, the indicator <em>%1</em> in the translation string
	 * is replaced by "{[replace replace_1}]" and <em>%2</em> is replaced by
	 * "{[replace replace_2]}".<br>
	 * It is possible for a translation marker to have several different (sets
	 * of) replace markers. Each of the different sets are evaluated separately
	 * by this function and all of these sets will end up with their own
	 * (unique) translation string with replace statements added.</p>
	 *
	 * @param string $raw_data
	 * @param array $MatchedStrings
	 * @param string $language_file
	 * @return string
	 */

	protected static function _processMarkers($raw_data, array $MatchedStrings, $language_file = null){

		/** @var SimpleXMLElement $TranslationString */
		foreach($MatchedStrings as $TranslationString){

			// Get the translation marker (seems to be no easier way to get the parent node in SimpeXML)

			$translations = $TranslationString->xpath('parent::translation/marker/text()');
			$translation_marker = (string) reset($translations);

			// Filter all but some very basic HTML from the translation string

			$translation_string = htmlentities(trim($TranslationString), ENT_NOQUOTES, 'UTF-8');
			$translation_string = preg_replace('/&lt;(\/?(?:strong|em|p|br|sub|sup))&gt;/',
				'<$1>', $translation_string);

			// Find all translation markers (regular and expanded) for the current translation

			$matches = [];
			preg_match_all("/(%$translation_marker%|%$translation_marker(?:\|[a-z0-9_,]*)%)/i",
				$raw_data, $matches, PREG_PATTERN_ORDER);

			foreach($matches[0] as $match){

				$expanded_string = $translation_string;

				// Optionally expand the translation marker

				if(strpos($match, '|') !== false){

					$replaces = substr($match, strpos($match, '|') + 1, -1);
					$replaces = explode(',', $replaces);

					// Update the translation string

					foreach($replaces as $key => $replace){

						++$key;

						$expanded_string = str_replace("%$key", '{{' . $replace . '}}', $expanded_string);
					}

					// Remove unused expansion markers

					$expanded_string = preg_replace('/%[0-9]*/', '', $expanded_string);
				}

				// Insert the translation

				$raw_data = str_replace($match, $expanded_string, $raw_data);
			}
		}

		return $raw_data;
	}

	/** @noinspection PhpDocSignatureInspection */

	/**
	 * Build a Template from a TokenList.
	 *
	 * <p>Builds a Template object from a provided TokenList. When called
	 * externally, the {@link $Parent} will in most cases be an empty Template4
	 * object. Any Node object will do, so it is also possible to parse a
	 * TokenList "into" an existing Template.</p>
	 *
	 * <p>See {@link Factory::parseTemplate()} for details on the optional
	 * {@link $html} argument. In the context of buildTemplate() this argument
	 * is only used when external entities are included (c.q. "include" or
	 * "component" statements are encountered).</p>
	 *
	 * @param TokenList $TokenList
	 * @param NodeTree $Parent
	 * @param boolean $html
	 * @return Node
	 * @throws FactoryException
	 */

	public static function buildTemplate(TokenList $TokenList, NodeTree $Parent, $html = true){

		// If the TokenList is empty we can directly return the Parent

		if(count($TokenList->tokens) == 0){

			// Except if it is a text-node, which should never be empty

			if($Parent instanceof Text){

				throw new FactoryException('Invalid TextNode encountered,
					text nodes cannot be empty');
			}

			return $Parent;
		}

		do{

			switch($TokenList->token){

				// Text

				case TokenList::T_TEXT_PLAIN:
				case TokenList::T_TEXT_HTML:

					new Text($TokenList->token_data, $Parent);

					break;

				// Replace

				case TokenList::T_REPLACE:

					new Text('{{' . $TokenList->token_data . '}}', $Parent);

					break;

				// Elements

				case TokenList::T_START_ELEMENT:

					$element_id = $TokenList->token_data;

					$element_type = $TokenList->nextData(TokenList::T_START_DEFINITION);
					$element_name = $TokenList->nextData(TokenList::T_NAME);

					switch($element_type){

						// Condition

						case 'condition':

							$operator = $TokenList->nextData(TokenList::T_OPERATOR);
							$value = $TokenList->nextData(TokenList::T_VALUE);

							$helper = 'if';

							switch($operator){

								// Scalar

								case '==': 	$helper = 'ifE';    break;
								case '!=': 	$helper = 'ifNE';   break;
								case '<': 	$helper = 'ifLT';   break;
								case '<=': 	$helper = 'ifLTE';  break;
								case '>':	$helper = 'ifGT';   break;
								case '>=': 	$helper = 'ifGTE';  break;

								case 'in':
								case '!in':
								default:

									ExceptionHandler::notice('in and !in are not
									implemented for handlebars, default to if');

								break;
							}

							// Check for local condition

							if($value === true){

								$value = 'true';
							}
							elseif($value === false){

								$value = 'false';
							}

							new Text('{{#' . $helper . ' ' . $element_name . ' \'' . $value . '\'}}', $Parent);

							// Build Element content

							$TokenList->nextToken(TokenList::T_END_DEFINITION);

							self::buildTemplate($TokenList->collectTokens(
									TokenList::T_END_ELEMENT, $element_id), $Parent, $html);

							new Text('{{/' . $helper . '}}', $Parent);

							break;

						// Repeater

						case 'repeater':

							new Text('{{#each ' . $element_name . '}}', $Parent);

							$TokenList->nextToken(TokenList::T_END_DEFINITION);

							self::buildTemplate($TokenList->collectTokens(
									TokenList::T_END_ELEMENT, $element_id), $Parent, $html);

							new Text('{{/each}}', $Parent);

							break;

						default:

							throw new FactoryException("Invalid element \"$element_type\" encountered");
					}

					break;

				// Unexpected token

				default:

					throw new FactoryException("Error while building Template,
						unexpected \"$TokenList->token\" encountered");
			}
		}

		while($TokenList->nextToken());

		return $Parent;
	}
}