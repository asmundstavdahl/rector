<?php

declare (strict_types=1);
namespace Rector\BetterPhpDocParser\PhpDocParser;

use RectorPrefix20211118\Nette\Utils\Strings;
use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use Rector\BetterPhpDocParser\Attributes\AttributeMirrorer;
use Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode;
use Rector\BetterPhpDocParser\PhpDoc\SpacelessPhpDocTagNode;
use Rector\BetterPhpDocParser\PhpDocInfo\TokenIteratorFactory;
use Rector\BetterPhpDocParser\ValueObject\DoctrineAnnotation\SilentKeyMap;
use Rector\BetterPhpDocParser\ValueObject\PhpDocAttributeKey;
use Rector\BetterPhpDocParser\ValueObject\StartAndEnd;
use Rector\Core\Configuration\CurrentNodeProvider;
use Rector\Core\Exception\ShouldNotHappenException;
final class DoctrineAnnotationDecorator
{
    /**
     * Special short annotations, that are resolved as FQN by Doctrine annotation parser
     * @var string[]
     */
    private const ALLOWED_SHORT_ANNOTATIONS = ['Target'];
    /**
     * @see https://regex101.com/r/95kIw4/1
     * @var string
     */
    private const LONG_ANNOTATION_REGEX = '#@\\\\(?<class_name>.*?)(?<annotation_content>\\(.*?\\))#';
    /**
     * @var \Rector\Core\Configuration\CurrentNodeProvider
     */
    private $currentNodeProvider;
    /**
     * @var \Rector\BetterPhpDocParser\PhpDocParser\ClassAnnotationMatcher
     */
    private $classAnnotationMatcher;
    /**
     * @var \Rector\BetterPhpDocParser\PhpDocParser\StaticDoctrineAnnotationParser
     */
    private $staticDoctrineAnnotationParser;
    /**
     * @var \Rector\BetterPhpDocParser\PhpDocInfo\TokenIteratorFactory
     */
    private $tokenIteratorFactory;
    /**
     * @var \Rector\BetterPhpDocParser\Attributes\AttributeMirrorer
     */
    private $attributeMirrorer;
    public function __construct(\Rector\Core\Configuration\CurrentNodeProvider $currentNodeProvider, \Rector\BetterPhpDocParser\PhpDocParser\ClassAnnotationMatcher $classAnnotationMatcher, \Rector\BetterPhpDocParser\PhpDocParser\StaticDoctrineAnnotationParser $staticDoctrineAnnotationParser, \Rector\BetterPhpDocParser\PhpDocInfo\TokenIteratorFactory $tokenIteratorFactory, \Rector\BetterPhpDocParser\Attributes\AttributeMirrorer $attributeMirrorer)
    {
        $this->currentNodeProvider = $currentNodeProvider;
        $this->classAnnotationMatcher = $classAnnotationMatcher;
        $this->staticDoctrineAnnotationParser = $staticDoctrineAnnotationParser;
        $this->tokenIteratorFactory = $tokenIteratorFactory;
        $this->attributeMirrorer = $attributeMirrorer;
    }
    public function decorate(\PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode $phpDocNode) : void
    {
        $currentPhpNode = $this->currentNodeProvider->getNode();
        if (!$currentPhpNode instanceof \PhpParser\Node) {
            throw new \Rector\Core\Exception\ShouldNotHappenException();
        }
        // merge split doctrine nested tags
        $this->mergeNestedDoctrineAnnotations($phpDocNode);
        $this->transformGenericTagValueNodesToDoctrineAnnotationTagValueNodes($phpDocNode, $currentPhpNode);
    }
    /**
     * Join token iterator with all the following nodes if nested
     */
    private function mergeNestedDoctrineAnnotations(\PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode $phpDocNode) : void
    {
        $removedKeys = [];
        foreach ($phpDocNode->children as $key => $phpDocChildNode) {
            if (\in_array($key, $removedKeys, \true)) {
                continue;
            }
            if (!$phpDocChildNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode) {
                continue;
            }
            if (!$phpDocChildNode->value instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode) {
                continue;
            }
            $genericTagValueNode = $phpDocChildNode->value;
            while (isset($phpDocNode->children[$key])) {
                ++$key;
                // no more next nodes
                if (!isset($phpDocNode->children[$key])) {
                    break;
                }
                $nextPhpDocChildNode = $phpDocNode->children[$key];
                if (!$nextPhpDocChildNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode) {
                    continue;
                }
                if (!$nextPhpDocChildNode->value instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode) {
                    continue;
                }
                if ($this->isClosedContent($genericTagValueNode->value)) {
                    break;
                }
                $composedContent = $genericTagValueNode->value . \PHP_EOL . $nextPhpDocChildNode->name . $nextPhpDocChildNode->value;
                $genericTagValueNode->value = $composedContent;
                /** @var StartAndEnd $currentStartAndEnd */
                $currentStartAndEnd = $phpDocChildNode->getAttribute(\Rector\BetterPhpDocParser\ValueObject\PhpDocAttributeKey::START_AND_END);
                /** @var StartAndEnd $nextStartAndEnd */
                $nextStartAndEnd = $nextPhpDocChildNode->getAttribute(\Rector\BetterPhpDocParser\ValueObject\PhpDocAttributeKey::START_AND_END);
                $startAndEnd = new \Rector\BetterPhpDocParser\ValueObject\StartAndEnd($currentStartAndEnd->getStart(), $nextStartAndEnd->getEnd());
                $phpDocChildNode->setAttribute(\Rector\BetterPhpDocParser\ValueObject\PhpDocAttributeKey::START_AND_END, $startAndEnd);
                $currentChildValueNode = $phpDocNode->children[$key];
                if (!$currentChildValueNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode) {
                    continue;
                }
                $currentGenericTagValueNode = $currentChildValueNode->value;
                if (!$currentGenericTagValueNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode) {
                    continue;
                }
                $removedKeys[] = $key;
            }
        }
        foreach (\array_keys($phpDocNode->children) as $key) {
            if (!\in_array($key, $removedKeys, \true)) {
                continue;
            }
            unset($phpDocNode->children[$key]);
        }
    }
    private function transformGenericTagValueNodesToDoctrineAnnotationTagValueNodes(\PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode $phpDocNode, \PhpParser\Node $currentPhpNode) : void
    {
        foreach ($phpDocNode->children as $key => $phpDocChildNode) {
            // the @\FQN use case
            if ($phpDocChildNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode) {
                $match = \RectorPrefix20211118\Nette\Utils\Strings::match($phpDocChildNode->text, self::LONG_ANNOTATION_REGEX);
                $fullyQualifiedAnnotationClass = $match['class_name'] ?? null;
                if ($fullyQualifiedAnnotationClass === null) {
                    continue;
                }
                $annotationContent = $match['annotation_content'] ?? null;
                $tagName = '@\\' . $fullyQualifiedAnnotationClass;
                $formerStartEnd = $phpDocChildNode->getAttribute(\Rector\BetterPhpDocParser\ValueObject\PhpDocAttributeKey::START_AND_END);
                $spacelessPhpDocTagNode = $this->createDoctrineSpacelessPhpDocTagNode($annotationContent, $tagName, $fullyQualifiedAnnotationClass, $formerStartEnd);
                $phpDocNode->children[$key] = $spacelessPhpDocTagNode;
                continue;
            }
            if (!$phpDocChildNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode) {
                continue;
            }
            if (!$phpDocChildNode->value instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode) {
                continue;
            }
            // known doc tag to annotation class
            $fullyQualifiedAnnotationClass = $this->classAnnotationMatcher->resolveTagFullyQualifiedName($phpDocChildNode->name, $currentPhpNode);
            // not an annotations class
            if (\strpos($fullyQualifiedAnnotationClass, '\\') === \false && !\in_array($fullyQualifiedAnnotationClass, self::ALLOWED_SHORT_ANNOTATIONS, \true)) {
                continue;
            }
            $spacelessPhpDocTagNode = $this->createSpacelessPhpDocTagNode($phpDocChildNode->name, $phpDocChildNode->value, $fullyQualifiedAnnotationClass);
            $this->attributeMirrorer->mirror($phpDocChildNode, $spacelessPhpDocTagNode);
            $phpDocNode->children[$key] = $spacelessPhpDocTagNode;
        }
    }
    /**
     * This is closed block, e.g. {( ... )},
     * false on: {( ... )
     */
    private function isClosedContent(string $composedContent) : bool
    {
        $composedTokenIterator = $this->tokenIteratorFactory->create($composedContent);
        $tokenCount = $composedTokenIterator->count();
        $openBracketCount = 0;
        $closeBracketCount = 0;
        if ($composedContent === '') {
            return \true;
        }
        do {
            if ($composedTokenIterator->isCurrentTokenTypes([\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_CURLY_BRACKET, \PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_OPEN_PARENTHESES]) || \strpos($composedTokenIterator->currentTokenValue(), '(') !== \false) {
                ++$openBracketCount;
            }
            if ($composedTokenIterator->isCurrentTokenTypes([\PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_CURLY_BRACKET, \PHPStan\PhpDocParser\Lexer\Lexer::TOKEN_CLOSE_PARENTHESES]) || \strpos($composedTokenIterator->currentTokenValue(), ')') !== \false) {
                ++$closeBracketCount;
            }
            $composedTokenIterator->next();
        } while ($composedTokenIterator->currentPosition() < $tokenCount - 1);
        return $openBracketCount === $closeBracketCount;
    }
    private function createSpacelessPhpDocTagNode(string $tagName, \PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode $genericTagValueNode, string $fullyQualifiedAnnotationClass) : \Rector\BetterPhpDocParser\PhpDoc\SpacelessPhpDocTagNode
    {
        $annotationContent = $genericTagValueNode->value;
        $formerStartEnd = $genericTagValueNode->getAttribute(\Rector\BetterPhpDocParser\ValueObject\PhpDocAttributeKey::START_AND_END);
        return $this->createDoctrineSpacelessPhpDocTagNode($annotationContent, $tagName, $fullyQualifiedAnnotationClass, $formerStartEnd);
    }
    private function createDoctrineSpacelessPhpDocTagNode(string $annotationContent, string $tagName, string $fullyQualifiedAnnotationClass, \Rector\BetterPhpDocParser\ValueObject\StartAndEnd $startAndEnd) : \Rector\BetterPhpDocParser\PhpDoc\SpacelessPhpDocTagNode
    {
        $nestedTokenIterator = $this->tokenIteratorFactory->create($annotationContent);
        // mimics doctrine behavior just in phpdoc-parser syntax :)
        // https://github.com/doctrine/annotations/blob/c66f06b7c83e9a2a7523351a9d5a4b55f885e574/lib/Doctrine/Common/Annotations/DocParser.php#L742
        $values = $this->staticDoctrineAnnotationParser->resolveAnnotationMethodCall($nestedTokenIterator);
        $identifierTypeNode = new \PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode($tagName);
        $identifierTypeNode->setAttribute(\Rector\BetterPhpDocParser\ValueObject\PhpDocAttributeKey::RESOLVED_CLASS, $fullyQualifiedAnnotationClass);
        $doctrineAnnotationTagValueNode = new \Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode($identifierTypeNode, $annotationContent, $values, \Rector\BetterPhpDocParser\ValueObject\DoctrineAnnotation\SilentKeyMap::CLASS_NAMES_TO_SILENT_KEYS[$fullyQualifiedAnnotationClass] ?? null);
        $doctrineAnnotationTagValueNode->setAttribute(\Rector\BetterPhpDocParser\ValueObject\PhpDocAttributeKey::START_AND_END, $startAndEnd);
        return new \Rector\BetterPhpDocParser\PhpDoc\SpacelessPhpDocTagNode($tagName, $doctrineAnnotationTagValueNode);
    }
}
