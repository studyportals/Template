<?php
/**
 * @file TokenList.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

/**
 * TokenList.
 *
 * @property string $token Current token
 * @property mixed $token_data Current token data
 * @property string $token_raw Current token in raw format (token [space] data)
 * @property array $tokens All tokens in the TokenList
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
class TokenList{

	const T_REPLACE = 'REPLACE';
	const T_REPLACE_LOCAL = 'REPLACE_LOCAL';
	const T_TEXT_PLAIN = 'TEXT_PLAIN';
	const T_TEXT_HTML = 'TEXT_HTML';
	const T_START_ELEMENT = 'START_ELEMENT';
	const T_END_ELEMENT = 'END_ELEMENT';
	const T_START_DEFINITION = 'START_DEFINITION';
	const T_END_DEFINITION = 'END_DEFINITION';
	const T_NAME = 'NAME';
	const T_CONFIG = 'CONFIG';
	const T_LOCAL = 'LOCAL';
	const T_RAW = 'RAW';
	const T_INCLUDE = 'INCLUDE';
	const T_INCLUDE_TEMPLATE = 'INCLUDE_TEMPLATE';
	const T_INCLUDE_COMPONENT = 'INCLUDE_COMPONENT';
	const T_OPERATOR = 'OPERATOR';
	const T_CLASS = 'CLASS';
	const T_VALUE = 'VALUE';
	const T_VALUE_BOOLEAN = 'VALUE_BOOLEAN';
	const T_VALUE_NULL = 'VALUE_NULL';
	const T_VALUE_INT = 'VALUE_INT';
	const T_VALUE_ARRAY = 'VALUE_ARRAY';
	const T_VALUE_STRING = 'VALUE_STRING';

	protected $_tokens = [];

	protected $_current_token;
	protected $_current_data;

	/**
	 * Construct a new TokenList.
	 *
	 * <p>Constructs a new TokenList containing the set of tokens found in the
	 * {@link $tokens} argument. Every token is added to the internal TokenList
	 * using the {@link addToken()} method and is thus checked before inclusion.
	 * Erronous tokens will cause the {@link TokenListException} to be thrown.</p>
	 *
	 * @param array $tokens Initial set of tokens for the TokenList
	 *
	 * @return void
	 * @throws TokenListException
	 */

	public function __construct(array $tokens = []){

		foreach($tokens as $raw_token){

			$this->addToken($raw_token);
		}
	}

	/**
	 * Get a dynamic property.
	 *
	 * @param string $name
	 *
	 * @return array|string
	 */

	public function __get($name){

		switch($name){

			case 'token':

				return $this->_current_token;
				break;

			case 'token_data':

				return $this->_current_data;
				break;

			case 'token_raw':

				if($this->_current_data === null){

					return $this->_current_token;
				}
				elseif(!is_array($this->_current_data)){

					return "$this->_current_token $this->_current_data";
				}
				else{

					assert(
						'is_array(unserialize(serialize($this->_current_data)))'
					);

					return $this->_current_token . ' ' .
						serialize($this->_current_data);
				}
				break;

			case 'tokens':

				return $this->_tokens;
				break;

			default:

				return null;
		}
	}

	/**
	 * Add a token to the TokenList.
	 *
	 * <p>The {@link $token} argument indicates the type of token and should be
	 * one of the token constants (<em>T_</em>) defined in the {@link TokenList}
	 * base class. The second argument {@link $token_data} should contain the
	 * token data. The argument may be omitted if no token data is present.</p>
	 *
	 * <p>When provided, the {@link $token_data} should be implicitly convertable
	 * to string. So, when using the <em>T_VALUE_ARRAY</em> token, its contents
	 * should be serialised <em>before</em> being passed into this method.</p>
	 *
	 * <p>If the {@link $token} argument contains a space character and the
	 * {@link $token_data} argument is set to <em>null</em>, the {@link $token}
	 * argument is assumed to be a raw token. All data after the first space is
	 * considered to be token data.</p>
	 *
	 * @param string $token
	 * @param string $token_data
	 *
	 * @return void
	 * @throws TokenListException
	 */

	public function addToken($token, $token_data = null){

		// Split token

		if(strpos($token, ' ') !== false && is_null($token_data)){

			list($token, $token_data) = explode(' ', $token, 2);
		}

		// Token data sanity checks

		switch($token){

			// Named tokens

			case self::T_REPLACE:
			case self::T_REPLACE_LOCAL:
			case self::T_NAME:
			case self::T_CONFIG:
			case self::T_CLASS:

				if(is_numeric($token_data) ||
					!preg_match('/^[A-Z0-9_]+$/i', $token_data)){

					throw new TokenListException(
						"Invalid token data provided for token \"$token\""
					);
				}

				break;

			// Arrays

			case self::T_VALUE_ARRAY:

				if(!is_array(@unserialize($token_data))){

					throw new TokenListException(
						"Invalid token data provided for token \"$token\""
					);
				}

				break;

			// Non-empty tokens

			case self::T_TEXT_HTML:
			case self::T_START_ELEMENT:
			case self::T_END_ELEMENT:
			case self::T_START_DEFINITION:
			case self::T_END_DEFINITION:
			case self::T_INCLUDE:
			case self::T_INCLUDE_TEMPLATE:
			case self::T_INCLUDE_COMPONENT:

			case self::T_VALUE_STRING:
				/** @noinspection PhpMissingBreakStatementInspection */
			case self::T_OPERATOR:

				if(trim($token_data) == ''){
					$token_data = null;
				}

			// Plain-text tokens are allowed to purely consist of whitespace characters
			// no break
			case self::T_TEXT_PLAIN:

				// Empty boolean and integer tokens are allowed since they can contain a value of "0"

			case self::T_VALUE_BOOLEAN:
			case self::T_VALUE_INT:

				if(is_null($token_data)){

					throw new TokenListException(
						"Token data not allowed
						 to be empty for token \"$token\""
					);
				}

				break;

			// Empty tokens

			case self::T_LOCAL;
			case self::T_RAW;
			case self::T_VALUE_NULL:

				if(trim($token_data) == ''){
					$token_data = null;
				}

				if(!is_null($token_data)){

					throw new TokenListException(
						"Token data not allowed for token \"$token\""
					);
				}

				break;

			// Unknown token

			default:

				throw new TokenListException(
					"Unknown token \"$token\" encountered"
				);
		}

		$this->_tokens[] =
			(is_null($token_data) ? $token : "$token $token_data");

		// Prime the TokenList

		if(count($this->_tokens) == 1){

			list(
				$this->_current_token, $this->_current_data
				) =
				$this->_parseRawToken(reset($this->_tokens));
		}
	}

	/**
	 * Forward the internal pointer to the next item in the TokenList.
	 *
	 * <p>This method will set the "current" token to be the next item in the
	 * internal TokenList. If no next item is present, <em>false</em> is
	 * returned.</p>
	 *
	 * <p>A special token constant {@link TokenList::T_VALUE} is defined which
	 * can be used to represent any other value token. This useful in cases
	 * where the type of value does not matter. This special token constant is
	 * <strong>never</strong> present in an actual TokenList.</p>
	 *
	 * <p>The optional {@link $expected_token} argument can be used to indicate
	 * which token is expected. If the actual token does not match the expected
	 * token an exception is thrown.</p>
	 *
	 * @param string $expected_token
	 *
	 * @return bool
	 * @throws TokenListException
	 * @see nextData()
	 */

	public function nextToken($expected_token = null){

		if(next($this->_tokens) === false){
			return false;
		}

		list(
			$token, $token_data
			) = $this->_parseRawToken(current($this->_tokens));

		// Check expected token

		switch($expected_token){

			// Handle the special "T_VALUE" token

			case self::T_VALUE:

				if(strpos($token, $expected_token) !== 0){

					prev($this->_tokens);

					throw new TokenListException(
						"Expected next token to be
						\"$expected_token\", \"$token\" encountered"
					);
				}

				break;

			default:

				if(!is_null($expected_token) && $expected_token != $token){

					prev($this->_tokens);

					throw new TokenListException(
						"Expected next token to be
						\"$expected_token\", \"$token\" encountered"
					);
				}
		}

		$this->_current_token = $token;
		$this->_current_data = $token_data;

		return true;
	}

	/**
	 * Forward to the next item in the TokenList and return its token data.
	 *
	 * <p>Similar to the {@link nextToken()} method, except for the fact that
	 * this method will return the token data for the next token. If there is
	 * no next token, <em>null</em> is returned.</p>
	 *
	 * @param string $expected_token
	 *
	 * @throws TokenListException
	 * @see nextToken()
	 * @return null
	 */

	public function nextData($expected_token = null){

		if($this->nextToken($expected_token)){

			return $this->_current_data;
		}

		return null;
	}

	/**
	 * Rewind internal pointer to the previous item in the TokenList.
	 *
	 * @throws TokenListException
	 * @return bool
	 */

	public function previousToken(){

		if(prev($this->_tokens) === false){
			return false;
		}

		list(
			$token, $token_data
			) = $this->_parseRawToken(current($this->_tokens));

		$this->_current_token = $token;
		$this->_current_data = $token_data;

		return true;
	}

	/**
	 * Reset the TokenList to its initial state.
	 *
	 * @throws TokenListException
	 * @return void
	 */

	public function reset(){

		if(count($this->_tokens) <= 1){
			return;
		}

		list(
			$this->_current_token, $this->_current_data
			) =
			$this->_parseRawToken(reset($this->_tokens));
	}

	/**
	 * Set the last token in the TokenList as active.
	 *
	 * @throws TokenListException
	 * @return void
	 */

	public function end(){

		if(count($this->_tokens) == 1){
			return;
		}

		list(
			$this->_current_token, $this->_current_data
			) =
			$this->_parseRawToken(end($this->_tokens));
	}

	/**
	 * Parse raw token data into a token and its data.
	 *
	 * <p>Returns an array containing two elements: The token and optionally its
	 * data. If no data is present, the second array element will be set to
	 * <em>null</em>.</p>
	 *
	 * @param string $raw_token
	 *
	 * @throws TokenListException
	 * @return array
	 */

	protected function _parseRawToken($raw_token){

		$token_data = '';

		if(strpos($raw_token, ' ') !== false){

			list($token, $token_data) = explode(' ', $raw_token, 2);
		}
		else{

			$token = $raw_token;
		}

		if(strpos($token, self::T_VALUE) === 0){

			$token_data = $this->_parseValueToken($token, $token_data);
		}

		return [$token, $token_data];
	}

	/**
	 * Parse a value token.
	 *
	 * <p>Used internally by {@link TokenList::_parseRawToken()} to parse
	 * value tokens. If fed with a proper value token it will return the token
	 * value in the correct data type.</p>
	 *
	 * @param string $token
	 * @param mixed $token_data
	 *
	 * @return int|string|bool|null
	 * @throws TokenListException
	 * @see TokenList::_parseRawToken()
	 */

	private function _parseValueToken($token, $token_data){

		switch($token){

			case self::T_VALUE_BOOLEAN:

				return (bool) $token_data;

				break;

			case self::T_VALUE_INT:

				return (int) $token_data;

				break;

			case self::T_VALUE_STRING:

				return (string) $token_data;

				break;

			case self::T_VALUE_ARRAY:

				$array = @unserialize($token_data);

				assert('is_array($array)');
				if(!is_array($array)){
					$array = [];
				}

				return $array;

				break;

			case self::T_VALUE_NULL:

				return null;

				break;

			default:

				throw new TokenListException(
					"Expected next token to be a value token,
					\"$token\" encountered"
				);
		}
	}

	/**
	 * Collect a set of tokens into a new TokenList.
	 *
	 * <p>This method creates a new TokenList and fills it with tokens,
	 * starting at the current token of the old TokenList, until a token
	 * matching {@link $end_token} and optionally {@link $end_data} is
	 * encountered.<br>
	 * If no such token is found before the end of the TokenList, a
	 * {@link TokenListException} gets thrown.</p>
	 *
	 * @param string $end_token
	 * @param mixed $end_data
	 *
	 * @return TokenList
	 * @throws TokenListException
	 */

	public function collectTokens($end_token, $end_data = null){

		$collection = [];

		while($this->nextToken()){

			if($this->token == $end_token){

				if(is_null($end_data) || $end_data == $this->token_data){

					return new TokenList($collection);
				}
			}

			$collection[] = $this->token_raw;
		}

		throw new TokenListException(
			"No matching end token found for token \"$end_token\""
		);
	}
}