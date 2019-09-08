<?php

namespace Phpactor\WorseReferenceFinder\Tests\Unit\SourceCodeFilesystem\Filter;

use Closure;
use PHPUnit\Framework\TestCase;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter\ChainFilter;
use Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter\SimilarityFilter;
use SplFileInfo;

class ChainFilterTest extends TestCase
{
    public function testReturnsTrueByDefault()
    {
        $fqn = FullyQualifiedName::fromString(__CLASS__);
        $this->assertTrue((
            new ChainFilter()
        )->__invoke($fqn)(new SplFileInfo(__FILE__)));
    }

    public function testReturnsFalseIfOneOfTheFiltersReturnsFalse()
    {
        $filter1 = new class implements SimilarityFilter {
            public function __invoke(FullyQualifiedName $name): Closure
            {
                return function () {
                    return true;
                };
            }
        };
        $filter2 = new class implements SimilarityFilter {
            public function __invoke(FullyQualifiedName $name): Closure
            {
                return function () {
                    return false;
                };
            }
        };
        $fqn = FullyQualifiedName::fromString(__CLASS__);
        $this->assertFalse((
            new ChainFilter(...[$filter1, $filter2])
        )->__invoke($fqn)(new SplFileInfo(__FILE__)));
    }

    public function testReturnsTrueIfAllAreTrue()
    {
        $filter1 = new class implements SimilarityFilter {
            public function __invoke(FullyQualifiedName $name): Closure
            {
                return function () {
                    return true;
                };
            }
        };
        $filter2 = new class implements SimilarityFilter {
            public function __invoke(FullyQualifiedName $name): Closure
            {
                return function () {
                    return true;
                };
            }
        };
        $fqn = FullyQualifiedName::fromString(__CLASS__);
        $this->assertTrue((
            new ChainFilter(...[$filter1, $filter2])
        )->__invoke($fqn)(new SplFileInfo(__FILE__)));
    }
}
