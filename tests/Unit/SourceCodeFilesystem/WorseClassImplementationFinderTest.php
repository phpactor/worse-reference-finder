<?php

namespace Phpactor\WorseReferenceFinder\Tests\Unit\SourceCodeFilesystem;

use PHPUnit\Framework\TestCase;
use Phpactor\Filesystem\Adapter\Simple\SimpleFilesystem;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\TextDocument\Location;
use Phpactor\WorseReferenceFinder\SourceCodeFilesystem\WorseClassImplementationFinder;
use Phpactor\WorseReferenceFinder\Tests\IntegrationTestCase;
use Phpactor\WorseReferenceFinder\Tests\WorseTestCase;

class WorseClassImplementationFinderTest extends IntegrationTestCase
{
    /**
     * @dataProvider provideFindImplementations
     */
    public function testFindImplementations(string $manifest, string $classFqn, array $expectedLocations)
    {
        $this->workspace->loadManifest($manifest);

        $filesystem = new SimpleFilesystem($this->workspace->path('/'));
        $finder = new WorseClassImplementationFinder($this->reflector(), $filesystem);
        $locations = $finder->findImplementations(
            FullyQualifiedName::fromString($classFqn)
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
<?php interface FoobarInterface {}
EOT
           , 'FoobarInterface', [
           ]
       ];

        yield 'finds single interface implementation' => [
            <<<'EOT'
// File: FoobarInterface.php
<?php interface FoobarInterface {}
// File: Foobar.php
<?php class Foobar implements FoobarInterface {}
EOT
           , 'FoobarInterface', [
               ['/Foobar.php', 6]
           ]
        ];

        yield 'finds multiple interface implementations' => [
            <<<'EOT'
// File: FoobarInterface.php
<?php interface FoobarInterface {}
// File: Foobar.php
<?php class Foobar implements FoobarInterface {}
// File: Bazboo.php
<?php class Bazboo implements FoobarInterface {}
EOT
           , 'FoobarInterface', [
               ['/Foobar.php', 6],
               ['/Bazboo.php', 6],
           ]
        ];

        yield 'finds classes which extend the interface' => [
            <<<'EOT'
// File: FoobarInterface.php
<?php interface FoobarInterface {}
// File: Foobar.php
<?php class Foobar implements FoobarInterface {}
// File: Bazboo.php
<?php class Bazboo extends Foobar {}
// File: Boobaz.php
<?php class Boobaz extends Bazboo {}
EOT
           , 'FoobarInterface', [
               ['/Foobar.php', 6],
               ['/Boobaz.php', 6],
               ['/Bazboo.php', 6],
           ]
       ];

        yield 'finds instances of parent class' => [
            <<<'EOT'
// File: Foobar.php
<?php class Foobar {}
// File: Bazboo.php
<?php class Bazboo extends Foobar {}
EOT
           , 'Foobar', [
               ['/Bazboo.php', 6],
           ]
       ];

        yield 'finds instances of abstract class' => [
            <<<'EOT'
// File: Foobar.php
<?php abstract class Foobar {}
// File: Bazboo.php
<?php class Bazboo extends Foobar {}
EOT
           , 'Foobar', [
               ['/Bazboo.php', 6],
           ]
       ];

        yield 'does not find instances of trait (current limitation)' => [
            <<<'EOT'
// File: Foobar.php
<?php trait Foobar {}
// File: Bazboo.php
<?php class Bazboo {
   use Foobar;
}
EOT
           , 'Foobar', [
           ]
       ];
    }
}
