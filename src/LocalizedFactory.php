<?php
/**
 * @file LocalizedFactory.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

use StudyPortals\Utils\XML;
use StudyPortals\Utils\XMLException;

/**
 * LocalizedFactory.
 *
 * <p>Extends upon the main Factory by allowing templates to contain
 * translation markers, which, upon parsing, are replaced by strings in the
 * correct language.</p>
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
class LocalizedFactory extends Factory{

	/**
	 * Parse a localised template into a well-structured {@link NodeList}.
	 *
	 * @param string $template_file
	 * @param boolean $html
	 * @param string $locale RFC-4646 compliant
	 *
	 * @throws FactoryException
	 * @throws InvalidSyntaxException
	 * @throws LocalizedFactoryException
	 * @return TokenList
	 * @see Factory::parseTemplate()
	 */

	public static function parseTemplate($template_file, $html = true,
		$locale = null){

		if($locale !== null){

			$locale = str_replace('_', '-', $locale);
			assert('preg_match(\'/^[a-z]{2}-[A-Z]{2}$/\', $locale)');
		}

		if(!file_exists($template_file) || !is_readable($template_file)){

			throw new LocalizedFactoryException(
				'Cannot parse template "'
				. basename($template_file) .
				'", file not found or access denied'
			);
		}

		$raw_data = @file_get_contents($template_file);

		try{

			$raw_data =
				self::_preParseTemplate($raw_data, $template_file, $html, $locale);
		}
		catch(LocalizedFactoryException $e){

			$raw_data = parent::_preParseTemplate($raw_data, $template_file, $html, $locale);
		}

		return self::_parseTemplate($raw_data, $html);
	}

	/**
	 * Load the translations belonging to the provide template file.
	 *
	 * <p>Throws a LocalizedFactoryException in cases when the translation file
	 * cannot be accessed, or contains invalid XML.</p>
	 *
	 * @param string $language_file
	 *
	 * @return \SimpleXMLElement
	 * @throws LocalizedFactoryException
	 */

	protected static function _loadTranslations($language_file){

		if(!file_exists($language_file) || !is_readable($language_file)){

			throw new LocalizedFactoryException(
				'Unable to load language file "'
				. basename($language_file) .
				'", file not found or access denied'
			);
		}

		try{

			$Translations = XML::loadSimpleXML($language_file, false);
		}
		catch(XMLException $e){

			$language_base = basename($language_file);

			throw new LocalizedFactoryException(
				"Unable to load language file
				$language_base:" . $e->getMessage(), 0, $e
			);
		}

		return $Translations;
	}

	/**
	 * Pre-parse a localised Template.
	 *
	 * @param string $raw_data
	 * @param string $template_file
	 * @param boolean $html
	 * @param string $locale RFC-4646 compliant
	 *
	 * @return string
	 * @throws LocalizedFactoryException
	 */

	protected static function _preParseTemplate($raw_data, $template_file,
		$html, $locale = null){

		$Translations = self::_loadTranslations("$template_file.langs");
		$xpath_base = '/translations/translation/string';

		$default_locale =
			str_replace('_', '-', trim($Translations->default_locale));
		assert('preg_match(\'/^[a-z]{2}-[A-Z]{2}$/\', $default_locale)');

		if($locale !== null){

			assert('preg_match(\'/^[a-z]{2}-[A-Z]{2}$/\', $locale)');

			$locale_alt = str_replace('-', '_', $locale);
			$MatchedStrings = $Translations->xpath(
				"{$xpath_base}[@locale='$locale' or @locale='$locale_alt']"
			);
		}

		// Locale not found, attempt a fallback to the default locale

		if(empty($MatchedStrings) && $default_locale != $locale){

			$locale = $default_locale;
			$locale_alt = str_replace('-', '_', $locale);

			$MatchedStrings = $Translations->xpath(
				"{$xpath_base}[@locale='$locale' or @locale='$locale_alt']"
			);
		}

		// Locale not found

		if(!$MatchedStrings){

			throw new LocalizedFactoryException(
				"The language file does not contain information on \"$locale\""
			);
		}

		$raw_data = parent::_preParseTemplate($raw_data, $template_file, $html);
		$raw_data = self::_processMarkers(
			$raw_data,
			$MatchedStrings
		);

		// Remove unused translation markers

		$raw_data = preg_replace('/%[a-z0-9_|,]+%/i', '', $raw_data);

		return $raw_data;
	}

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
	 * of) replace markers. Each of the different sets are evaluated seperately
	 * by this function and all of these sets will end up with their own
	 * (unique) translation string with replace statements added.</p>
	 *
	 * @param string $raw_data
	 * @param array $MatchedStrings
	 *
	 * @inheritdoc
	 * @return string
	 */

	protected static function _processMarkers($raw_data, array $MatchedStrings){

		foreach($MatchedStrings as $TranslationString){

			// Get the translation marker (seems to be no easier way to get the parent node in SimpeXML)

			$translations =
				$TranslationString->xpath('parent::translation/marker/text()');
			$translation_marker = (string) reset($translations);

			// Filter all but some very basic HTML from the translation string

			$translation_string =
				htmlentities(trim($TranslationString), ENT_NOQUOTES, 'UTF-8');
			$translation_string = preg_replace(
				'/&lt;(\/?(?:strong|em|p|br|sub|sup))&gt;/',
				'<$1>',
				$translation_string
			);

			// Find all translation markers (regular and expanded) for the current translation

			$matches = [];
			preg_match_all(
				"/(%$translation_marker%|%$translation_marker(?:\|[a-z0-9_,]*)%)/i",
				$raw_data,
				$matches,
				PREG_PATTERN_ORDER
			);

			foreach($matches[0] as $match){

				$expanded_string = $translation_string;

				// Optionally expand the translation marker

				if(strpos($match, '|') !== false){

					$replaces = substr($match, strpos($match, '|') + 1, -1);
					$replaces = explode(',', $replaces);

					// Update the translation string

					foreach($replaces as $key => $replace){

						++$key;

						$expanded_string = str_replace(
							"%$key",
							"[{replace $replace}]",
							$expanded_string
						);
					}

					// Remove unused expansion markers

					$expanded_string =
						preg_replace('/%[0-9]*/', '', $expanded_string);
				}

				// Insert the translation

				$raw_data = str_replace($match, $expanded_string, $raw_data);
			}
		}

		return $raw_data;
	}
}