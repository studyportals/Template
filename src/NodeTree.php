<?php
/**
 * @file NodeTree.php
 *
 * @author Rob van den Hout <vdhout@studyportals.com>
 * @version 1.0.0
 * @copyright © 2017 StudyPortals B.V., all rights reserved.
 */

namespace StudyPortals\Template;

/**
 * NodeTree.
 *
 * <p>Extends {@link Node} with the ability to contain child Nodes and
 * the ability to operate in a Template tree consisting of a mixed set of
 * {@link NodeTree}  and {@link TemplateNodeTree} objects.</p>
 *
 * @package StudyPortals.Framework
 * @subpackage Template4
 */
abstract class NodeTree extends Node{

	protected $_children = [];

	/**
	 * Create a "deep" clone of the NodeTree.
	 *
	 * <p>By default PHP creates a "shallow" clone which means that only the
	 * current object is cloned. All properties which reference other objects
	 * keep their original reference. This is not what we want when we clone a
	 * Node<strong>Tree</strong>.</p>
	 *
	 * @return void
	 * @see Node::__clone()
	 */

	public function __clone(){

		foreach($this->_children as $index => $Child){

			$Child = clone $Child;

			// Replace child with its clone and re-reference the parent Node

			$this->_children[$index] = $Child;
			$Child->_Parent = $this;
		}

		parent::__clone();
	}

	/**
	 * Get a value from the Template tree.
	 *
	 * <p>This method serves as a wrapper for {@link TemplateNodeTree::getvalue()}.
	 * Usually, a Template tree is acombination of {@link NodeTree} and
	 * {@link TemplateodeTree} classes. This method allows calls to
	 * {@link TemplateNodeTree::getvalue()} to travel through the entire tree.<br>
	 * In the context of a tree consisting purely of {@link NodeTree} classes
	 * this method has no use and will simply iterate up the tree until the top
	 * node is reached, at which point <em>null</em> will be returned.</p>
	 *
	 * @param string $name
	 * @param boolean $local
	 *
	 * @return mixed
	 * @see TemplateNodeTree::getValue()
	 * @see TemplateNodeTree::getChildByName()
	 */

	public function getValue($name, $local = false){

		if(is_null($this->_Parent)){
			return null;
		}

		return $this->_Parent->getValue($name, $local);
	}

	/**
	 * Return this Node's <strong>named</strong> descendant with a matching name.
	 *
	 * <p>As only Nodes inheriting from {@link TemplateNodeTree} can have a name,
	 * there can be several levels of {@link NodeTree} Nodes in between the
	 * current Node and the first named child Node requested.<br>
	 * This method traverses a "virtual" Template tree only containing the Nodes
	 * inheriting from {@link TemplateNodeTree}. While the specified named Node
	 * is not found, but there are still {@link NodeTree} children, this
	 * method will recurse. The "virtual" Template tree thus consists only of
	 * {@link TemplatNodeTree} Nodes.</p>
	 *
	 * <p>As these "virtual child" lookups are <strong>extremely</strong>
	 * expensive, the {@link TemplateNodeTree::getChildByName()} method
	 * extends this base method by providing a virtual child cache.<br>
	 * Once a child is located, it is stored in the cache and no recursion
	 * through the template tree is required anymore.</p>
	 *
	 * @param string $name
	 *
	 * @return TemplateNodeTree
	 * @throws NodeNotFoundException
	 * @see TemplateNodeTree::hasChildByName()
	 */

	public function getChildByName($name){

		if(isset($this->_children[$name])
			&& $this->_children[$name] instanceof TemplateNodeTree){

			return $this->_children[$name];
		}

		// Virtual child

		else{

			foreach($this->_children as $Node){

				if($Node instanceof NodeTree
					&& !($Node instanceof TemplateNodeTree)){

					try{

						return $Node->getChildByName($name);
					}

					catch(NodeNotFoundException $e){

						continue;
					}
				}
			}
		}

		throw new NodeNotFoundException(
			"Unable to find node with name \"$name\""
		);
	}

	/**
	 * Check if this Node has a *named* descendant with a matching name.
	 *
	 * <p>This method provides some "syntactic sugar" around the {@link
	 * NodeTree::getChildByName()} method. Instead of returning the Node
	 * found or throwing an exception, this method only returns <em>true</em>
	 * or <em>false</em>. This method thus also utilises the virtual child
	 * cache.</p>
	 *
	 * @param string $name
	 *
	 * @return bool
	 * @see NodeTree::getChildByName()
	 */

	public function hasChildByName($name){

		try{

			$this->getChildByName($name);

			return true;
		}

		catch(NodeNotFoundException $e){

			return false;
		}
	}

	/**
	 * Add a Node as a child to the current Node.
	 *
	 * <p><strong>Note:</strong> If you create a new Node (through its constructor),
	 * it is automatically appended to the Parent specified in said constructor.</p>
	 *
	 * <p>See the description of {@link NodeTree::replaceChild()} for some
	 * <strong>important</strong> remarks concerning the appending of nodes
	 * which are already part of the current template tree.</p>
	 *
	 * @param Node $Child
	 *
	 * @return void
	 * @throws TemplateException
	 * @see NodeTree::replaceChild()
	 */

	public function appendChild(Node $Child){

		if($Child instanceof TemplateNodeTree){

			// Prevent nodes from being appended (c.q. referenced) into their own template trees

			if($this->getRoot() === $Child->getRoot()){

				throw new TemplateException(
					"Cannot append node
					\"{$Child->getName()}\", already part of the same template tree"
				);
			}

			elseif($this->hasChildByName($Child->getName())){

				throw new TemplateException(
					"Cannot append node
					\"{$Child->getName()}\", a node with this name already exists"
				);
			}

			$this->_children[$Child->getName()] = $Child;
		}

		else{

			$this->_children[] = $Child;
		}
	}

	/**
	 * Replace a child Node with another Node.
	 *
	 * <p><strong>Note:</strong> If you intent to use this method (or the fact
	 * that its automatically called from {@link TemplateNodeTree::__set()}) to
	 * dynamically extend a template with Nodes from the same template,
	 * <strong>clone</strong> the Node <em>before</em> passing it into this
	 * method.<br>
	 * If you do not do this, you will most likely run into the recursion/memory
	 * limits of PHP as there are going to be interactions between the
	 * "replaced" node (which is now more or less referenced multiple times in
	 * the same tree) that you cannot foresee easily.</p>
	 *
	 * <p>This method will attempt to warn you by throwing a
	 * {@link TemplateException} when it  detects the {@link $ReplaceChilde} is
	 * <em>$this</em> or one of its children. This prevents most problems, but
	 * there are scenarios which are not caught.<br>
	 * If you ever run into the function recursion limit of PHP in the
	 * {@link NodeTree::__clone()} method, the error is most likely as
	 * described above.</p>
	 *
	 * @param TemplateNodeTree $Child
	 * @param TemplateNodeTree $ReplaceChild
	 *
	 * @return void
	 * @throws TemplateException
	 * @see NodeTree::__clone()
	 * @see TemplateNodeTree::__set()
	 */

	public function replaceChild(TemplateNodeTree $Child,
		TemplateNodeTree $ReplaceChild){

		// Prevent nodes from being added (c.q. referenced) into their own template trees

		if($Child->getRoot() === $ReplaceChild->getRoot()){

			throw new TemplateException(
				"Cannot replace node
				\"{$ReplaceChild->getName()}\", already part of the same template tree"
			);
		}

		foreach($this->_children as $index => $myChild){

			if($myChild === $Child){

				$Child->_Parent = null;

				$ReplaceChild->_Parent = $this;
				$ReplaceChild->_name = $Child->getName();

				$this->_children[$index] = $ReplaceChild;

				return;
			}
		}

		throw new NodeNotFoundException(
			"Cannot replace node \"{$Child->getName()}\",
			not a child node"
		);
	}

	/**
	 * Remove a descendant Node from this Node.
	 *
	 * @param Node $Child
	 *
	 * @return void
	 * @throws NodeNotFoundException
	 */

	public function removeChild(Node $Child){

		foreach($this->_children as $index => $myChild){

			if($myChild === $Child){

				unset($this->_children[$index]);

				return;
			}
		}

		throw new NodeNotFoundException(
			"Cannot remove node
			of type \"" . get_class($Child) . "\", not a child node"
		);
	}

	/**
	 * Display the node tree.
	 *
	 * <p>Calls Node::display() on all of its children.</p>
	 *
	 * @return string
	 * @see Node::display()
	 */

	public function display(){

		$output = '';

		/** @var Node $Child */
		foreach($this->_children as $Child){

			$output .= $Child->display();
		}

		return $output;
	}

	/**
	 * Reset the template to its initial state.
	 *
	 * <p>This method clears all stored values from the Template (c.q. this Node
	 * and all its {@link NodeTree} based descendants). This method does
	 * <strong>not</strong> reset changes made to the structure of the Template,
	 * by adding or removing Nodes.</p>
	 *
	 * @return void
	 */

	public function resetTemplate(){

		foreach($this->_children as $Child){

			if($Child instanceof NodeTree){
				$Child->resetTemplate();
			}
		}
	}
}