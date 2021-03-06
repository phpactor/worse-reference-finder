<?php

namespace Phpactor\WorseReferenceFinder;

use Generator;
use Microsoft\PhpParser\ClassLike;
use Microsoft\PhpParser\FunctionLike;
use Microsoft\PhpParser\MissingToken;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Node\Expression\AnonymousFunctionCreationExpression;
use Microsoft\PhpParser\Node\Expression\ScopedPropertyAccessExpression;
use Microsoft\PhpParser\Node\Expression\Variable;
use Microsoft\PhpParser\Node\MethodDeclaration;
use Microsoft\PhpParser\Node\Parameter;
use Microsoft\PhpParser\Node\PropertyDeclaration;
use Microsoft\PhpParser\Node\SourceFileNode;
use Microsoft\PhpParser\Node\UseVariableName;
use Microsoft\PhpParser\Parser;
use Phpactor\ReferenceFinder\PotentialLocation;
use Phpactor\ReferenceFinder\ReferenceFinder;
use Phpactor\TextDocument\ByteOffset;
use Phpactor\TextDocument\Location;
use Phpactor\TextDocument\TextDocument;
use function assert;
use Exception;

class TolerantVariableReferenceFinder implements ReferenceFinder
{
    /**
     * @var Parser
     */
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }
    /**
     * {@inheritDoc}
     */
    public function findReferences(TextDocument $document, ByteOffset $byteOffset): Generator
    {
        $sourceNode = $this->sourceNode($document->__toString());
        $variable = $this->variableNodeFromSource($sourceNode, $byteOffset->toInt());
        if ($variable === null) {
            return;
        }

        $scopeNode = $this->scopeNode($variable);
        $referencesGenerator = $this->find($scopeNode, $this->variableName($variable), $document->uri());
        $referencesGenerator->next(); // discard the first result as it is the definition
        if ($referencesGenerator->valid()) {
            yield from $referencesGenerator;
        }
    }

    private function sourceNode(string $source): SourceFileNode
    {
        return $this->parser->parseSourceFile($source);
    }

    private function variableNodeFromSource(SourceFileNode $sourceNode, int $offset): ?Node
    {
        $node = $sourceNode->getDescendantNodeAtPosition($offset);

        if (
            false === $node instanceof Variable &&
            false === $node instanceof UseVariableName &&
            false === $node instanceof Parameter
        ) {
            return null;
        }

        if (
            ($node instanceof Variable && $node->parent instanceof ScopedPropertyAccessExpression)
            || ($node instanceof Variable && $node->getFirstAncestor(PropertyDeclaration::class))
        ) {
            return null;
        }

        return $node;
    }

    private function scopeNode(Node $variable): Node
    {
        $name = $this->variableName($variable);
        if ($variable instanceof UseVariableName) {
            $variable = $variable->getFirstAncestor(MethodDeclaration::class) ?: $variable;
        }

        $scopeNode = $variable->getFirstAncestor(FunctionLike::class, ClassLike::class, SourceFileNode::class);
        while (
            $scopeNode instanceof AnonymousFunctionCreationExpression &&
            $this->nameExistsInUseClause($name, $scopeNode)
        ) {
            $scopeNode = $scopeNode->getFirstAncestor(FunctionLike::class, ClassLike::class, SourceFileNode::class);
        }

        if (null === $scopeNode) {
            throw new Exception(
                'Could not determine scope node, this should not happen as ' .
                'there should always be a SourceFileNode.'
            );
        }

        return $scopeNode;
    }

    private function nameExistsInUseClause(string $variableName, AnonymousFunctionCreationExpression $function): bool
    {
        if (
            $function->anonymousFunctionUseClause === null
            || $function->anonymousFunctionUseClause->useVariableNameList === null
            || $function->anonymousFunctionUseClause->useVariableNameList instanceof MissingToken
        ) {
            return false;
        }

        foreach ($function->anonymousFunctionUseClause->useVariableNameList->getElements() as $useVariableName) {
            assert($useVariableName instanceof UseVariableName);
            if ($this->variableName($useVariableName) == $variableName) {
                return true;
            }
        }
        return false;
    }
    /**
     * @return Generator<PotentialLocation>
     */
    private function find(Node $scopeNode, string $name, string $uri): Generator
    {
        /** @var Node $node */
        foreach ($scopeNode->getChildNodes() as $node) {
            if ($node instanceof AnonymousFunctionCreationExpression && !$this->nameExistsInUseClause($name, $node)) {
                continue;
            }
            
            if (
                $this->isPotentialReferenceNode($node)
                && $name == $this->variableName($node)
            ) {
                yield PotentialLocation::surely(
                    Location::fromPathAndOffset($uri, $node->getStart())
                );
            } else {
                yield from $this->find($node, $name, $uri);
            }
        }
    }

    private function isPotentialReferenceNode(Node $node): bool
    {
        return
            $node instanceof UseVariableName
            || ($node instanceof Variable)
            || $node instanceof Parameter
        ;
    }

    private function variableName(Node $variable): ?string
    {
        if (
            $variable instanceof Variable ||
            $variable instanceof UseVariableName ||
            $variable instanceof Parameter
        ) {
            return $variable->getName();
        }
        
        return null;
    }
}
