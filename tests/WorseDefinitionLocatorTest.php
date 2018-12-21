<?php

namespace Phpactor\WorseReferenceFinder\Tests;

use PHPUnit\Framework\TestCase;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\TestUtils\ExtractOffset;
use Phpactor\TestUtils\Workspace;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\WorseReferenceFinder\WorseDefinitionLocator;
use Phpactor\WorseReflection\Core\SourceCodeLocator\StubSourceLocator;
use Phpactor\WorseReflection\ReflectorBuilder;

class WorseDefinitionLocatorTest extends TestCase
{
    const EXAMPLE_SOURCE = 'foobar';
    const EXAMPLE_OFFSET = 1234;

    /**
     * @var WorseDefinitionLocator
     */
    private $locator;

    /**
     * @var Workspace
     */
    private $workspace;

    public function setUp()
    {
        $this->workspace = Workspace::create(__DIR__ . '/Workspace');
        $this->workspace->reset();
    }

    public function testExceptionOnNonPhpFile()
    {
        $this->expectException(CouldNotLocateDefinition::class);
        $this->expectExceptionMessage('PHP');

        $this->locator()->locateDefinition(
            TextDocumentBuilder::create('asd')->language('asd')->build(),
            ByteOffset::fromInt(1234)
        );
    }

    public function testExceptionOnUnresolvableSymbol()
    {
        $this->expectException(CouldNotLocateDefinition::class);
        $this->expectExceptionMessage('Do not know how');

        [$source, $offset] = ExtractOffset::fromSource('<?php <>');

        $this->locator()->locateDefinition(
            TextDocumentBuilder::create($source)->language('php')->build(),
            ByteOffset::fromInt($offset)
        );
    }

    public function testExceptionWhenNoContainingClass()
    {
        $this->expectException(CouldNotLocateDefinition::class);
        $this->expectExceptionMessage('Containing class');

        [$source, $offset] = ExtractOffset::fromSource('<?php $foo->fo<>');

        $this->locator()->locateDefinition(
            TextDocumentBuilder::create($source)->language('php')->build(),
            ByteOffset::fromInt($offset)
        );
    }

    public function testExceptionWhenContainingClassNotFound()
    {
        $this->markTestSkipped();
    }

    public function testExceptionWhrenClassNoPath()
    {
        $this->markTestSkipped();
    }

    public function testExceptionWhenFunctionHasNoSourceCode()
    {
        $this->markTestSkipped();
    }

    public function testLocatesFunction()
    {
        $location = $this->locate(<<<'EOT'
// File: file1.php
<?php

function foobar()
{
}
EOT
        , '<?php foob<>ar();');

        $this->assertEquals($this->workspace->path('file1.php'), (string) $location->uri());
        $this->assertEquals(7, $location->offset()->toInt());
    }

    public function testExceptionIfMethodNotFound()
    {
        $this->expectException(CouldNotLocateDefinition::class);
        $this->expectExceptionMessage('Class "Foobar" has no property');
        $location = $this->locate(<<<'EOT'
// File: Foobar.php
<?php 

class Foobar 
{
}
EOT
        , '<?php $foo = new Foobar(); $foo->b<>ar;');
    }

    public function testLocatesToMethod()
    {
        $location = $this->locate(<<<'EOT'
// File: Foobar.php
<?php class Foobar { public function bar() {} }
EOT
        , '<?php $foo = new Foobar(); $foo->b<>ar();');

        $this->assertEquals($this->workspace->path('Foobar.php'), (string) $location->uri());
        $this->assertEquals(21, $location->offset()->toInt());
    }

    public function testLocatesConstant()
    {
    }

    public function testLocatesProperty()
    {
    }

    public function testExceptionIfPropertyIsInterface()
    {
    }

    private function assertGotoDefinition($symbolType)
    {
    }

    private function locate(string $manifset, string $source): DefinitionLocation
    {
        [$source, $offset] = ExtractOffset::fromSource($source);

        return $this->locator($manifset)->locateDefinition(
            TextDocumentBuilder::create($source)->language('php')->build(),
            ByteOffset::fromInt($offset)
        );
    }

    private function locator(string $manifest): WorseDefinitionLocator
    {
        $this->workspace->loadManifest($manifest);

        $reflector = ReflectorBuilder::create()
            ->addLocator(new StubSourceLocator(
                ReflectorBuilder::create()->build(),
                $this->workspace->path(''),
                $this->workspace->path('cache')
            ))
            ->build();

        return new WorseDefinitionLocator($reflector);
    }
}
