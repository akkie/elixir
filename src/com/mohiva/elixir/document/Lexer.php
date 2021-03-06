<?php
/**
 * Mohiva Elixir
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.textile.
 * It is also available through the world-wide-web at this URL:
 * https://github.com/mohiva/elixir/blob/master/LICENSE.textile
 *
 * @category  Mohiva/Elixir
 * @package   Mohiva/Elixir/Document
 * @author    Christian Kaps <christian.kaps@mohiva.com>
 * @copyright Copyright (c) 2007-2012 Christian Kaps (http://www.mohiva.com)
 * @license   https://github.com/mohiva/elixir/blob/master/LICENSE.textile New BSD License
 * @link      https://github.com/mohiva/elixir
 */
namespace com\mohiva\elixir\document;

use DOMAttr;
use com\mohiva\elixir\document\tokens\ExpressionContentToken;
use DOMText;
use DOMComment;
use com\mohiva\common\xml\XMLDocument;
use com\mohiva\common\xml\XMLElement;
use com\mohiva\common\parser\TokenStream;
use com\mohiva\elixir\document\tokens\PropertyToken;
use com\mohiva\elixir\document\tokens\NodeToken;
use com\mohiva\elixir\document\tokens\HelperToken;
use com\mohiva\elixir\document\tokens\ExpressionToken;

/**
 * Tokenize a document.
 *
 * @category  Mohiva/Elixir
 * @package   Mohiva/Elixir/Document
 * @author    Christian Kaps <christian.kaps@mohiva.com>
 * @copyright Copyright (c) 2007-2012 Christian Kaps (http://www.mohiva.com)
 * @license   https://github.com/mohiva/elixir/blob/master/LICENSE.textile New BSD License
 * @link      https://github.com/mohiva/elixir
 */
class Lexer {

	/**
	 * All available document token codes.
	 *
	 * @var int
	 */
	const T_XML_VERSION        = 1;
	const T_XML_ENCODING       = 2;
	const T_ROOT_NODE          = 3;
	const T_ELEMENT_NODE       = 4;
	const T_ELEMENT_HELPER     = 5;
	const T_ATTRIBUTE_HELPER   = 6;
	const T_EXPRESSION         = 7;
	const T_EXPRESSION_OPEN    = 8;  // {%
	const T_EXPRESSION_CLOSE   = 9;  // %}
	const T_EXPRESSION_CHARS   = 10; // .+
	const T_EXPRESSION_ELEMENT = 11; // <tag />
	const T_EXPRESSION_COMMENT = 12; // <!-- comment -->
	const T_EXPRESSION_CDATA   = 13; // <![CDATA[ ]]>
	const T_EXPRESSION_PI      = 14; /* <?PITarget PIContent?> */

	/**
	 * The XPath pattern used to find the expressions inside the XML document.
	 *
	 * @var string
	 */
	const EXPRESSION_QUERY = "(contains(., '{%') or contains(., '%}'))";

	/**
	 * Prevent the lexer to tokenize anything inside the Raw tag of the core namespace.
	 *
	 * It matches only if the node isn't inside a Raw tag.
	 *
	 * <ex:Raw>
	 *     <tag test:Locale="{% test %}" />
	 * </ex:Raw>
	 *
	 * @var string
	 */
	const RAW_QUERY = "not(ancestor::*[namespace-uri() = 'http://elixir.mohiva.com' and local-name() = 'Raw'])";

	/**
	 * The placeholder node.
	 *
	 * @var string
	 */
	const NODE_PLACEHOLDER = '__N_-_O_-_D_-_E__';

	/**
	 * The lexemes to recognize the expressions.
	 *
	 * This approach is used to recognize if a string is escaped or not. Because the first both lexemes
	 * detect all strings first, and thereafter all other tokens. So the following syntax is correctly
	 * recognized as a escaped expression: {% '{% user.name.toLower() %}' %} and not as an nested
	 * expression.
	 *
	 * @var array
	 */
	private $lexemes = array(
		"('(?:[^'\\\\]|\\\\['\"]|\\\\)*')",
		'("(?:[^"\\\]|\\\["\']|\\\)*")',
		'(\{%|%\})',
	);

	/**
	 * Map the constant values with its token type.
	 *
	 * @var int[]
	 */
	private $constTokenMap = array(
		'{%' => self::T_EXPRESSION_OPEN,
		'%}' => self::T_EXPRESSION_CLOSE
	);

	/**
	 * Contains namespaces that can be defined in XML documents, but which
	 * can't contains helpers.
	 *
	 * @var array
	 */
	private $nonHelperNamespaces = array(
		'http://www.w3.org/2001/XInclude',
		'http://www.w3.org/XML/1998/namespace',
		'http://www.w3.org/1999/xhtml',
		'http://www.w3.org/1999/XSL/Transform',
		'http://www.w3.org/1999/Math/MathML',
		'http://www.w3.org/2000/svg',
		'http://www.w3.org/1999/xlink'
	);

	/**
	 * Contains all namespaces found within this document.
	 *
	 * @var array
	 */
	private $namespaces = array();

	/**
	 * Stores the namespace part of a XPATH query to get all elements which
	 * have a helper namespace. This query is used more than once, therefore
	 * it will be cached for reuse.
	 *
	 * @var string
	 */
	private $elNsQuery = null;

	/**
	 * Stores the namespace part of a XPATH query to get all elements or attributes
	 * which have a helper namespace. This query is used more than once, therefore
	 * it will be cached for reuse.
	 *
	 * @var string
	 */
	private $elAtNsQuery = null;

	/**
	 * The pattern used to split the expression tokens.
	 *
	 * @var string
	 */
	private $expressionPattern = null;

	/**
	 * The flags used to split the expression tokens.
	 *
	 * @var int
	 */
	private $expressionFlags = null;

	/**
	 * Contains all original helper nodes before they were processed.
	 *
	 * @var array
	 */
	private $originalHelperNodes = [];

	/**
	 * The class constructor.
	 */
	public function __construct() {

		$this->expressionPattern = '/' . implode('|', $this->lexemes) . '/';
		$this->expressionFlags = PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE;
	}

	/**
	 * Set the file to tokenize and start the tokenize process.
	 *
	 * Note: Use a clone of the document if you scan the file multiple times, because
	 * the lexer changes the document structure. Scanning the same document twice will
	 * not work.
	 *
	 * @param XMLDocument $doc The XML document to tokenize.
	 * @return TokenStream The resulting token stream.
	 */
	public function scan(XMLDocument $doc) {

		$this->namespaces = $doc->getNamespaces($this->nonHelperNamespaces);
		$this->elNsQuery = $this->buildNSQuery($this->namespaces, array('.'));
		$this->elAtNsQuery = $this->buildNSQuery($this->namespaces, array('.', '@*'));

		$stream = $this->tokenize($doc);
		$stream->rewind();

		return $stream;
	}

	/**
	 * Tokenize the given XML document.
	 *
	 * @param XMLDocument $doc The document to tokenize.
	 * @return TokenStream The resulting token stream.
	 */
	private function tokenize(XMLDocument $doc) {

		// The lexer tokenize the XML content from the inside-out,
		// so we iterate in reverse order to start from the root node.
		$stream = new TokenStream;
		$stream->setIteratorMode(TokenStream::IT_MODE_LIFO);
		$stream->setSource($doc->saveXML());

		$expressionQuery = $this->buildNodeExpressionQuery();
		$nodeQuery = $this->buildNodeQuery();
		$nodes = $doc->xpath->query($nodeQuery);
		for ($i = $nodes->length - 1; $i >= 0; $i--) {
			/* @var XMLElement $node */
			$node = $nodes->item($i);
			$path = $node->getNodePath();
			$id = sha1($path);
			$this->originalHelperNodes[$id] = clone $node;

			// NOTE: This order shouldn't be changed, otherwise the parser cannot
			// resolve the Parent/Child relationship
			$this->tokenizeAttributeHelpers($stream, $node);
			$this->tokenizeElementHelper($stream, $node);
			$this->tokenizeExpressions($stream, $node, $expressionQuery);
			$token = new NodeToken(
				$node === $doc->documentElement ? self::T_ROOT_NODE : self::T_ELEMENT_NODE,
				$id,
				$path,
				$node->getLineNo(),
				$this->getAncestor($node),
				$this->getPreviousSibling($node),
				$this->getNextSibling($node),
				$this->getContent($node),
				$this->getChildren($node)
			);

			$placeholder = $doc->createElement(self::NODE_PLACEHOLDER);
			$placeholder->setAttribute('id', $id);
			$node->parentNode->replaceChild($placeholder, $node);
			$stream->push($token);
		}

		$this->originalHelperNodes = [];

		// Iterator mode is set to LIFO, so this are the the first tokens
		$stream->push(new PropertyToken(self::T_XML_ENCODING, $doc->xmlEncoding));
		$stream->push(new PropertyToken(self::T_XML_VERSION, $doc->xmlVersion));

		return $stream;
	}

	/**
	 * Check if the given element is an element helper. If it's a helper then extract
	 * all helper relevant data and push it to the stream.
	 *
	 * @param TokenStream $stream The stream in which the tokens should be written.
	 * @param XMLElement $element The current processed XML element object.
	 */
	private function tokenizeElementHelper(TokenStream $stream, XMLElement $element) {

		if (!in_array($element->namespaceURI, $this->namespaces)) {
			return;
		}

		$query = "@*[not({$this->elNsQuery}) and " . self::EXPRESSION_QUERY . "]";
		$this->tokenizeExpressions($stream, $element, $query);
		$token = new HelperToken(
			self::T_ELEMENT_HELPER,
			sha1($element->getNodePath()),
			$element->getNodePath(),
			$element->getLineNo(),
			$element->localName,
			$element->namespaceURI
		);

		$token->setAttributes($this->getHelperAttributes($element));
		$stream->push($token);
	}

	/**
	 * Check if the given element contains attribute helpers. If it contains
	 * helpers then extract all helper relevant data and push it to the stream.
	 * This method will also remove the found helper from element.
	 *
	 * @param TokenStream $stream The stream in which the tokens should be written.
	 * @param XMLElement $element The current processed XML element object.
	 */
	private function tokenizeAttributeHelpers(TokenStream $stream, XMLElement $element) {

		if (!$this->namespaces) {
			return;
		}

		/* @var \DomNodeList $nodes */
		$nodes = $element("@*[{$this->elNsQuery}]");
		for ($i = $nodes->length - 1; $i >= 0; $i--) {
			/* @var \DOMAttr $attribute */
			$attribute = $nodes->item($i);
			$query = "attribute::*[local-name() = '{$attribute->localName}' and " . self::EXPRESSION_QUERY . "]";
			$this->tokenizeExpressions($stream, $element, $query);
			$token = new HelperToken(
				self::T_ATTRIBUTE_HELPER,
				sha1($attribute->getNodePath()),
				$attribute->getNodePath(),
				$attribute->getLineNo(),
				$attribute->localName,
				$attribute->namespaceURI
			);
			$token->setValue($attribute->value);
			$stream->push($token);

			$attribute->parentNode->removeAttributeNode($attribute);
		}
	}

	/**
	 * Get all expressions from the given element based on the given XPATH query and push
	 * it to the stream.
	 *
	 * @param TokenStream $stream The stream in which the tokens should be written.
	 * @param XMLElement $element The current processed element.
	 * @param string $query The query to get the expressions from element.
	 */
	private function tokenizeExpressions(TokenStream $stream, XMLElement $element, $query) {

		/* @var \DomNodeList $nodes */
		$nodes = $element($query);
		for ($i = $nodes->length - 1; $i >= 0; $i--) {
			$contentStream = new TokenStream;

			/* @var \DOMNode $node */
			$node = $nodes->item($i);
			if ($node->nodeType == XML_ELEMENT_NODE) {
				/* @var XMLElement $node */
				$contentStream = $this->tokenizeExpressionNode($contentStream, $node);
			} else {
				$contentStream = $this->tokenizeExpressionText($contentStream, $node->value, $node->getLineNo());
			}

			$contentStream->rewind();
			$token = new ExpressionToken(
				self::T_EXPRESSION,
				sha1($node->getNodePath()),
				$node->getNodePath(),
				$node->getLineNo(),
				$node->nodeType == XML_ATTRIBUTE_NODE ? /* @var \DOMAttr $node */ $node->name : null,
				$contentStream
			);
			$stream->push($token);
		}
	}

	/**
	 * Tokenize a complete XML node.
	 *
	 * @param TokenStream $stream The stream to which the tokens should be added.
	 * @param XMLElement $element The current processed element.
	 * @return TokenStream The stream with the added tokens.
	 */
	private function tokenizeExpressionNode(TokenStream $stream, XMLElement $element) {

		$nodes = $element->childNodes;
		for ($i = 0; $i < $nodes->length; $i++) {
			$node = $nodes->item($i);
			if ($node->nodeType == XML_ELEMENT_NODE && $node->nodeName === self::NODE_PLACEHOLDER) {
				/* @var XMLElement $node */
				$id = $node['id']->toString();
				$node = $this->originalHelperNodes[$id];
				$token = new ExpressionContentToken(self::T_EXPRESSION_ELEMENT, $node->toXML(), $node->getLineNo());
				$stream->push($token);
			} else if ($node->nodeType == XML_ELEMENT_NODE) {
				/* @var XMLElement $node */
				$token = new ExpressionContentToken(self::T_EXPRESSION_ELEMENT, $node->toXML(), $node->getLineNo());
				$stream->push($token);
			} else if ($node->nodeType == XML_COMMENT_NODE) {
				/* @var \DOMComment $node */
				$value = '<!--' . $node->nodeValue . '-->';
				$token = new ExpressionContentToken(self::T_EXPRESSION_COMMENT, $value, $node->getLineNo());
				$stream->push($token);
			} else if ($node->nodeType == XML_PI_NODE) {
				/* @var \DOMProcessingInstruction $node */
				$value = '<?' . $node->target . ' ' . $node->data . '?>';
				$token = new ExpressionContentToken(self::T_EXPRESSION_PI, $value, $node->getLineNo());
				$stream->push($token);
			} else if ($node->nodeType == XML_CDATA_SECTION_NODE) {
				/* @var \DOMCdataSection $node */
				$value = '<![CDATA[' . $node->nodeValue . ']]>';
				$token = new ExpressionContentToken(self::T_EXPRESSION_CDATA, $value, $node->getLineNo());
				$stream->push($token);
			} else if ($node->nodeType == XML_TEXT_NODE) {
				/* @var \DOMText $node */
				$stream = $this->tokenizeExpressionText($stream, $node->nodeValue, $node->getLineNo());
			}
		}

		return $stream;
	}

	/**
	 * Tokenize the text part of a node.
	 *
	 * This method tokenize the text parts of a node and splits it in three kind of tokens. The main
	 * task of this tokenize process is to find all non escaped start or end tokens of an expression.
	 * The expression itself will then be parsed later by the Mohiva Pyramid library.
	 *
	 * @param TokenStream $stream The stream to which the tokens should be added.
	 * @param string $text The text part of an expression to tokenize.
	 * @param int $line The line in which the text was found.
	 * @return TokenStream The stream with the added tokens.
	 */
	private function tokenizeExpressionText(TokenStream $stream, $text, $line) {

		$matches = preg_split($this->expressionPattern, $text, -1, $this->expressionFlags);
		$cnt = count($matches);
		for ($i = 0; $i < $cnt; $i++) {
			if (isset($this->constTokenMap[$matches[$i]])) {
				$code = $this->constTokenMap[$matches[$i]];
			} else {
				$code = self::T_EXPRESSION_CHARS;
			}

			$stream->push(new ExpressionContentToken($code, $matches[$i], $line));
			if ($cnt > 1) $line += substr_count($matches[$i], "\n");
		}

		return $stream;
	}

	/**
	 * Get all helper attributes from the given element.
	 *
	 * @param XMLElement $element The current processed XML element object.
	 * @return array All namespaced attributes as name => value pair.
	 */
	private function getHelperAttributes(XMLElement  $element) {

		$attributes = array();
		foreach ($element->attributes as $attribute) {
			/* @var \DOMAttr $attribute */
			if (!$this->isHelperAttribute($attribute)) {
				$attributes[$attribute->name] = $attribute->value;
			}
		}

		return $attributes;
	}

	/**
	 * Check if the given DOMAttr element is an helper attribute.
	 *
	 * Helper attributes have its own namespace.
	 *
	 * @param \DOMAttr $attribute The attribute to check.
	 * @return boolean True if the attribute is an helper attribute, false otherwise.
	 */
	private function isHelperAttribute(DOMAttr $attribute) {

		return $attribute->namespaceURI ? true : false;
	}

	/**
	 * Get the ID of the ancestor node for the given element.
	 *
	 * @param XMLElement $element The current processed element.
	 * @return string The ID of the ancestor node for the given element.
	 */
	private function getAncestor(XMLElement $element) {

		if (!$this->namespaces || $element === $element->ownerDocument->documentElement) {
			return null;
		}

		/* @var \DOMNodeList $ancestors */
		$ancestors = $element("ancestor::*[{$this->elAtNsQuery}]");
		if ($ancestors->length) {
			$ancestor = $ancestors->item($ancestors->length - 1);
		} else {
			$ancestor = $element->ownerDocument->documentElement;
		}

		return sha1($ancestor->getNodePath());
	}

	/**
	 * Get the ID of the previous sibling node for the given element.
	 *
	 * It returns only the ID if the previous sibling is an elixir node. If the previous
	 * sibling is a non elixir element then this method returns null.
	 *
	 * @param XMLElement $element The current processed element.
	 * @return string The ID of the previous sibling node for the given element.
	 */
	private function getPreviousSibling(XMLElement $element) {

		if (!$this->namespaces || $element === $element->ownerDocument->documentElement) {
			return null;
		}

		/* @var \DOMNodeList $siblings */
		$siblings = $element("preceding-sibling::*");
		if (!$siblings->length) {
			return null;
		}

		$sibling = $siblings->item($siblings->length - 1);
		if (!in_array($sibling->namespaceURI, $this->namespaces)) {
			return null;
		}

		return sha1($sibling->getNodePath());
	}

	/**
	 * Get the ID of the next sibling node for the given element.
	 *
	 * It returns only the ID, if the next sibling is an elixir node. If the next
	 * sibling is a non elixir element, then this method returns null.
	 *
	 * The document will be tokenized from the inside out, so it isn't possible to get the
	 * original following siblings, because the nodes are all replaced with the placeholder
	 * node {@see self::PLACEHOLDER}. So we must look for this placeholder nodes.
	 *
	 * @param XMLElement $element The current processed element.
	 * @return string The ID of the next sibling node for the given element.
	 */
	private function getNextSibling(XMLElement $element) {

		if (!$this->namespaces || $element === $element->ownerDocument->documentElement) {
			return null;
		}

		/* @var \DOMNode $sibling */
		$sibling = $element("#following-sibling::*");
		if (!$sibling || $sibling->localName != self::NODE_PLACEHOLDER) {
			return null;
		}

		return $sibling['id']->toString();
	}

	/**
	 * Get all children from the given element. The children are the previously
	 * replaced placeholder nodes. {@see self::PLACEHOLDER}
	 *
	 * @param XMLElement $element The current processed element.
	 * @return array A list with child id's in ascending order.
	 */
	private function getChildren(XMLElement $element) {

		$children = array();
		if (!$this->namespaces) {
			return $children;
		}

		/* @var \DOMNodeList $nodes */
		$nodes = $element("descendant-or-self::*[local-name() = '" . self::NODE_PLACEHOLDER . "']");
		for ($i = 0; $i < $nodes->length; $i++) {
			$children[] = $nodes->item($i)['id']->toString();
		}

		return $children;
	}

	/**
	 * Get the content for the given node. This method removes all helper related tags
	 * or attributes from XML and return the result as string.
	 *
	 * @param XMLElement $element The current processed element.
	 * @return string The content of the given XML node.
	 */
	private function getContent(XMLElement $element) {

		if ($this->hasHelperNamespace($element)) {
			return $this->getChildNodesAsContent($element);
		}

		return $this->getCompleteNodeAsContent($element);
	}

	/**
	 * @param XMLElement $element The current processed element.
	 * @return string The string representation of the element.
	 */
	private function getCompleteNodeAsContent(XMLElement $element) {

		$this->removeHelperNamespaces($element);

		/* @var \DOMNodeList $comments */
		$comments = $element('.//comment()');
		for ($i = 0; $i < $comments->length; $i++) {
			/* @var \DOMComment $comment*/
			$comment = $comments->item($i);
			if ($this->getComment($comment) == '') {
				$comment->parentNode->removeChild($comment);
			}
		}

		return $element->toXML();
	}

	/**
	 * @param XMLElement $element The current processed element.
	 * @return string The string representations of the elements child nodes.
	 */
	private function getChildNodesAsContent(XMLElement $element) {

		$content = '';
		foreach ($element->childNodes as $child) {
			/* @var XMLElement $child */
			if ($child->nodeType == XML_ELEMENT_NODE) {
				$this->removeHelperNamespaces($child);
				$content .= $child->toXML();
			} else if ($child->nodeType == XML_COMMENT_NODE) {
				/* @var DOMComment $child */
				$content .= $this->getComment($child);
			} else {
				$content .= $child->nodeValue;
			}
		}

		return $content;
	}

	/**
	 * Gets the comment as string.
	 *
	 * If a comment starts with <!--% and ends with %--> then it will be removed, otherwise
	 * the comments will be preserved.
	 *
	 * @param \DOMComment $comment The comment node.
	 * @return string The comment as string.
	 */
	private function getComment(DOMComment $comment) {

		if (preg_match('/^%.*%$/ms', $comment->nodeValue)) {
			return '';
		}

		return '<!--' . $comment->nodeValue . '-->';
	}

	/**
	 * @param XMLElement $element The current processed element.
	 * @return bool True if the element has a helper namespace.
	 */
	private function hasHelperNamespace(XMLElement $element) {

		if (in_array($element->namespaceURI, $this->namespaces)) {
			return true;
		}

		return false;
	}

	/**
	 * Remove all helper namespaces from the given element and his descendants.
	 *
	 * @param XMLElement $element The current processed node.
	 */
	private function removeHelperNamespaces(XMLElement $element) {

		$rawQuery = self::RAW_QUERY;
		$namespaces = $element("descendant-or-self::*/namespace::*[{$rawQuery}]");
		foreach ($namespaces as $node) { /* @var \DOMElement $node */
			if (!in_array($node->nodeValue, $this->nonHelperNamespaces)) {
				$node->parentNode->removeAttributeNS($node->nodeValue, $node->prefix);
			}
		}
	}

	/**
	 * Build the XPath query to find all nodes.
	 *
	 * Nodes are XML tags which contains at least one helper. An exception is the root node of a
	 * document. This is always a node, even if no helper is located on it.
	 *
	 * @return string The XPath query to find all nodes.
	 */
	private function buildNodeQuery() {

		/**
		 * Prevents the lexer to find an expression inside a Raw tag.
		 */
		$rawQuery = self::RAW_QUERY;

		/**
		 * Finds the root node.
		 */
		$query = '/*';

		/**
		 * Finds all nodes which have at least one element helper and which are not inside a Raw tag.
		 */
		if ($this->elNsQuery) $query .= "|//*[{$this->elNsQuery} and {$rawQuery}]";

		/**
		 * Finds all nodes which have at least one attribute helper and which not are inside a Raw tag.
		 */
		if ($this->elNsQuery) $query .= "|//@*[{$this->elNsQuery}]/parent::*[{$rawQuery}]";

		return $query;
	}

	/**
	 * Builds the XPath query to find all expressions inside a node.
	 *
	 * Note: All expressions inside element or attribute helpers will be matched in the
	 * `tokenizeElementHelper` or `tokenizeAttributeHelpers` methods.
	 *
	 * @return string The XPath query to find all expressions inside a node.
	 */
	private function buildNodeExpressionQuery() {

		/**
		 * Finds the expressions inside the selected node.
		 */
		$expQuery = self::EXPRESSION_QUERY;

		/**
		 * Prevents the lexer to find an expression inside a Raw tag.
		 */
		$rawQuery = self::RAW_QUERY;

		/**
		 * This query prevents the node expression query to find expressions in attribute helpers. So
		 * the query does not match the following snippet, because the expression is located inside
		 * the ex:Locale attribute helper.
		 *
		 * <test ex:Locale="{% var %}"></test>
		 */
		$parentNSQuery = $this->buildNSQuery($this->namespaces, array('parent::*'));

		/**
		 * For attribute expressions in the content of a node.
		 *
		 * <root>
		 *    <child test="{% var %}"></child>
		 * </root>
		 */
		$query = "descendant::*//@*[{$rawQuery} and {$expQuery}]";

		/**
		 * For attribute expressions on the same node. This is typically a node which contains an other
		 * attribute helper. In this example the child tag is a node, because it contains the ex:Locale
		 * attribute helper. So this query matches only the expression in the test attribute. The expression
		 * in the helper will be matched in the method `tokenizeAttributeHelpers()`.
		 *
		 * <root>
		 *    <child test="{% var %}" ex:Locale="{% var %}" />
		 * </root>
		 */
		if ($parentNSQuery) $query .= "|@*[not({$parentNSQuery}) and {$rawQuery} and {$expQuery}]";

		/**
		 * Or for attribute expressions on the root node when it isn't a element helper.
		 *
		 * <root test="{% var %}"></root>
		 */
		else $query .= "|@*[{$rawQuery} and {$expQuery}]";

		/**
		 * For text expressions in the content of a node.
		 *
		 * <root>
		 *    {% var %}
		 * </root>
		 */
		$query .= "|descendant-or-self::*//text()[{$rawQuery} and {$expQuery}]/parent::*";

		return $query;
	}

	/**
	 * Create the namespace part of a XPATH query to get the given elements
	 * which have a helper namespace.
	 *
	 * @param array $namespaces A list with namespaces.
	 * @param array $elements The elements on which the namespace is located.
	 * @return string A string containing a list of namespaces separated by the or keyword.
	 */
	private function buildNSQuery(array $namespaces, array $elements) {

		$uris = array();
		foreach ($namespaces as $nsUri) {
			foreach ($elements as $element) {
				$uris[] = "namespace-uri({$element}) = '{$nsUri}'";
			}
		}

		$nsString = $uris ? "(" . implode(' or ', $uris) . ")" : '';

		return $nsString;
	}
}
