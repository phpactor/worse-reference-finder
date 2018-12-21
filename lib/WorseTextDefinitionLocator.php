<?php

namespace Phpactor\WorseReferenceFinder;

use Phpactor\ReferenceFinder\DefinitionLocation;
use Phpactor\ReferenceFinder\DefinitionLocator;
use Phpactor\ReferenceFinder\Exception\CouldNotLocateDefinition;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\TextDocument;
use Phpactor\TextDocument\TextDocumentUri;
use Phpactor\WorseReflection\Core\Exception\NotFound;
use Phpactor\WorseReflection\Reflector;

class WorseTextDefinitionLocator implements DefinitionLocator
{
    /**
     * @var Reflector
     */
    private $reflector;

    /**
     * @var array
     */
    private $breakingChars;

    public function __construct(Reflector $reflector, array $breakingChars = [])
    {
        $this->reflector = $reflector;
        $this->breakingChars = $breakingChars ?: [
            ' ',
            '"', '\'', '|', '%', '(', ')', '[', ']',':'
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function locateDefinition(TextDocument $document, ByteOffset $byteOffset): DefinitionLocation
    {
        $word = $this->extractWord($document, $byteOffset);

        try {
            $reflectionClass = $this->reflector->reflectClassLike($word);
        } catch (NotFound $notFound) {
            throw new CouldNotLocateDefinition(sprintf(
                'Word "%s" could not be resolved to a class',
                $word
            ), 0, $notFound);
        }

        $path = $reflectionClass->sourceCode()->path();

        return new DefinitionLocation(
            TextDocumentUri::fromString($path),
            ByteOffset::fromInt($reflectionClass->position()->start())
        );
    }

    private function extractWord(TextDocument $document, ByteOffset $byteOffset)
    {
        $text = $document->__toString();
        $offset = $byteOffset->toInt();

        $chars = [];
        $char = $text[$offset];

        // read back
        while ($this->charIsNotBreaking($char)) {
            $chars[] = $char;

            $char = $offset > 0 ? $text[--$offset] : null;
        };

        $chars = array_reverse($chars);

        $offset = $byteOffset->toInt() + 1;
        $char = $text[$offset];

        // read forward
        while ($this->charIsNotBreaking($char)) {
            $chars[] = $char;
            $char = $text[++$offset] ?? null;
        };

        return implode('', $chars);
    }

    private function charIsNotBreaking(?string $char)
    {
        if (null === $char) {
            return false;
        }

        return !in_array($char, $this->breakingChars);
    }
}
