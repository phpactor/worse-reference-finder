<?php

namespace Phpactor\WorseReferenceFinder\SourceCodeFilesystem;

use Phpactor\Filesystem\Domain\Filesystem;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\ReferenceFinder\ClassImplementationFinder;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\Locations;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Reflector\SourceCodeReflector;
use Phpactor\WorseReflection\Reflector;
use SplFileInfo;

class WorseClassImplementationFinder implements ClassImplementationFinder
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var SourceCodeReflector
     */
    private $reflector;

    public function __construct(SourceCodeReflector $reflector, Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        $this->reflector = $reflector;
    }

    public function findImplementations(FullyQualifiedName $name): Locations
    {
        $locations = [];
        foreach ($this->filesystem->fileList()->phpFiles() as $path) {
            $locations = array_merge($locations, $this->scanLocations($path->asSplFileInfo(), $name));
        }

        return new Locations($locations);
    }

    private function scanLocations(SplFileInfo $fileInfo, FullyQualifiedName $name): array
    {
        $worseClassName = ClassName::fromUnknown($name->__toString());
        $locations = [];
        $textDocument = TextDocumentBuilder::fromUri($fileInfo->getPathname())
            ->language('php')
            ->build();

        foreach ($this->reflector->reflectClassesIn($textDocument) as $classReflection) {
            if ($classReflection->name() == $worseClassName) {
                continue;
            }

            if (!$classReflection->isInstanceOf($worseClassName)) {
                continue;
            }

            $locations[] = Location::fromPathAndOffset(
                $classReflection->sourceCode()->path(),
                $classReflection->position()->start()
            );
        }

        return $locations;
    }
}
