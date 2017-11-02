<?php
/**
 * @file Replace.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

/**
 * Allows variables to be inserted in the Template.
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
class Replace extends Node{

	protected $_replace;

	protected $_local = false;
	protected $_raw = false;

	/**
	 * Construct a new Replace Node.
	 *
	 * <p>The contents of this Node will be replaced by a value specified during
	 * runtime. The {@link $replace} parameter is the name of the value which
	 * will contain the replacement content.</p>
	 *
	 * <p>The optional {@link $local} parameter indicates whether the entire
	 * Template tree should be searched for the replace value, or only the local
	 * scope should be used.<br>
	 * Local scope in this case refers to the "virtual" Template tree as defined
	 * in the description of the {@link NodeTree::getChildByName()} method.</p>
	 *
	 * <p>The optional {@link $raw} parameter indicates whether the value should
	 * be treated as raw HTML. When enabled, the value will <strong>not</strong>
	 * filtered to prevent accidental inclusion of HTML into the template through
	 * the replace mechanism.</p>
	 *
	 * @param NodeTree $Parent
	 * @param string $replace
	 * @param boolean $local
	 * @param boolean $raw
	 *
	 * @throws TemplateException
	@see TemplateNodeTree::setValue()
	 */

	public function __construct(NodeTree $Parent, $replace, $local = null,
		$raw = null){

		if(!$this->_isValidName($replace)){

			throw new TemplateException(
				"Invalid name \"$replace\"
				specified for Replace node"
			);
		}

		parent::__construct($Parent);

		$this->_replace = $replace;

		$this->_local = (is_null($local) ? $this->_local : (bool) $local);
		$this->_raw = (is_null($raw) ? $this->_raw : (bool) $raw);
	}

	/**
	 * Display the Replace Node.
	 *
	 * <p>This method searches the Template tree for a value matching
	 * {@link $Replace::$_replace} and returns its contents if found. If not
	 * found, an empty string is returned.</p>
	 *
	 * @return string
	 * @see Node::display()
	 * @see TemplateNodeTree::getValue()
	 */

	public function display(){

		$value =
			(string) $this->_Parent->getValue($this->_replace, $this->_local);

		if(!$this->_raw){

			$value = htmlspecialchars(
				$value,
				ENT_COMPAT | ENT_HTML401,
				DEFAULT_CHARSET
			);
		}

		return $value;
	}
}