<?php

namespace Phpactor\WorseReferenceFinder\Tests\Unit;

use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\WorseReferenceFinder\Tests\WorseTestCase;
use Phpactor\WorseReferenceFinder\WorsePlainTextClassDefinitionLocator;

class WorseTextDefinitionLocatorTest extends WorseTestCase
{
    /**
     * @dataProvider provideGotoWord
     */
    public function testGotoWord(string $text, string $expectedPath)
    {
        $location = $this->locate(<<<'EOT'
// File: Foobar.php
<?php class Foobar {}
// File: Barfoo.php
<?php namespace Barfoo { class Barfoo {} }
EOT
        , $text);
        $this->assertEquals($this->workspace->path($expectedPath), (string) $location->uri());
    }

    public function testExceptionIfCannotFindClass()
    {
        $this->expectException(CouldNotLocateDefinition::class);
        $this->expectExceptionMessage('Word "is" could not be resolved to a class');
        $this->locate('', 'Hello this i<>s ');
    }

    public function provideGotoWord()
    {
        yield 'property docblock' => [ '/** @var Foob<>ar */', 'Foobar.php' ];
        yield 'fully qualified' => [ '/** @var \Barfoo\Barf<>oo */', 'Barfoo.php' ];
        yield 'qualified' => [ '/** @var Barfoo\Barf<>oo */', 'Barfoo.php' ];
        yield 'xml attribute' => [ '<element class="Foob<>ar">', 'Foobar.php' ];
        yield 'array access' => [ '[Foob<>ar::class]', 'Foobar.php' ];
        yield 'solid block of text' => [ 'Foob<>ar', 'Foobar.php' ];
    }

    protected function locator(): DefinitionLocator
    {
        return new WorsePlainTextClassDefinitionLocator($this->reflector());
    }
}
