<?php

/**
 * @file Template4Factory.php
 *
 * <p>Based on code released under a BSD-style license. For complete license
 * text see http://sgraastra.net/code/license/.</p>
 *
 * @author Thijs Putman <thijs@sgraastra.net>
 * @copyright © 2008-2009 Thijs Putman, all rights reserved.
 * @copyright © 2010-2012 StudyPortals B.V., all rights reserved.
 * @version 4.0.3
 */

namespace StudyPortals\Template;

/**
 * Factory.
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */

class Factory{

	const MARKER_START = '[{';
	const MARKER_END = '}]';

	/**
	 * Parse raw template data into a well-structured TokenList.
	 *
	 * <p>The second {@link $html} argument is used to indicate the provided
	 * {@link $template_file} is assumed to be HTML data. In the case of HTML
	 * data, all non-essential whitespace characters are removed. This is the
	 * default behaviour and only needs to be displayed in case of plain-text
	 * templates.</p>
	 *
	 * @param string $template_file
	 * @param boolean $html
	 * @return TokenList
	 * @throws FactoryException, InvalidSyntaxException
	 * @see Factory::_parseTemplate()
	 */

	public static function parseTemplate($template_file, $html = true){

		if(!file_exists($template_file) || !is_readable($template_file)){

			throw new FactoryException('Cannot parse template "'
				. basename($template_file) . '", file not found or access denied');
		}

		$raw_data = @file_get_contents($template_file);
		$raw_data = self::_preParseTemplate($raw_data, $template_file, $html);

		return self::_parseTemplate($raw_data, $html);
	}

	/**
	 * Clean raw template data before starting the actual parse operation.
	 *
	 * <p>This method is called at the start of {@link Factory::parseTemplate()}
	 * and allows the {@link $raw_data} to be "pre-parsed" before being
	 * transformed into a structured TokenList.</p>
	 *
	 * <p>In the Factory implementation this method replaces "comment markers"
	 * with regular markers. This replace happens independent of the
	 * {@link $html} parameter.</p>
	 *
	 * @param string $raw_data
	 *
	 * @param $template_file
	 * @param $html
	 * @param null $locale
	 *
	 * @return string
	 * @see Factory::parseTemplate()
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */

	protected static function _preParseTemplate($raw_data, $template_file,
		$html, $locale = null){

		// Replace "comment-markers" with actual template-markers

		$comment_start = '/(?:<!--|\/\*)\s*' . preg_quote(self::MARKER_START, '/') . '/';
		$comment_end = '/' . preg_quote(self::MARKER_END, '/') . '\s*(?:-->|\*\/)/';

		$raw_data = preg_replace(
			[$comment_start, $comment_end],
			[self::MARKER_START, self::MARKER_END],
			$raw_data);

		return $raw_data;
	}

	/**
	 * Parse raw template data into a well-structured TokenList.
	 *
	 * <p>Work-horse method for the Factory::parseTemplate() public method.</p>
	 *
	 * @param string $raw_data
	 * @param boolean $html
	 *
	 * @throws InvalidSyntaxException
	 * @throws FactoryException
	 * @return TokenList
	 * @see Factory::parseTemplate()
	 */

	protected static function _parseTemplate($raw_data, $html){

		// Setup the parser-state

		$TemplateTokens = new TokenList();

		$in_definition = false;
		$definition_data = '';
		$element_data = '';

		$stack = [];

		$scope = [];
		$scope_depth = 0;

		$length = strlen($raw_data);

		assert('strlen(self::MARKER_START) === strlen(self::MARKER_END)');
		$marker_length = strlen(self::MARKER_START);

		$line = 1;

		// Start parsing

		for($i = 0; $i < $length; $i++){

			// Line counter

			if(($raw_data[$i] == "\r" && $raw_data[$i + 1] == "\n")
				|| ($raw_data[$i] == "\n" && $raw_data[$i - 1] != "\r")
				|| $raw_data[$i] == "\r"){

				$line++;
			}

			switch(substr($raw_data, $i, $marker_length)){

				// Definition start

				case self::MARKER_START:

					if($in_definition){

						throw new InvalidSyntaxException('Unexpected start of definition', $line);
					}

					self::_addTextToken($TemplateTokens, $element_data, $html);

					$in_definition = true;
					$element_data = '';

					// Skip to end of tag marker

					$i = $i + $marker_length - 1;

				break;

				// Definition end

				case self::MARKER_END:

					if(!$in_definition){

						throw new InvalidSyntaxException('Unexpected end of definition', $line);
					}

					$definition = self::_tokeniseString($definition_data);

					try{

						switch(strtolower($definition[0])){

							// Replace

							case 'replace':
							case 'var':

								$TemplateTokens->addToken(TokenList::T_REPLACE, $definition[1]);

								// Process parameters

								foreach(array_slice($definition, 2) as $key => $value){

									switch(strtolower($value)){

										case 'local':

											$TemplateTokens->addToken(TokenList::T_LOCAL);

										break;

										case 'raw':

											$TemplateTokens->addToken(TokenList::T_RAW);

										break;

										default:

											throw new InvalidSyntaxException("Invalid parameter
												\"$value\" for replace-statement", $line);
									}
								}

							break;

							// Config

							case 'config':

								// Only allowed inside a Component

								list($element_type,,) = end($stack);

								if($element_type != 'component'){

									throw new InvalidSyntaxException('config-statement not allowed
										in this context', $line);
								}

								// Remove optional "is" from definition

								if(strtolower($definition[2]) == 'is'){

									$configuration_value = $definition[3];
									unset($definition[2]);
								}

								else{

									$configuration_value = $definition[2];
								}

								// Check parameters

								if(count($definition) != 3){

									throw new InvalidSyntaxException('Invalid parameters
										for config-statement', $line);
								}

								$TemplateTokens->addToken(TokenList::T_CONFIG, $definition[1]);
								self::_addValueToken($configuration_value, $TemplateTokens);

							break;

							// Include

							case 'include':

								// Include Template or Component

								if(strtolower($definition[1]) == 'template'
									|| strtolower($definition[1]) == 'component'){

									// Check parameters

									if(count($definition) != 5 && count($definition) != 3){

										throw new InvalidSyntaxException('Invalid parameters
											for include-statement', $line);
									}

									switch(strtolower($definition[1])){

										case 'template':

											$TemplateTokens->addToken(
												TokenList::T_INCLUDE_TEMPLATE, $definition[2]);

										break;

										case 'component':

											$TemplateTokens->addToken(
												TokenList::T_INCLUDE_COMPONENT, $definition[2]);

										break;
									}

									// Named include

									if(count($definition) == 5){

										if(strtolower($definition[3]) != 'as'){

											throw new InvalidSyntaxException("Invalid syntax
												for include-statement, expected \"as\", got
												\"$definition[3]\"", $line);
										}

										$TemplateTokens->addToken(TokenList::T_NAME, $definition[4]);
									}
								}

								// Include non-parseable File

								else{

									// Check parameters

									if(count($definition) != 2){

										throw new InvalidSyntaxException('Invalid parameters
											for include-statement', $line);}

									$TemplateTokens->addToken(TokenList::T_INCLUDE, $definition[1]);
								}

							break;

							// Elements with content

							case 'condition':
							case 'if':
							case 'repeater':
							case 'loop':
							case 'section':
							case 'component':

								$element_type = strtolower($definition[0]);
								$element_name = $definition[1];

								// Process aliasses

								if($element_type == 'if') 	$element_type = 'condition';
								if($element_type == 'loop')	$element_type = 'repeater';

								// Closing statement

								if(isset($definition[2])
									&& strtolower($definition[2]) == 'end'
									&& count($definition) == 3){

									// Read stack

									list($expected_type, $expected_name, $expected_uid) = array_pop($stack);

									// Type match

									if($expected_type != $element_type){

										throw new InvalidSyntaxException("Expected $expected_type
											got $element_type", $line);
									}

									// Name match

									elseif($expected_name != $element_name){

										throw new InvalidSyntaxException("Expected $element_type
											with name \"$expected_name\", got \"$element_name\"", $line);
									}

									$TemplateTokens->addToken(TokenList::T_END_ELEMENT, $expected_uid);

									// Reset scope (for Template and children only)

									if($element_type != 'condition'){

										unset($scope[$scope_depth]);

										--$scope_depth;
									}
								}

								// Opening statement

								else{

									$element_uid = md5(uniqid($element_type . $element_name, true));

									// Check scope (for Template and children only)

									if($element_type != 'condition'){

										++$scope_depth;

										// Duplicate element detected

										if(isset($scope[$scope_depth])
											&& is_array($scope[$scope_depth])
											&& in_array($element_name, $scope[$scope_depth])){

											throw new InvalidSyntaxException("Duplicate element
												$element_name encountered in scope", $line);
										}

										else{

											$scope[$scope_depth][] = $element_name;
										}
									}

									// Update stack

									$stack[] = [$element_type, $element_name, $element_uid];

									$TemplateTokens->addToken(TokenList::T_START_ELEMENT, $element_uid);
									$TemplateTokens->addToken(TokenList::T_START_DEFINITION, $element_type);
									$TemplateTokens->addToken(TokenList::T_NAME, $element_name);

									switch($element_type){

										// Condition

										case 'condition':

											// Scope

											if(end($definition) == 'local'){

												array_pop($definition);
												$TemplateTokens->addToken(TokenList::T_LOCAL);
											}

											// Operator

											try{

												self::_addOperatorToken($definition[2], $TemplateTokens);
											}
											catch(FactoryException $e){

												throw new InvalidSyntaxException($e->getMessage(), $line);
											}

											// Comparison value

											$TemplateTokens->end();
											assert('$TemplateTokens->token == ' . __NAMESPACE__
												. '\TokenList::T_OPERATOR');

											try{

												// Set

												if($TemplateTokens->token_data == 'in'
													|| $TemplateTokens->token_data == '!in'){

													self::_addValueToken(
														array_slice($definition, 3), $TemplateTokens);
												}

												// Scalar

												else{

													if(count($definition) != 4){

														throw new InvalidSyntaxException('Invalid parameter
															count for condition-statement', $line);
													}

													self::_addValueToken($definition[3], $TemplateTokens);
												}
											}
											catch(FactoryException $e){

												throw new InvalidSyntaxException($e->getMessage(), $line);
											}

										break;

										// Component

										case 'component':

											// Check parameters

											if(count($definition) != 4 && strtolower($definition[2]) != 'class'){

												throw new InvalidSyntaxException('Invalid parameter count
													for component-statement', $line);
											}

											$TemplateTokens->addToken(TokenList::T_CLASS, $definition[3]);

										break;
									}

									$TemplateTokens->addToken(TokenList::T_END_DEFINITION, $element_type);
								}

							break;

							// Unknown element

							default:

								if(empty($element_type)) $element_type = strtolower($definition[0]);

								throw new InvalidSyntaxException("Unknown element
									$element_type encountered", $line);
						}
					}

					catch(TokenListException $e){

						throw new InvalidSyntaxException($e->getMessage(), $line);
					}

					$in_definition = false;
					$definition_data = '';

					// Skip to end of tag marker

					$i = $i + $marker_length - 1;

				break;

				// Collect text

				default:

					if($in_definition){

						$definition_data .= $raw_data[$i];
					}
					else $element_data .= $raw_data[$i];
			}
		}

		// Collect final text characters

		self::_addTextToken($TemplateTokens, $element_data, $html);

		$TemplateTokens->reset();

		return $TemplateTokens;
	}

	/**
	 * Split a string into tokens.
	 *
	 * <p>Tokenises a string using white-space characters. Both single- and
	 * double-quote characters can be used to start a quoted section. A
	 * backslash can be used to escape a single- or double-quote character.</p>
	 *
	 * @param string $string
	 * @return array
	 */

	protected static function _tokeniseString($string){

		$token_list = [];

		$in_quote = false;
		$length = strlen($string);
		$token = '';

		for($i = 0; $i < $length; $i++){

			switch($string[$i]){

				// Token seperators

				case ' ':
				case "\r":
				case "\n":
				case "\t":

					if($in_quote){

						$token .= $string[$i];}

					elseif($token != ''){

						$token_list[] = $token;
						$token = '';
					}

				break;

				// Quote seperators

				case '\'':
				case '"':

					if(!$in_quote){

						$in_quote = $string[$i];
					}

					elseif($in_quote == $string[$i]){

						if($string[$i - 1] == '\\') break;

						$in_quote = false;

						if($token != ''){

							$token_list[] = $token;
							$token = '';
						}
					}

					else{

						$token .= $string[$i];
					}

				break;

				default:

					$token .= $string[$i];
			}
		}

		if($token != '') $token_list[] = $token;

		return $token_list;
	}

	/**
	 * Add a text (<em>TokenList::T_TEXT_*</em>) token to the TokenList.
	 *
	 * <p>This method adds the contents of the {@link $text} argument as a new
	 * token to the TokenList. Based upon the state of the {@link $html}
	 * argument, either a {@link TokenList::T_TEXT_PLAIN} or
	 * {@link TokenList::T_TEXT_HTML} token is added.</p>
	 *
	 * <p>In case the {@link $html} argument is set to <em>true</em> additional
	 * filtering  is applied to produce "cleaner" HTML. Amongst other things,
	 * all unnecessary whitespace characters are removed.</p>
	 *
	 * @param string $text
	 * @param boolean $html
	 * @param TokenList $TemplateTokens
	 *
	 * @throws TokenListException
	 * @return void
	 * @see Factory::parseTemplate()
	 */

	private static function _addTextToken(TokenList $TemplateTokens, $text, $html = true){

		if($text == '') return;

		// HTML

		if($html){

			if(trim($text) == '') return;

			// Condense all whitespace characters into a single space

			$text = preg_replace('/[\s]+/', ' ', $text);

			$TemplateTokens->addToken(TokenList::T_TEXT_HTML, $text);
		}

		// Plain-Text (unchanged)

		else{

			$TemplateTokens->addToken(TokenList::T_TEXT_PLAIN, $text);
		}
	}

	/**
	 * Add an operator ({@link TokenList::T_OPERATOR}) token to the TokenList.
	 *
	 * <p>This method is used internally by Factory::parseTemplate() to convert
	 * a multitude of operator tokens into the limited set of operators
	 * accepted by the Condition object.</p>
	 *
	 * @param string $operator
	 * @param TokenList $TemplateTokens
	 * @return void
	 * @throws FactoryException
	 * @see Factory::parseTemplate()
	 */

	private static function _addOperatorToken($operator, TokenList $TemplateTokens){

		switch(strtolower($operator)){

			// Equals

			case 'is':
			case '=':
			case '==':

				$TemplateTokens->addToken(TokenList::T_OPERATOR, '==');
				return;

			break;

			// Not equals

			case 'not':
			case '!=':
			case '<>':

				$TemplateTokens->addToken(TokenList::T_OPERATOR, '!=');
				return;

			break;

			// Sets

			case 'in':

				$TemplateTokens->addToken(TokenList::T_OPERATOR, 'in');
				return;

			break;

			case '!in':
			case 'notin':

				$TemplateTokens->addToken(TokenList::T_OPERATOR, '!in');
				return;

			break;

			// Greater

			case 'greater':
			case 'gt':
			case '>':

				$TemplateTokens->addToken(TokenList::T_OPERATOR, '>');
				return;

			break;

			case 'gte':
			case '>=':

				$TemplateTokens->addToken(TokenList::T_OPERATOR, '>=');
				return;

			break;

			// Smaller

			case 'smaller':
			case 'lt':
			case '<':

				$TemplateTokens->addToken(TokenList::T_OPERATOR, '<');
				return;

			break;

			case 'lte':
			case '<=':

				$TemplateTokens->addToken(TokenList::T_OPERATOR, '<=');
				return;

			break;

			// Invalid Operator

			default:

				throw new FactoryException("Invalid condition operator
					\"$operator\" specified");
		}
	}

	/**
	 * Add a value (<em>TokenList::T_VALUE_*</em>) token to the TokenList.
	 *
	 * <p>This method is used internally by Factory::parseTemplate() to
	 * properly parse values and add them to the TokenList.</p>
	 *
	 * @param mixed $value
	 * @param TokenList $TemplateTokens
	 * @return void
	 * @throws FactoryException
	 * @see Factory::parseTemplate()
	 */

	private static function _addValueToken($value, TokenList $TemplateTokens){

		if(is_numeric($value)){

			$TemplateTokens->addToken(TokenList::T_VALUE_INT, (int) $value);
			return;
		}
		elseif(is_array($value)){

			$TemplateTokens->addToken(TokenList::T_VALUE_ARRAY, serialize($value));
			return;
		}

		switch(strtolower($value)){

			case 'true':

				$TemplateTokens->addToken(TokenList::T_VALUE_BOOLEAN, '1');
				return;

			break;

			case 'false':

				$TemplateTokens->addToken(TokenList::T_VALUE_BOOLEAN, '0');
				return;

			break;

			case 'null':

				$TemplateTokens->addToken(TokenList::T_VALUE_NULL);
				return;

			break;

			default:

				$TemplateTokens->addToken(TokenList::T_VALUE_STRING, (string) $value);
				return;
		}
	}

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
	 *
	 * @throws FactoryException
	 * @throws TokenListException
	 * @return Node
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

					$replace = $TokenList->token_data;
					$local = false;
					$raw = false;

					// Collect parameters

					while($TokenList->nextToken()){

						switch($TokenList->token){

							case TokenList::T_LOCAL: $local = true;
							break;

							case TokenList::T_RAW: $raw = true;
							break;

							default:

								$TokenList->previousToken();
								break 2;
						}
					}

					new Replace($Parent, $replace, $local, $raw);

				break;

				// Includes

				case TokenList::T_INCLUDE:
				case TokenList::T_INCLUDE_TEMPLATE:
				case TokenList::T_INCLUDE_COMPONENT:

					// Get the file name of the base template file

					assert('method_exists($Parent->getRoot(), \'getFileName\')');

					/** @noinspection PhpUndefinedMethodInspection */
					$base_dir = dirname($Parent->getRoot()->getFileName());
					$file_name = "$base_dir/{$TokenList->token_data}";

					$TemplateTokens = null;

					// Try an include path relative to the base template file

					if(!file_exists($file_name) || !is_readable($file_name)){

						$file_name = $TokenList->token_data;

						// Fall-back to an include path relative to the PHP-file being executed

						if(!file_exists($file_name) || !is_readable($file_name)){

							throw new FactoryException('Error while including "'
								. basename($file_name) . '", file not found or access denied');
						}
					}

					// Include non-parseable file

					if($TokenList->token == TokenList::T_INCLUDE){

						$file_contents = @file_get_contents($file_name);
						assert('$file_contents !== false');

						if(trim($file_contents) != ''){

							new Text($file_contents, $Parent);
						}

						unset($file_contents);
					}

					// Include component

					elseif($TokenList->token == TokenList::T_INCLUDE_COMPONENT){

						// Use a localised component if the root is also localised

						$ComponentTokens = null;
						if($Parent->getRoot() instanceof LocalizedTemplate){

							try{

								/** @noinspection PhpUndefinedMethodInspection */
								$ComponentTokens = LocalizedFactory::parseTemplate(
									$file_name, $html, $Parent->getRoot()->getLocale());
							}

							catch(TemplateException $e){}
						}

						if(!($ComponentTokens instanceof TokenList)){

							$ComponentTokens = self::parseTemplate($file_name, $html);
						}

						// The external date can only be used if it contains a single, top-level Component

						if(!self::_isOnlyComponent($ComponentTokens)){

							throw new FactoryException('Only a single, top-level,
								component-element is allowed in an external component file');
						}

						// Attempt to use name provided in TokenList

						try{

							$name = $TokenList->nextData(TokenList::T_NAME);

							if($name !== null){

								$ComponentTokens = self::_renameComponent($ComponentTokens, $name);
							}
						}

						// Fallback to name provided in the external file (c.q. leave Component as-is)

						catch(TokenListException $e){}

						self::buildTemplate($ComponentTokens, $Parent, $html);

						unset($ComponentTokens);
					}

					// Include template

					elseif($TokenList->token == TokenList::T_INCLUDE_TEMPLATE){

						// Use a localised template if the root is also localised

						if($Parent->getRoot() instanceof LocalizedTemplate){

							try{

								/** @noinspection PhpUndefinedMethodInspection */
								$TemplateTokens = LocalizedFactory::parseTemplate(
									$file_name, $html, $Parent->getRoot()->getLocale());
							}

							catch(TemplateException $e){}
						}

						if(!($TemplateTokens instanceof TokenList)){

							$TemplateTokens = self::parseTemplate($file_name, $html);
						}

						// Attempt to use name provided in TokenList

						try{

							$node_name = $TokenList->nextData(TokenList::T_NAME);
						}

						catch(TokenListException $e){

							$node_name = null;}

						// Fallback to filename

						if($node_name === null){

							$node_name = basename($TokenList->token_data);
							$node_name = substr($node_name, 0, strrpos($node_name, '.'));
							$node_name = preg_replace('/[^A-Z0-9]+/i', '', $node_name);
						}

						self::buildTemplate($TemplateTokens, new Section($node_name, $Parent), $html);

						unset($TemplateTokens);
					}

				break;

				// Elements

				case TokenList::T_START_ELEMENT:

					$element_id = $TokenList->token_data;

					$element_type = $TokenList->nextData(TokenList::T_START_DEFINITION);
					$element_name = $TokenList->nextData(TokenList::T_NAME);

					switch($element_type){

						// Section

						case 'section':

							$Child = new Section($element_name, $Parent);

						break;

						// Condition

						case 'condition':

							$local = true;

							// Check for local condition

							try{

								$TokenList->nextToken(TokenList::T_LOCAL);
							}

							catch(TokenListException $e){

								$local = false;
							}

							$Child = new Condition($Parent, $element_name,
								$TokenList->nextData(TokenList::T_OPERATOR),
								$TokenList->nextData(TokenList::T_VALUE), $local);

						break;

						// Repeater

						case 'repeater':

							$Child = new Repeater($element_name, $Parent);

						break;

						// Component

						case 'component':

							$token_class = $TokenList->nextData(TokenList::T_CLASS);

							if(!class_exists($token_class)){

								throw new FactoryException("Class \"$token_class\" does not exist");
							}

							if(!@is_subclass_of($token_class, 'Component')){

								throw new FactoryException("Class \"$token_class\"
									needs to inherit from Component");
							}

							$Child = new $token_class($Parent, $element_name);

						break;

						default:

							throw new FactoryException("Invalid element \"$element_type\" encountered");
					}

					// Build Element content

					$TokenList->nextToken(TokenList::T_END_DEFINITION);

					self::buildTemplate($TokenList->collectTokens(
						TokenList::T_END_ELEMENT, $element_id), $Child, $html);

					unset($Child);

				break;

				// Configuration data (only allowed inside a Component)

				case TokenList::T_CONFIG:

					if(!($Parent instanceof Component)){

						throw new FactoryException("Token \"$TokenList->token\"
							is only allowed inside Component");
					}

					$Parent->setDefault($TokenList->token_data,
						$TokenList->nextData(TokenList::T_VALUE));

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

	/**
	 * Check if the provided TokenList contains only a single, top-level Component.
	 *
	 * <p>This method checks if the provided TokenList contains only a single
	 * top-level element which is also of the Component type. This is a
	 * requirement for an external component if it is to be included in another
	 * Template.</p>
	 *
	 * @param TokenList $ComponentTokens
	 *
	 * @throws TokenListException
	 * @return bool
	 * @see Factory::buildTemplate()
	 */

	private static function _isOnlyComponent(TokenList $ComponentTokens){

		$only_component = false;

		if($ComponentTokens->token == TokenList::T_START_ELEMENT){

			$element_id = $ComponentTokens->token_data;

			if($ComponentTokens->nextData(TokenList::T_START_DEFINITION) == 'component'){

				$ComponentTokens->collectTokens(TokenList::T_END_ELEMENT, $element_id);

				if(!$ComponentTokens->nextToken()){

					$only_component = true;
				}
			}
		}

		$ComponentTokens->reset();

		return $only_component;
	}

	/**
	 * Rename a Component by modifying its TokenList.
	 *
	 * <p>This method modifies the provided {@link $ComponentTokens} in such a
	 * way that the Component contained within this TokenList has its name
	 * changed into {@link $name}. This method is used internally by
	 * Factory::buildTemplate().<br>
	 * Note furthermore that this method does <strong>not</strong> verify
	 * whether the provided TokenList actually contains a valid Component
	 * definition!</p>
	 *
	 * <p>This is somewhat of a <em>hack</em>, but changing the TokenList to
	 * accomodate this behaviour requires a lot of effort for a single
	 * exceptional case. If these features are required for additional elements
	 * in the future it is most likely worth changing the TokenList class.</p>
	 *
	 * @param TokenList $ComponentTokens
	 * @param string $name
	 * @return TokenList
	 * @throws FactoryException
	 * @see Factory::buildTemplate()
	 */

	private static function _renameComponent(TokenList $ComponentTokens, $name){

		$tokens = [];
		$changed = false;

		foreach($ComponentTokens->tokens as $raw_token){

			// First name in the TokenList is the Component name

			if(!$changed && strpos($raw_token, TokenList::T_NAME) === 0){

				$tokens[] = TokenList::T_NAME . " $name";
				$changed = true;
			}

			else{

				$tokens[] =  $raw_token;
			}
		}

		try{

			return new TokenList($tokens);
		}

		catch(TokenListException $e){

			throw new FactoryException("Unable to rename Component to\"$name\",
				the name is most likely invalid");
		}
	}
}