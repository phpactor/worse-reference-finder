<?php

namespace Phpactor\WorseReferenceFinder\Tests\Unit\SourceCodeFilesystem\Filter;

use Phpactor\Name\FullyQualifiedName;
use Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter\AbstractnessFilter;
use Phpactor\WorseReferenceFinder\Tests\IntegrationTestCase;
use SplFileInfo;

class AbstractnessFilterTest extends IntegrationTestCase
{
    /**
     * @dataProvider provideFilter
     */
    public function testFilter(string $source, bool $shouldMatch)
    {
        $this->workspace->reset();
        $this->workspace->put('test.php', $source);
        $this->assertEquals($shouldMatch, (
            new AbstractnessFilter()
        )->__invoke(
            FullyQualifiedName::fromString('Foo')
        )(
            new SplFileInfo($this->workspace->path('/test.php'))
        ));
    }

    public function provideFilter()
    {
        yield 'no class' => [
            'foobar',
            false
        ];

        yield 'class that does not extend anything' => [
            'class Foobar',
            false
        ];

        yield 'class that extends' => [
            'class Foobar extends Bar',
            true
        ];

        yield 'class that implements' => [
            'class Foobar implements Bar',
            true
        ];
        yield 'class that includes the word "abstract"' => [
            'class Foobar implements Bar { function hello() { echo "abstract class";}} ',
            true
        ];

        yield 'abstract class' => [
            'abstract class Foobar implements Bar',
            false
        ];
    }
}
