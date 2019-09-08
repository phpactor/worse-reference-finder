<?php

namespace Phpactor\WorseReferenceFinder\Tests\Unit\SourceCodeFilesystem;

use Phpactor\Filesystem\Adapter\Simple\SimpleFilesystem;
use Phpactor\TestUtils\ExtractOffset;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter\ChainFilter;
use Phpactor\WorseReferenceFinder\SourceCodeFilesystem\WorseClassImplementationFinder;
use Phpactor\WorseReferenceFinder\Tests\IntegrationTestCase;

class WorseClassImplementationFinderTest extends IntegrationTestCase
{
    /**
     * @dataProvider provideFindImplementations
     */
    public function testFindImplementations(string $manifest, string $documentPath, array $expectedLocations)
    {
        $this->workspace->loadManifest($manifest);
        $document = $this->workspace->getContents($documentPath);
        [$document, $offset] = ExtractOffset::fromSource($document);
        $this->workspace->put($documentPath, $document);

        $filesystem = new SimpleFilesystem($this->workspace->path('/'));
        $finder = new WorseClassImplementationFinder($this->reflector(), $filesystem, new ChainFilter());
        $locations = $finder->findImplementations(
            TextDocumentBuilder::create($document)->language('php')->build(),
            ByteOffset::fromInt($offset)
        );

        $this->assertCount(count($expectedLocations), $locations);
        $expectedLocations = array_map(function (array $location) {
            return Location::fromPathAndOffset(
                $this->workspace->path($location[0]),
                $location[1]
            );
        }, $expectedLocations);

        $this->assertEquals($expectedLocations, iterator_to_array($locations));
    }

    public function provideFindImplementations()
    {
        yield 'ignores given class implementation' => [
            <<<'EOT'
// File: FoobarInterface.php
<?php interface Fo<>obarInterface {}
EOT
           , 'FoobarInterface.php', [
           ]
       ];

        yield 'finds single interface implementation' => [
            <<<'EOT'
// File: FoobarInterface.php
<?php interface FoobarInterface {}
// File: Foobar.php
<?php class Foobar implements Fo<>obarInterface {}
EOT
           , 'Foobar.php', [
               ['/Foobar.php', 6]
           ]
        ];

        yield 'finds multiple interface implementations' => [
            <<<'EOT'
// File: FoobarInterface.php
<?php interface Fo<>obarInterface {}
// File: Foobar.php
<?php class Foobar implements FoobarInterface {}
// File: Bazboo.php
<?php class Bazboo implements FoobarInterface {}
EOT
           , 'FoobarInterface.php', [
               ['/Foobar.php', 6],
               ['/Bazboo.php', 6],
           ]
        ];

        yield 'finds classes which extend the interface' => [
            <<<'EOT'
// File: FoobarInterface.php
<?php interface Fo<>obarInterface {}
// File: Foobar.php
<?php class Foobar implements FoobarInterface {}
// File: Bazboo.php
<?php class Bazboo extends Foobar {}
// File: Boobaz.php
<?php class Boobaz extends Bazboo {}
EOT
           , 'FoobarInterface.php', [
               ['/Foobar.php', 6],
               ['/Boobaz.php', 6],
               ['/Bazboo.php', 6],
           ]
       ];

        yield 'finds instances of parent class' => [
            <<<'EOT'
// File: Foobar.php
<?php class F<>oobar {}
// File: Bazboo.php
<?php class Bazboo extends Foobar {}
EOT
           , 'Foobar.php', [
               ['/Bazboo.php', 6],
           ]
       ];

        yield 'finds instances of abstract class' => [
            <<<'EOT'
// File: Foobar.php
<?php abstract class Foob<>ar {}
// File: Bazboo.php
<?php class Bazboo extends Foobar {}
EOT
           , 'Foobar.php', [
               ['/Bazboo.php', 6],
           ]
       ];

        yield 'does not find instances of trait (current limitation)' => [
            <<<'EOT'
// File: Foobar.php
<?php trait Foo<>bar {}
// File: Bazboo.php
<?php class Bazboo {
   use Foobar;
}
EOT
           , 'Foobar.php', [
           ]
       ];

        yield 'does not find abstract implementations' => [
            <<<'EOT'
// File: Foobar.php
<?php interface Foo<>bar {}
// File: Bazboo.php
<?php class Bazboo implements Foobar {}
// File: Bazbar.php
<?php abstract class Bazboo implements Foobar {}
EOT
           , 'Foobar.php', [
               ['/Bazboo.php', 6]
           ]
       ];
    }
}
