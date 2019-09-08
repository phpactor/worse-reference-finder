<?php

namespace Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter;

use Closure;
use Phpactor\Name\FullyQualifiedName;
use SplFileInfo;

class PathFqnSimilarityFilter implements SimilarityFilter
{
    /**
     * @var int
     */
    private $lastSegmentCount;

    /**
     * lastSegmentCount: consider the last n segments of the path
     */
    public function __construct(int $lastSegmentCount = 2)
    {
        $this->lastSegmentCount = $lastSegmentCount;
    }

    public function __invoke(FullyQualifiedName $fqn): Closure
    {
        return function (SplFileInfo $info) use ($fqn) {
            $path = $info->getPathname();

            $pathSegments = array_map('trim', explode('/', $path));
            $pathSegments = array_slice($pathSegments, -$this->lastSegmentCount);
            $segments = $this->explodeUpperCaseSegments($pathSegments);
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
