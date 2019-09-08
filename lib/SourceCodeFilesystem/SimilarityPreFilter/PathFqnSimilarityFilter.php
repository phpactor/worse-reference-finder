<?php

namespace Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter;

use Closure;
use Phpactor\Name\FullyQualifiedName;
use SplFileInfo;

class PathFqnSimilarityFilter implements SimilarityFilter
{
    public function __invoke(FullyQualifiedName $fqn): Closure
    {
        return function (SplFileInfo $info) use ($fqn) {
            $path = $info->getPathname();

            $segments = $this->explodeUpperCaseSegments(array_map('trim', explode('/', $path)));
            $names = $this->explodeUpperCaseSegments($fqn->toArray());

            $diff = array_intersect($segments, $names);

            if ($diff) {
                return true;
            }

            return false;
        };
    }

    private function explodeUpperCaseSegments(array $names): array
    {
        $exploded = [];
        foreach ($names as $name) {
            $exploded = array_merge($exploded, (array)preg_split('{(?=[A-Z])}', $name));
        }
        return array_filter(array_map('strtolower', $exploded));
    }
}
