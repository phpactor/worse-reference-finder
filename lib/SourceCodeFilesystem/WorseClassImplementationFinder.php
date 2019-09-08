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
use Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter\SimilarityFilter;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Exception\SourceNotFound;
use Phpactor\WorseReflection\Core\Reflector\SourceCodeReflector;
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

    /**
     * @var SimilarityFilter
     */
    private $filter;

    public function __construct(SourceCodeReflector $reflector, Filesystem $filesystem, SimilarityFilter $filter)
    {
        $this->filesystem = $filesystem;
        $this->reflector = $reflector;
        $this->filter = $filter;
    }

    public function findImplementations(TextDocument $document, ByteOffset $byteOffset): Locations
    {
        $reflectionOffset = $this->reflector->reflectOffset($document, $byteOffset);
        return $this->doFindImplementations(
            FullyQualifiedName::fromString($reflectionOffset->symbolContext()->type()->__toString())
        );
    }

    public function doFindImplementations(FullyQualifiedName $fqn): Locations
    {
        $locations = [];
        foreach ($this->filesystem->fileList()->phpFiles()->filter($this->filter->__invoke($fqn)) as $path) {
            $locations = array_merge($locations, $this->scanLocations($path->asSplFileInfo(), $fqn));
        }

        return new Locations($locations);
    }

    private function scanLocations(SplFileInfo $fileInfo, FullyQualifiedName $fqn): array
    {
        $worseClassName = ClassName::fromString($fqn->__toString());
        $textDocument = TextDocumentBuilder::fromUri($fileInfo->getPathname())
            ->language('php')
            ->build();

        $locations = [];

        foreach ($this->reflector->reflectClassesIn($textDocument) as $classReflection) {
            try {
                if ($classReflection->name() == $worseClassName) {
                    continue;
                }

                if (false === $classReflection->isClass()) {
                    continue;
                }

                if ($classReflection->isAbstract()) {
                    continue;
                }

                if (!$classReflection->isInstanceOf($worseClassName)) {
                    continue;
                }

                $locations[] = Location::fromPathAndOffset(
                    $classReflection->sourceCode()->path(),
                    $classReflection->position()->start()
                );
                break;
            } catch (SourceNotFound $e) {
                continue;
            }
        }

        return $locations;
    }
}
