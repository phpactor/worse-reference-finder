<?php

namespace Phpactor\WorseReferenceFinder;

use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\WorseReflection\Core\ClassName;
use Phpactor\WorseReflection\Core\Exception\NotFound;
use Phpactor\WorseReflection\Core\Inference\Symbol;
use Phpactor\WorseReflection\Core\Inference\SymbolContext;
use Phpactor\WorseReflection\Reflector;

class WorseDefinitionLocator implements DefinitionLocator
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
    public function locateDefinition(TextDocument $document, ByteOffset $byteOffset): DefinitionLocation
    {
        if (false === $document->language()->isPhp()) {
            throw new CouldNotLocateDefinition('I only work with PHP files');
        }

        $offset = $this->reflector->reflectOffset($document->__toString(), $byteOffset->toInt());

        return $this->gotoDefinition($offset->symbolContext());
    }

    private function gotoDefinition(SymbolContext $symbolContext): DefinitionLocation
    {
        switch ($symbolContext->symbol()->symbolType()) {
            case Symbol::METHOD:
            case Symbol::PROPERTY:
            case Symbol::CONSTANT:
                return $this->gotoMember($symbolContext);
            case Symbol::CLASS_:
                return $this->gotoClass($symbolContext);
            case Symbol::FUNCTION:
                return $this->gotoFunction($symbolContext);
        }

        throw new CouldNotLocateDefinition(sprintf(
            'Do not know how to goto definition of symbol type "%s"',
            $symbolContext->symbol()->symbolType()
        ));
    }

    private function gotoClass(SymbolContext $symbolContext): DefinitionLocation
    {
        $className = $symbolContext->type();

        try {
            $class = $this->reflector->reflectClassLike(
                ClassName::fromString((string) $className)
            );
        } catch (NotFound $e) {
            throw new CouldNotLocateDefinition($e->getMessage(), null, $e);
        }

        $path = $class->sourceCode()->path();

        if (null === $path) {
            throw new CouldNotLocateDefinition(sprintf(
                'The source code for class "%s" has no path associated with it.',
                $class->name()
            ));
        }

        return new DefinitionLocation(
            TextDocumentUri::fromString($path),
            ByteOffset::fromInt($class->position()->start())
        );
    }

    private function gotoFunction(SymbolContext $symbolContext): DefinitionLocation
    {
        $functionName = $symbolContext->name();

        try {
            $function = $this->reflector->reflectFunction($functionName);
        } catch (NotFound $e) {
            throw new GotoDefinitionException($e->getMessage(), null, $e);
        }

        $path = $function->sourceCode()->path();

        if (null === $path) {
            throw new GotoDefinitionException(sprintf(
                'The source code for function "%s" has no path associated with it.',
                $function->name()
            ));
        }

        return new DefinitionLocation(
            TextDocumentUri::fromString($path),
            ByteOffset::fromInt($function->position()->start())
        );
    }

    private function gotoMember(SymbolContext $symbolContext): DefinitionLocation
    {
        $symbolName = $symbolContext->symbol()->name();
        $symbolType = $symbolContext->symbol()->symbolType();

        if (null === $symbolContext->containerType()) {
            throw new CouldNotLocateDefinition(sprintf('Containing class for member "%s" could not be determined', $symbolName));
        }

        try {
            $containingClass = $this->reflector->reflectClassLike(ClassName::fromString((string) $symbolContext->containerType()));
        } catch (NotFound $e) {
            throw new CouldNotLocateDefinition($e->getMessage());
        }

        if ($symbolType === Symbol::PROPERTY && $containingClass->isInterface()) {
            throw new CouldNotLocateDefinition(sprintf('Symbol is a property and class "%s" is an interface', (string) $containingClass->name()));
        }

        $path = $containingClass->sourceCode()->path();

        if (null === $path) {
            throw new CouldNotLocateDefinition(sprintf(
                'The source code for class "%s" has no path associated with it.',
                (string) $containingClass->name()
            ));
        }

        switch ($symbolType) {
            case Symbol::METHOD:
                $members = $containingClass->methods();
                break;
            case Symbol::CONSTANT:
                $members = $containingClass->constants();
                break;
            case Symbol::PROPERTY:
                $members = $containingClass->properties();
                break;
        }

        if (false === $members->has($symbolName)) {
            throw new CouldNotLocateDefinition(sprintf(
                'Class "%s" has no %s named "%s", has: "%s"',
                $containingClass->name(),
                $symbolType,
                $symbolName,
                implode('", "', $members->keys())
            ));
        }

        $member = $members->get($symbolName);

        return new DefinitionLocation(
            TextDocumentUri::fromString($path),
            ByteOffset::fromInt($member->position()->start())
        );
    }
}
