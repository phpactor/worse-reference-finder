<?php

namespace Phpactor\WorseReferenceFinder\Tests\Unit;

use Generator;
use Microsoft\PhpParser\Parser;
use PHPUnit\Framework\TestCase;
use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\WorseReferenceFinder\TolerantVariableReferenceFinder;
use function iterator_to_array;

class TolerantVariableReferenceFinderTest extends TestCase
{
    /**
    * @dataProvider provideReferences
    */
    public function testReferences(string $source, string $uri): void
    {
        list($source, $selectionOffset, $expectedReferences) = $this->offsetsFromSource($source, $uri);
        $document = TextDocumentBuilder::create($source)
            ->uri($uri)
            ->language("php")
            ->build();
        
        $finder = new TolerantVariableReferenceFinder(new Parser());
        $actualReferences = iterator_to_array($finder->findReferences($document, ByteOffset::fromInt($selectionOffset)), false);
        // dump($expectedReferences, $actualReferences);
        $this->assertEquals($expectedReferences, $actualReferences);
    }

    public function provideReferences(): Generator
    {
        // Remember the definition is not returned as a reference!

        $uri = "file:///root/testDoc";
        yield 'not on variable' => [
            '<?php $var1 = <>5;',
            $uri,
        ];

        yield 'basic' => [
            '<?php $v<>ar1 = 5; $var2 = <sr>$var1 + 10;',
            $uri,
        ];

        yield 'dynamic name' => [
            '<?php $v<>ar1 = 5; echo $<sr>$var1;',
            $uri,
        ];

        yield 'function argument' => [
            '<?php $v<>ar1 = 5; func(<sr>$var1);',
            $uri,
        ];

        yield 'global statement' => [
            '<?php $v<>ar1 = 5; global <sr>$var1;',
            $uri,
        ];

        yield 'dynamic property name' => [
            '<?php $v<>ar1 = 5; $obj-><sr>$var1 = 5;',
            $uri,
        ];

        yield 'dynamic property name (braced)' => [
            '<?php $v<>ar1 = 5; $obj->{<sr>$var1} = 5;',
            $uri,
        ];

        yield 'dynamic method name' => [
            '<?php $v<>ar1 = 5; $obj-><sr>$var1(5);',
            $uri,
        ];

        yield 'dynamic method name (braced)' => [
            '<?php $v<>ar1 = 5; $obj->{<sr>$var1}(5);',
            $uri,
        ];

        yield 'dynamic class name' => [
            '<?php $v<>ar1 = 5; $obj = new <sr>$var1();',
            $uri,
        ];

        yield 'embedded string' => [
            '<?php $v<>ar1 = 5; $str = "Text {<sr>$var1} more text";',
            $uri,
        ];

        yield 'scope: anonymous function: argument' => [
            '<?php $v<>ar1 = 5; $func = function($var1) { };',
            $uri,
        ];

        yield 'scope: anonymous function: use statement' => [
            '<?php $v<>ar1 = 5; $func = function() use (<sr>$var1) { };',
            $uri,
        ];

        yield 'scope: anonymous function: inside' => [
            '<?php $v<>ar1 = 5; $func = function() use (<sr>$var1) { $var2 = <sr>$var1; };',
            $uri,
        ];

        yield 'scope: anonymous function: inside selection' => [
            '<?php $var1 = 5; $func = function() use (<sr>$var1) { $var2 = <sr>$v<>ar1; };',
            $uri,
        ];

        yield 'scope: anonymous function: only inside' => [
            '<?php $var1 = 2; $func = function() { $v<>ar1 = 5; $var2 = <sr>$var1 + 10; };',
            $uri,
        ];

        yield 'scope: anonymous function: only outside' => [
            '<?php $va<>r1 = 2; $func = function() { $var1 = 5; $var2 = $var1 + 10; }; $var2 = <sr>$var1 / 4;',
            $uri,
        ];

        yield 'scope: inside class method' => [
            '<?php class C1 { function M1($var1) { <sr>$v<>ar1 = 5; $var2 = <sr>$var1 + 10; } }',
            $uri,
        ];

        yield 'scope: inside class method: select argument' => [
            '<?php class C1 { function M1($va<>r1) { <sr>$var1 = 5; $var2 = <sr>$var1 + 10; } }',
            $uri,
        ];

        yield 'scope: inside class method: inside anonumous function + use, click inside' => [
            '<?php class C1 { function M1() { $var1 = 10; $f = function() use (<sr>$var1) { <sr>$v<>ar1 = 5; $var2 = <sr>$var1 + 10; } } }',
            $uri,
        ];

        yield 'scope: inside class method: inside anonumous function + use, click outside' => [
            '<?php class C1 { function M1() { $v<>ar1 = 10; $f = function() use (<sr>$var1) { <sr>$var1 = 5; $var2 = <sr>$var1 + 10; } } }',
            $uri,
        ];

        yield 'scope: inside class method: inside anonumous function + use, click in use' => [
            '<?php class C1 { function M1() { $var1 = 10; $f = function() use (<sr>$v<>ar1) { <sr>$var1 = 5; $var2 = <sr>$var1 + 10; } } }',
            $uri,
        ];

        yield 'scope: inside class method: inside anonumous function (no use), click inside' => [
            '<?php class C1 { function M1() { $var1 = 10; $f = function($var1) { <sr>$v<>ar1 = 5; $var2 = <sr>$var1 + 10; } } }',
            $uri,
        ];

        yield 'scope: inside class method: inside anonumous function (no use), click outside' => [
            '<?php class C1 { function M1() { $v<>ar1 = 10; $f = function($var1) { $var1 = 5; $var2 = $var1 + 10; } } }',
            $uri,
        ];

        yield 'scope: inside class method: inside anonumous class method' => [
            '<?php '.
                'class C1 { function M1() { '.
                '$var = 1;'.
                '$c = new class { function IM() { $v<>ar = 1; } } '.
                ' } }',
            $uri,
        ];

        yield 'skip: static property access' => [
            '<?php '.
                'class C1 { static $prop1; function M1() { self::$pro<>p1 = 5; $var4 = self::$prop1; } }',
            $uri,
        ];

        yield 'skip: static property declaration' => [
            '<?php '.
                'class C1 { static $pr<>op1; function M1() { self::$prop1 = 5; $var4 = self::$prop1; } }',
            $uri,
        ];

        yield 'skip: instance property declaration' => [
            '<?php '.
                'class C1 { public $pr<>op1; function M1() { $this->prop1 = 5; $var4 = $this->prop1; } }',
            $uri,
        ];
    }

    private static function offsetsFromSource(string $source, ?string $uri): array
    {
        $textDocumentUri = $uri !== null ? TextDocumentUri::fromString($uri) : null;
        $results = preg_split("/(<>|<sr>|<mr>)/u", $source, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        
        $referenceLocations = [];
        $selectionOffset = null;

        if (is_array($results)) {
            $newSource = "";
            $offset = 0;
            foreach ($results as $result) {
                if ($result == "<>") {
                    $selectionOffset = $offset;
                    continue;
                }
                
                if ($result == "<sr>") {
                    $referenceLocations[] = PotentialLocation::surely(
                        new Location($textDocumentUri, ByteOffset::fromInt($offset))
                    );
                    continue;
                }
                if ($result == "<mr>") {
                    $referenceLocations[] = PotentialLocation::maybe(
                        new Location($textDocumentUri, ByteOffset::fromInt($offset))
                    );
                    continue;
                }
                
                $newSource .= $result;
                $offset += mb_strlen($result);
            }
        } else {
            throw new \Exception('No selection.');
        }
        
        return [$newSource, $selectionOffset, $referenceLocations];
    }
}
