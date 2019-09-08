<?php

namespace Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter;

use Closure;
use Phpactor\Name\FullyQualifiedName;
use RuntimeException;
use SplFileInfo;

class AbstractnessFilter implements SimilarityFilter
{
    public function __invoke(FullyQualifiedName $fqn): Closure
    {
        return function (SplFileInfo $info) {
            $path = $info->getPathname();

            if (!file_exists($path)) {
                return false;
            }

            $contents = file_get_contents($path);

            if (false === $contents) {
                throw new RuntimeException(sprintf(
                    'Could not get file contents for "%s"',
                    $path
                ));
            }

            if (preg_match('{^\s*abstract class}', $contents)) {
                return false;
            }

            return (bool) preg_match('{\s*class .* (extends|implements)}', $contents);
        };
    }
}
