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
use com\mohiva\elixir\document\tokens\SourceToken;

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
class StringLexer {

	/**
	 * All available document token codes.
	 *
	 * @var int
	 */
	const T_TAG_OPEN           = 1;  // <
	const T_TAG_CLOSE          = 2;  // >
	const T_TAG_CLOSER         = 3;  // /
	const T_COMMENT_OPEN       = 4;  // <!--
	const T_COMMENT_CLOSE      = 5;  // -->
	const T_EXPRESSION_OPEN    = 6;  // {%
	const T_EXPRESSION_CLOSE   = 7;  // %}
	const T_COLON              = 8;  // :
	const T_EQUAL              = 9;  // =
	const T_DOUBLE_QUOTE       = 10; // "
	const T_SINGLE_QUOTE       = 11; // '
	const T_NAME               = 12; // \w+
	const T_WHITESPACE         = 13; // \s+
	const T_CONTENT            = 14; // .*

	/**
	 * The lexemes to find the tokens.
	 *
	 * @var array
	 */
	private $lexemes = array(
		'(<!--)', // Comment open
		'(-->)', // Comment close

		/* Must be defined before the tags, to catch < or > inside attributes or expressions */
		"(?:(\\w+)(:))?(\\w+)(=)(')((?:[^'\\\\]|\\\\['\"]|\\\\)*)(')", // Attribute single quoted
		'(?:(\w+)(:))?(\w+)(=)(")((?:[^"\\\]|\\\["\']|\\\)*)(")', // Attribute double quoted
		'({%(?!%))(.*?)((?<!%)%})', // Text expressions
		/* end */

		'(<)(\/?)(?:(\w+)(:))?(\w+)|(\/?)(>)', // Tag open and close
	);

	/**
	 * Map the constant values with its token type.
	 *
	 * @var int[]
	 */
	private $constTokenMap = array(
		'<'    => self::T_TAG_OPEN,
		'>'    => self::T_TAG_CLOSE,
		'/'    => self::T_TAG_CLOSER,
		'<!--' => self::T_COMMENT_OPEN,
		'-->'  => self::T_COMMENT_CLOSE,
		'{%'   => self::T_EXPRESSION_OPEN,
		'%}'   => self::T_EXPRESSION_CLOSE,
		':'    => self::T_COLON,
		'='    => self::T_EQUAL,
		'"'    => self::T_DOUBLE_QUOTE,
		'\''   => self::T_SINGLE_QUOTE,
	);

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
		$this->flags = PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE;
	}

	/**
	 * Tokenize the given input string and return the resulting token stream.
	 *
	 * @param string $input The string input to scan.
	 * @return TokenStream The resulting token stream.
	 */
	public function scan($input) {

		$stream = $this->tokenize($input);
		$stream->rewind();

		return $stream;
	}

	/**
	 * Transform the input string into a token stream.
	 *
	 * @param string $input The string input to tokenize.
	 * @return TokenStream The resulting token stream.
	 */
	private function tokenize($input) {

		$stream = new TokenStream;
		$stream->setSource($input);

		$line = 1;
		$matches = preg_split($this->pattern, $input, -1, $this->flags);
		$cnt = count($matches);
		for ($i = 0; $i < $cnt; $i++) {

			$value = $matches[$i][0];
			$offset = $matches[$i][1];
			if (isset($this->constTokenMap[$value])) {
				$code = $this->constTokenMap[$value];
			} else if (preg_match('/\w+/', $value)) {
				$code = self::T_NAME;
			} else if (ctype_space($value)) {
				$code = self::T_WHITESPACE;
			} else {
				$code = self::T_CONTENT;
			}

			$stream->push(new SourceToken(
				$code,
				$value,
				$line,
				$offset
			));

			if ($cnt > 1) $line += substr_count($value, "\n");
		}

		return $stream;
	}
}
