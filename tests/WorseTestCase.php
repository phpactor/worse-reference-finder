<?php

namespace Phpactor\WorseReferenceFinder\Tests;

use PHPUnit\Framework\TestCase;
use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\TestUtils\ExtractOffset;
use Phpactor\TestUtils\Workspace;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocumentBuilder;

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

    protected abstract function locator(): DefinitionLocator;
}
