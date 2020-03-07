<?php

namespace Phpactor\WorseReferenceFinder;

use Phpactor\ReferenceFinder\Exception\UnsupportedDocument;
use Phpactor\ReferenceFinder\TypeLocation;
use Phpactor\ReferenceFinder\TypeLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateType;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\WorseReflection\Core\Exception\NotFound;
use Phpactor\WorseReflection\Core\Inference\Symbol;
use Phpactor\WorseReflection\Core\Inference\SymbolContext;
use Phpactor\WorseReflection\Core\Reflection\ReflectionClass;
use Phpactor\WorseReflection\Core\Reflection\ReflectionInterface;
use Phpactor\WorseReflection\Core\Reflection\ReflectionTrait;
use Phpactor\WorseReflection\Core\SourceCode;
use Phpactor\WorseReflection\Reflector;

class WorseReflectionTypeLocator implements TypeLocator
{
    /**
     * @var Reflector
     */
    private $reflector;

    public function __construct(Reflector $reflector)
    {
        $this->reflector = $reflector;
    }

    /**
     * {@inheritDoc}
     */
    public function locateType(TextDocument $document, ByteOffset $byteOffset): Location
    {
        if (false === $document->language()->isPhp()) {
            throw new UnsupportedDocument('I only work with PHP files');
        }

        if ($uri = $document->uri()) {
            $sourceCode = SourceCode::fromPathAndString($uri->__toString(), $document->__toString());
        } else {
            $sourceCode = SourceCode::fromString($document->__toString());
        }

        $offset = $this->reflector->reflectOffset(
            $sourceCode,
            $byteOffset->toInt()
        );

        return $this->gotoType($offset->symbolContext());
    }

    private function gotoType(SymbolContext $symbolContext): Location
    {
        $type = $symbolContext->type();

        if ($type->isPrimitive()) {
            throw new CouldNotLocateType(sprintf(
                'Cannot goto to primitive type "%s"', $type->__toString()
            ));
        }

        $className = $type->className();

        if (null === $className) {
            throw new CouldNotLocateType(sprintf(
                'Cannot goto to type "%s"', $type->__toString()
            ));
        }

        try {
            $class = $this->reflector->reflectClass($className->full());
        } catch (NotFound $e) {
            throw new CouldNotLocateType($e->getMessage(), 0, $e);
        }

        $path = $class->sourceCode()->path();

        return new Location(
            TextDocumentUri::fromString($path),
            ByteOffset::fromInt($class->position()->start())
        );
    }
}
