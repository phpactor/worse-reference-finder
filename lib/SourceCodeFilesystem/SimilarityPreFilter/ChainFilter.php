<?php

namespace Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter;

use Closure;
use Phpactor\Name\FullyQualifiedName;
use SplFileInfo;

class ChainFilter implements SimilarityFilter
{
    /**
     * @var SimilarityFilter[]
     */
    private $filters;

    public function __construct(SimilarityFilter ...$filters)
    {
        $this->filters = $filters;
    }

    public function __invoke(FullyQualifiedName $fqn): Closure
    {
        return function (SplFileInfo $info) use ($fqn) {
            foreach ($this->filters as $filter) {
                if ($filter->__invoke($fqn)($info) === false) {
                    return false;
                }
            }

            return true;
        };
    }
}
