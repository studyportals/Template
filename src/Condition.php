<?php
/**
 * @file Condition.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

use StudyPortals\Exception\ExceptionHandler;

/**
 * Allows if-constructions in the template.
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
class Condition extends NodeTree{

	protected $_condition;
	protected $_operator;
	protected $_value;
	protected $_value_set = [];

	protected $_local = false;

	/**
	 * Construct a new Condition Node.
	 *
	 * <p>The contents of this Node will be displayed based upon a condition
	 * evaluated at runtime. The {@link $condition}, {@link $operator} and
	 * {@link value} parameters define the condition to be evaluated. The
	 * {@link $condition} parameter contains the name of the value queried from
	 * the Template tree.</p>
	 *
	 * <p>The optional {@link $local} parameter indicates whether the entire
	 * Template tree should be searched for the condition value, or only the
	 * local scope should be used.<br>
	 * Local scope in this case refers to the "virtual" Template tree as defined
	 * in the description of the {@link NodeTree::getChildByName()} method.</p>
	 *
	 * @param NodeTree $Parent
	 * @param string $condition
	 * @param string $operator
	 * @param string|array $value
	 * @param boolean $local
	 *
	 * @throws TemplateException
	 */

	public function __construct(NodeTree $Parent, $condition, $operator, $value,
		$local = null){

		if(!$this->_isValidName($condition)){

			throw new TemplateException(
				"Invalid condition \"$condition\"
				specified for Condition node"
			);
		}

		parent::__construct($Parent);

		$this->_condition = $condition;
		$this->_operator = $operator;
		$this->_local = (is_null($local) ? $this->_local : (bool) $local);

		if($this->_operator == 'in' || $this->_operator == '!in'){

			if(!is_array($value)){

				throw new TemplateException(
					"Invalid set-value specified for
					Condition node \"$condition\""
				);
			}

			$this->_value_set = $value;
		}
		else{

			$this->_value = $value;
		}
	}

	/**
	 * Execute the comparison stored in this Node on the provided value.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */

	public function compareValue($value){

		switch($this->_operator){

			// Scalar

			case '==':
				return $value == $this->_value;
			case '!=':
				return $value != $this->_value;
			case '<':
				return $value < $this->_value;
			case '<=':
				return $value <= $this->_value;
			case '>':
				return $value > $this->_value;
			case '>=':
				return $value >= $this->_value;

			// Set

			case 'in':
			case '!in':

				$match = ($this->_operator == 'in' ? false : true);

				foreach($this->_value_set as $element){

					if($value == $element){

						$match = ($this->_operator == 'in' ? true : false);
						break;
					}
				}

				return $match;

			default:

				ExceptionHandler::notice(
					"Unknown comparison operator {$this->_operator} encountered"
				);
				return false;
		}
	}

	/**
	 * Display the contents of the condition node.
	 *
	 * @return string
	 * @see NodeTree::display()
	 */

	public function display(){

		if(!$this->compareValue(
			$this->_Parent->getValue($this->_condition, $this->_local)
		)){
			return '';
		}

		return parent::display();
	}
}