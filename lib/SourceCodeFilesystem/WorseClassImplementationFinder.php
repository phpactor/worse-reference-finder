<?php

namespace Phpactor\WorseReferenceFinder\SourceCodeFilesystem;

use Phpactor\Filesystem\Domain\Filesystem;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\ReferenceFinder\ClassImplementationFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\Locations;
use Phpactor\TextDocument\TextDocument;
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

    public function findImplementations(TextDocument $document, ByteOffset $byteOffset): Locations
    {
        $reflectionOffset = $this->reflector->reflectOffset($document, $byteOffset);
        return $this->doFindImplementations(
            $reflectionOffset->symbolContext()->type()->__toString()
        );
    }

    public function doFindImplementations(string $fqn): Locations
    {
        $locations = [];
        foreach ($this->filesystem->fileList()->phpFiles() as $path) {
            $locations = array_merge($locations, $this->scanLocations($path->asSplFileInfo(), $fqn));
        }

        return new Locations($locations);
    }

    private function scanLocations(SplFileInfo $fileInfo, string $fqn): array
    {
        $worseClassName = ClassName::fromString($fqn);
        $textDocument = TextDocumentBuilder::fromUri($fileInfo->getPathname())
            ->language('php')
            ->build();

        $locations = [];

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
