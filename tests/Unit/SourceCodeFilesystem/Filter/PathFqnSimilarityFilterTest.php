<?php

namespace Phpactor\WorseReferenceFinder\Tests\Unit\SourceCodeFilesystem\Filter;

use PHPUnit\Framework\TestCase;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter\PathFqnSimilarityFilter;
use SplFileInfo;

class PathFqnSimilarityFilterTest extends TestCase
{
    /**
     * @dataProvider provideFilter
     */
    public function testFilter(string $path, string $fqn, bool $shouldMatch)
    {
        $fqn = FullyQualifiedName::fromString($fqn);
        $this->assertEquals($shouldMatch, (new PathFqnSimilarityFilter())->__invoke($fqn)(new SplFileInfo($path)));
    }

    public function provideFilter()
    {
        yield 'no similarity' => [
            '/src/Framework/Plugin/FoobarControllerPlugin',
            'SplFileInfo',
            false
        ];

        yield 'shared path segment' => [
            '/src/Framework/Plugin/FoobarControllerPlugin',
            'Framework\\Controller',
            true
        ];

        yield 'suffix matches interface name' => [
            '/src/FoobarExtension/FoobarHandler',
            'Phpactor\\Handler',
            true
        ];

        yield 'segment in namespace matches segment in path' => [
            '/src/FoobarExtension/Foobar',
            'Phpactor\\FooExtension\\Handler',
            true
        ];
    }
}
