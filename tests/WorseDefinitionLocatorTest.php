<?php

namespace Phpactor\WorseReferenceFinder\Tests;

use PHPUnit\Framework\TestCase;
use Phpactor\WorseReferenceFinder\WorseDefinitionLocator;
use Phpactor\WorseReflection\ReflectorBuilder;

class WorseDefinitionLocatorTest extends TestCase
{
    const EXAMPLE_SOURCE = 'foobar';
    const EXAMPLE_OFFSET = 1234;

    /**
     * @var WorseDefinitionLocator
     */
    private $locator;

    public function create(string $source)
    {
        $reflector = ReflectorBuilder::create()->addSource($source)->build();
        return new WorseDefinitionLocator($reflector);
    }


    public function testExceptionOnNonPhpFile()
    {
    }

    public function testExceptionOnUnresolvableSymbol()
    {
    }

    public function testExceptionWhenNoContainingClass()
    {
    }

    public function testExceptionWhenContainingClassNotFound()
    {
    }

    public function testExceptionWhrenClassNoPath()
    {
    }

    public function testExceptionWhenFunctionHasNoSourceCode()
    {
    }

    public function testLocatesFunction()
    {
    }

    public function testExceptionIfMethodNotFound()
    {
    }

    public function testLocatesToMethod()
    {
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

    private function locate(TextDocument $document)
    {
        $this->locator->locateDefinition($document, $this->offset);
    }
}
