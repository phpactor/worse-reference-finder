<?php

namespace Phpactor\WorseReferenceFinder\Tests;

use PHPUnit\Framework\TestCase;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\TestUtils\ExtractOffset;
use Phpactor\TestUtils\Workspace;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\WorseReflection\Core\SourceCodeLocator\StubSourceLocator;
use Phpactor\WorseReflection\Reflector;
use Phpactor\WorseReflection\ReflectorBuilder;

abstract class WorseTestCase extends TestCase
{
    /**
     * @var Workspace
     */
    protected $workspace;

    public function setUp()
    {
        $this->workspace = Workspace::create(__DIR__ . '/Workspace');
        $this->workspace->reset();
    }

    protected function locate(string $manifset, string $source): DefinitionLocation
    {
        [$source, $offset] = ExtractOffset::fromSource($source);

        $this->workspace->loadManifest($manifset);
        return $this->locator()->locateDefinition(
            TextDocumentBuilder::create($source)->language('php')->build(),
            ByteOffset::fromInt($offset)
        );
    }

    abstract protected function locator(): DefinitionLocator;

    protected function reflector(): Reflector
    {
        return ReflectorBuilder::create()
            ->addLocator(new StubSourceLocator(
                ReflectorBuilder::create()->build(),
                $this->workspace->path(''),
                $this->workspace->path('cache')
            ))
            ->build();
    }
}
