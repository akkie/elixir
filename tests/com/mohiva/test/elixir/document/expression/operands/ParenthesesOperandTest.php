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
 * @package   Mohiva/Elixir/Test
 * @author    Christian Kaps <christian.kaps@mohiva.com>
 * @copyright Copyright (c) 2007-2012 Christian Kaps (http://www.mohiva.com)
 * @license   https://github.com/mohiva/elixir/blob/master/LICENSE.textile New BSD License
 * @link      https://github.com/mohiva/elixir
 */
namespace com\mohiva\test\elixir\document\expression\operands;

use com\mohiva\pyramid\Token;
use com\mohiva\elixir\document\expression\Lexer;
use com\mohiva\elixir\document\expression\Grammar;
use com\mohiva\elixir\document\expression\operands\ParenthesesOperand;
use com\mohiva\common\parser\TokenStream;

/**
 * Unit test case for the Mohiva Elixir project.
 *
 * @category  Mohiva/Elixir
 * @package   Mohiva/Elixir/Test
 * @author    Christian Kaps <christian.kaps@mohiva.com>
 * @copyright Copyright (c) 2007-2012 Christian Kaps (http://www.mohiva.com)
 * @license   https://github.com/mohiva/elixir/blob/master/LICENSE.textile New BSD License
 * @link      https://github.com/mohiva/elixir
 */
class ParenthesesOperandTest extends \PHPUnit_Framework_TestCase {

	/**
	 * Test if the `parse` method returns a `Node` object.
	 */
	public function testParseReturnsNode() {

		$tokenStream = new TokenStream();
		$tokenStream->push(new Token(Lexer::T_OPEN_PARENTHESIS, '(', 1));
		$tokenStream->push(new Token(Lexer::T_VALUE, 100, 1));
		$tokenStream->push(new Token(Lexer::T_CLOSE_PARENTHESIS, ')', 1));
		$tokenStream->rewind();

		$operand = new ParenthesesOperand();
		$node = $operand->parse(new Grammar(), $tokenStream);

		$this->assertInstanceOf('\com\mohiva\pyramid\Node', $node);
	}

	/**
	 * Test if the node has outer parenthesis.
	 */
	public function testNodeHasOuterParenthesis() {

		$tokenStream = new TokenStream();
		$tokenStream->push(new Token(Lexer::T_OPEN_PARENTHESIS, '(', 1));
		$tokenStream->push(new Token(Lexer::T_VALUE, 100, 1));
		$tokenStream->push(new Token(Lexer::T_CLOSE_PARENTHESIS, ')', 1));
		$tokenStream->rewind();

		$operand = new ParenthesesOperand();
		$node = $operand->parse(new Grammar(), $tokenStream);

		$this->assertSame('(100)', $node->evaluate());
	}

	/**
	 * Test if the `parse` method throws an exception if the closing parentheses is missing
	 * and the end of the stream isn't reached.
	 *
	 * @expectedException \com\mohiva\common\exceptions\SyntaxErrorException
	 */
	public function testParseThrowsExceptionIfEndOfStreamIsNotReached() {

		$tokenStream = new TokenStream();
		$tokenStream->push(new Token(Lexer::T_OPEN_PARENTHESIS, '(', 1));
		$tokenStream->push(new Token(Lexer::T_VALUE, 100, 1));
		$tokenStream->push(new Token(Lexer::T_VALUE, 100, 1));
		$tokenStream->rewind();

		$operand = new ParenthesesOperand();
		$operand->parse(new Grammar(), $tokenStream);
	}

	/**
	 * Test if the `parse` method throws an exception if the closing parentheses is missing
	 * and the end of the stream is reached.
	 *
	 * @expectedException \com\mohiva\common\exceptions\SyntaxErrorException
	 */
	public function testParseThrowsExceptionIfEndOfStreamIsReached() {

		$tokenStream = new TokenStream();
		$tokenStream->push(new Token(Lexer::T_OPEN_PARENTHESIS, '(', 1));
		$tokenStream->push(new Token(Lexer::T_VALUE, 100, 1));
		$tokenStream->rewind();

		$operand = new ParenthesesOperand();
		$operand->parse(new Grammar(), $tokenStream);
	}
}
