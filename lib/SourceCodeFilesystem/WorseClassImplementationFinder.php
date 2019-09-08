<?php

namespace Phpactor\WorseReferenceFinder\SourceCodeFilesystem;

use Exception;
use Phpactor\Filesystem\Domain\Filesystem;
use Phpactor\Name\FullyQualifiedName;
use Phpactor\ReferenceFinder\ClassImplementationFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\Locations;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentBuilder;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Exception\SourceNotFound;
use Phpactor\WorseReflection\Core\Reflector\SourceCodeReflector;
use Phpactor\WorseReflection\Reflector;
use RuntimeException;
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
        foreach ($this->filesystem->fileList()->phpFiles()->filter(function (SplFileInfo $info) {
            $path = $info->getPathname();

            if (!file_exists($path)) {
                return false;
            }

            $contents = file_get_contents($path);

            if (false === $contents) {
                throw new RuntimeException(sprintf(
                    'Could not get file contents for "%s"', $path
                ));
            }

            if (preg_match('{abstract class}', $contents)) {
                return false;
            }

            return preg_match('{class .* (extends|implements)}', $contents);
        }) as $path) {
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
            try {
                if ($classReflection->name() == $worseClassName) {
                    continue;
                }

                if ($classReflection->isTrait()) {
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
