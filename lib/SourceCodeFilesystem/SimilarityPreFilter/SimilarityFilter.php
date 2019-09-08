<?php

namespace Phpactor\WorseReferenceFinder\SourceCodeFilesystem\SimilarityPreFilter;

use Closure;
use Phpactor\Name\FullyQualifiedName;

interface SimilarityFilter
{
    public function __invoke(FullyQualifiedName $fqn): Closure;
}
