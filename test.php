<?php
// 0.036 Linux
$start = microtime(true);

use com\mohiva\test\elixir\Bootstrap;
use com\mohiva\pyramid\Parser as ExpressionParser;
use com\mohiva\common\xml\XMLDocument;
use com\mohiva\common\crypto\Hash;
use com\mohiva\elixir\document\expression\Lexer as ExpressionLexer;
use com\mohiva\elixir\document\expression\Grammar as ExpressionGrammar;
use com\mohiva\elixir\document\Lexer as DocumentLexer;
use com\mohiva\elixir\document\Parser as DocumentParser;
use com\mohiva\elixir\document\Compiler;
use com\mohiva\elixir\document\Lexer;
use com\mohiva\elixir\io\StreamWrapper;
use com\mohiva\common\io\ClassAutoloader;
use com\mohiva\common\cache\HashKey;
use com\mohiva\elixir\io\CacheContainer;
use com\mohiva\common\cache\adapters\APCAdapter;

set_include_path(implode(PATH_SEPARATOR, array(
	realpath(__DIR__ . "/src"),
	realpath(__DIR__ . "/tests"),
	realpath(__DIR__ . "/vendor/mohiva/common/src"),
	realpath(__DIR__ . "/vendor/mohiva/pyramid/src"),
	realpath(__DIR__ . "/vendor/mohiva/manitou/src"),
	get_include_path()
)));

/** @noinspection PhpIncludeInspection */
//require_once 'vendor/autoload.php';

require_once 'com/mohiva/common/io/ClassAutoloader.php';

$autoloader = new ClassAutoloader();
$autoloader->register(true, true);

$key = new HashKey(Hash::ALGO_SHA1, 'php://temp/');
$adapter = new APCAdapter();
$container = new CacheContainer($adapter, $key);

StreamWrapper::setCacheContainer($container);
StreamWrapper::register();

function loadTemplate($file) {

	$hash = sha1(uniqid() . microtime(true));
	$className = 'Test_' . $hash;
	$fileName = 'elixir://test' . $hash;

	$doc = new XMLDocument();
	$doc->fixLineNumbers = true;
	$doc->preserveWhiteSpace = true;
	$doc->load($file);

	$lexer = new DocumentLexer();
	$stream = $lexer->scan($doc);

	$parser = new DocumentParser(new ExpressionLexer(), new ExpressionParser(new ExpressionGrammar));
	$tree = $parser->parse($stream);

	$compiler = new Compiler('test', $className, '\stdClass', 'Test class');
	$content = $compiler->compile($tree);
	file_put_contents($fileName, $content);

	/** @noinspection PhpIncludeInspection */
	require $fileName;
}

loadTemplate(__DIR__ . '/tests/com/mohiva/test/resources/elixir/document/compiler/default.xml');

echo microtime(true) - $start;
