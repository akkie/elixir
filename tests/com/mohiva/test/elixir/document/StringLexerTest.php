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
namespace com\mohiva\test\elixir\document;

use com\mohiva\test\elixir\Bootstrap;
use com\mohiva\elixir\document\StringLexer;
use com\mohiva\elixir\document\PreProcessor;

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
class StringLexerTest extends \PHPUnit_Framework_TestCase {

	/**
	 * Test a file with a defined XML version.
	 */
	public function testXMLVersion() {

		$start = microtime(true);
		$xmlFile = Bootstrap::$resourceDir . '/elixir/document/lexer/default.xml';
		$doc = file_get_contents($xmlFile);

		$lexer = new StringLexer();
		$stream = $lexer->scan($doc);
		$preProcessor = new PreProcessor();
		$preProcessor->process($stream);
		echo microtime(true) - $start;
	}
}
