<?php
/**
 * @file Node.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

use Exception;
use StudyPortals\Exception\PHPAssertionFailed;
use StudyPortals\Exception\Silenced;

/**
 * Single Node class.
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
abstract class Node implements Silenced{

	protected $_Parent;

	/**
	 * Construct a new Template Node.
	 *
	 * @param NodeTree $Parent
	 *
	 * @throws TemplateException
	 */

	public function __construct(NodeTree $Parent){

		$Parent->appendChild($this);

		$this->_Parent = $Parent;
	}

	/**
	 * Remove the Node's parent-reference upon cloning.
	 *
	 * <p>When a Node is cloned it is "lifted" from its current template tree and
	 * effectively becomes the root Node of its own template tree. This prevents
	 * unexpected recursions in the template tree and allows you to insert a
	 * cloned instance of the Node into the three it was originally also part of.</p>
	 *
	 * <p>If the node has any children (c.q. is an instance of {@link NodeTree}),
	 * the {@link NodeTree::__clone()} method will restore the parent-references
	 * for all child nodes in the tree, leaving only the top-most Node (which had
	 * the "clone" operator applied to it) without a parent.</p>
	 *
	 * @return void
	 * @see NodeTree::__clone()
	 */

	public function __clone(){

		$this->_Parent = null;
	}

	/**
	 * Check if the provided string is valid as a name for a Node.
	 *
	 * @param string $name
	 *
	 * @return bool
	 */

	protected function _isValidName($name){

		return !is_numeric($name) || !preg_match('/^[A-Z0-9_]+$/i', $name);
	}

	/**
	 * Get the root Node of the template tree.
	 *
	 * @return Node|NodeTree
	 */

	public function getRoot(){

		if(!($this->_Parent instanceof NodeTree)){

			// The root node should be a NodeTree

			assert(
				'$this instanceof StudyPortals\Framework\Template4\NodeTree'
			);

			return $this;
		}

		else{

			return $this->_Parent->getRoot();
		}
	}

	/**
	 * Display the Node.
	 *
	 * @return string
	 */

	abstract public function display();

	/**
	 * Display a string representation of the Node.
	 *
	 * <p>If an exception occurs while generating the string representation, this
	 * exception caught and an empty string is returned. This prevents PHP from
	 * generating a fatal error under these circumstances.</p>
	 *
	 * @return string
	 * @see Node::display()
	 */

	public function __toString(){

		$output = '';

		try{

			try{

				$output = $this->display();

				assert('is_string($output)');
			}
			catch(Exception $e){

				assert('false /* Exception: ' . $e->getMessage() . ' */');
			}
		}
		catch(PHPAssertionFailed $e){

			echo $e;
			die();
		}

		return (string) $output;
	}
}