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

use com\mohiva\common\parser\TokenStream;

/**
 * A lexical pre processor which transforms the source based token stream into a more node based token stream.
 *
 * @category  Mohiva/Elixir
 * @package   Mohiva/Elixir/Document
 * @author    Christian Kaps <christian.kaps@mohiva.com>
 * @copyright Copyright (c) 2007-2012 Christian Kaps (http://www.mohiva.com)
 * @license   https://github.com/mohiva/elixir/blob/master/LICENSE.textile New BSD License
 * @link      https://github.com/mohiva/elixir
 */
class PreProcessor {

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

	/**
	 * The placeholder node.
	 *
	 * @var string
	 */
	const NODE_PLACEHOLDER = '__N_-_O_-_D_-_E__';

	/**
	 * The lexemes to recognize the expressions inside the attributes.
	 *
	 * @var array
	 */
	private $lexemes = array(
		'({%(?!%))(.*?)((?<!%)%})',
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
	 * The pattern used to split the tokens.
	 *
	 * @var string
	 */
	private $pattern = null;

	/**
	 * The flags used to split the tokens.
	 *
	 * @var null
	 */
	private $flags = null;

	/**
	 * The class constructor.
	 */
	public function __construct() {

		$this->pattern = '/' . implode('|', $this->lexemes) . '/';
		$this->flags = PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE;
	}

	/**
	 * Process the source token stream and convert it into a more node oriented token stream.
	 *
	 * @param TokenStream $stream The source token stream.
	 */
	public function process(TokenStream $stream) {

		$tree = new NodeTree();
		$content = '';
		while ($stream->valid()) {
			/* @var tokens\SourceToken $token */
			$token = $stream->current();
			switch ($token->getCode()) {
				case StringLexer::T_TAG_OPEN:
					$content .= $this->parseTag($stream, $tree);
					break;

				case StringLexer::T_COMMENT_OPEN:
					$content .= $this->parseTag($stream, $tree);
					break;

				default:
					$content .= $token->getValue();
					$stream->next();
					break;
			}
		}

		echo $content;
	}

	private function parseTag(TokenStream $stream, NodeTree $tree) {

		/* @var tokens\SourceToken $token */
		$token = $stream->current();
		$content = $token->getValue();
		$stream->next();

		while ($stream->valid()) {
			$token = $stream->current();
			switch ($token->getCode()) {
				case StringLexer::T_NAME:
					$content .= $this->parseTagName($stream, $tree);
					break;

				default:
					$content .= $token->getValue();
					$stream->next();
					break;
			}
		}

		return $content;
	}

	private function parseTagName(TokenStream $stream, NodeTree $tree) {

		/* @var tokens\SourceToken $token */
		$token = $stream->current();
		$content = $token->getValue();
		$stream->next();



		return $content;
	}

	private function parseComment(TokenStream $stream, NodeTree $tree) {

		/* @var tokens\SourceToken $token */
		$token = $stream->current();
		$content = $token->getValue();
		$stream->next();



		return $content;
	}

	private function parseAttribute(TokenStream $stream) {


	}
}
