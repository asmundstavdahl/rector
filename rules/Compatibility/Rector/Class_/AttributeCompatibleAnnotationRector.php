<?php

declare (strict_types=1);
namespace Rector\Compatibility\Rector\Class_;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode;
use Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo;
use Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover;
use Rector\Compatibility\ValueObject\PropertyWithPhpDocInfo;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\ValueObject\MethodName;
use Rector\DeadCode\PhpDoc\TagRemover\ParamTagRemover;
use Rector\Php80\NodeAnalyzer\PhpAttributeAnalyzer;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
/**
 * @see \Rector\Tests\Compatibility\Rector\Class_\AttributeCompatibleAnnotationRector\AttributeCompatibleAnnotationRectorTest
 */
final class AttributeCompatibleAnnotationRector extends \Rector\Core\Rector\AbstractRector
{
    /**
     * @var string
     */
    private const ATTRIBUTE = 'Attribute';
    /**
     * @var \Rector\Php80\NodeAnalyzer\PhpAttributeAnalyzer
     */
    private $phpAttributeAnalyzer;
    /**
     * @var \Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover
     */
    private $phpDocTagRemover;
    /**
     * @var \Rector\DeadCode\PhpDoc\TagRemover\ParamTagRemover
     */
    private $paramTagRemover;
    public function __construct(\Rector\Php80\NodeAnalyzer\PhpAttributeAnalyzer $phpAttributeAnalyzer, \Rector\BetterPhpDocParser\PhpDocManipulator\PhpDocTagRemover $phpDocTagRemover, \Rector\DeadCode\PhpDoc\TagRemover\ParamTagRemover $paramTagRemover)
    {
        $this->phpAttributeAnalyzer = $phpAttributeAnalyzer;
        $this->phpDocTagRemover = $phpDocTagRemover;
        $this->paramTagRemover = $paramTagRemover;
    }
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Change annotation to attribute compatible form, see https://tomasvotruba.com/blog/doctrine-annotations-and-attributes-living-together-in-peace/', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample(<<<'CODE_SAMPLE'
use Doctrine\Common\Annotations\Annotation\Required;

/**
 * @annotation
 */
class SomeAnnotation
{
    /**
     * @var string[]
     * @Required()
     */
    public array $enum;
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
use Doctrine\Common\Annotations\Annotation\NamedArgumentConstructor;

/**
 * @annotation
 * @NamedArgumentConstructor
 */
class SomeAnnotation
{
    /**
     * @param string[] $enum
     */
    public function __construct(
        public array $enum
    ) {
    }
}
CODE_SAMPLE
)]);
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [\PhpParser\Node\Stmt\Class_::class];
    }
    /**
     * @param Class_ $node
     */
    public function refactor(\PhpParser\Node $node) : ?\PhpParser\Node
    {
        $phpDocInfo = $this->phpDocInfoFactory->createFromNode($node);
        if (!$phpDocInfo instanceof \Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo) {
            return null;
        }
        if ($this->shouldSkipClass($phpDocInfo, $node)) {
            return null;
        }
        // add "NamedArgumentConstructor"
        $phpDocInfo->addTagValueNode(new \Rector\BetterPhpDocParser\PhpDoc\DoctrineAnnotationTagValueNode(new \PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode('Doctrine\\Common\\Annotations\\Annotation\\NamedArgumentConstructor')));
        // resolve required properties
        $requiredPropertiesWithPhpDocInfos = [];
        foreach ($node->getProperties() as $property) {
            if (!$property->isPublic()) {
                continue;
            }
            $propertyPhpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($property);
            if (!$this->isRequiredProperty($propertyPhpDocInfo, $property)) {
                continue;
            }
            $propertyName = $this->getName($property);
            $requiredPropertiesWithPhpDocInfos[] = new \Rector\Compatibility\ValueObject\PropertyWithPhpDocInfo($propertyName, $property, $propertyPhpDocInfo);
        }
        $constructorClassMethod = $this->createConstructorClassMethod($requiredPropertiesWithPhpDocInfos);
        $node->stmts = \array_merge($node->stmts, [$constructorClassMethod]);
        return $node;
    }
    private function shouldSkipClass(\Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo $phpDocInfo, \PhpParser\Node\Stmt\Class_ $class) : bool
    {
        if (!$phpDocInfo->hasByNames(['Annotation', 'annotation'])) {
            return \true;
        }
        if ($phpDocInfo->hasByAnnotationClass('Doctrine\\Common\\Annotations\\Annotation\\NamedArgumentConstructor')) {
            return \true;
        }
        // has attribute? skip it
        return $this->phpAttributeAnalyzer->hasPhpAttribute($class, self::ATTRIBUTE);
    }
    /**
     * @param PropertyWithPhpDocInfo[] $requiredPropertiesWithPhpDocInfos
     * @return Param[]
     */
    private function createConstructParams(array $requiredPropertiesWithPhpDocInfos) : array
    {
        $params = [];
        foreach ($requiredPropertiesWithPhpDocInfos as $requiredPropertyWithPhpDocInfo) {
            $property = $requiredPropertyWithPhpDocInfo->getProperty();
            $propertyName = $this->getName($property);
            // unwrap nullable type, as variable is required
            $propertyType = $property->type;
            if ($propertyType instanceof \PhpParser\Node\NullableType) {
                $propertyType = $propertyType->type;
            }
            $param = new \PhpParser\Node\Param(new \PhpParser\Node\Expr\Variable($propertyName), null, $propertyType, \false, \false, [], $property->flags);
            $params[] = $param;
            $propertyPhpDocInfo = $requiredPropertyWithPhpDocInfo->getPhpDocInfo();
            // remove required
            $this->phpDocTagRemover->removeByName($propertyPhpDocInfo, 'Doctrine\\Common\\Annotations\\Annotation\\Required');
            $this->removeNode($property);
        }
        return $params;
    }
    private function isRequiredProperty(\Rector\BetterPhpDocParser\PhpDocInfo\PhpDocInfo $propertyPhpDocInfo, \PhpParser\Node\Stmt\Property $property) : bool
    {
        if ($propertyPhpDocInfo->hasByAnnotationClass('Doctrine\\Common\\Annotations\\Annotation\\Required')) {
            return \true;
        }
        // sometimes property has default null, but @var says its not null - that's due to nullability of typed properties
        // in that case, we should treat property as required
        $firstProperty = $property->props[0];
        if (!$firstProperty->default instanceof \PhpParser\Node\Expr) {
            return \false;
        }
        if (!$this->valueResolver->isNull($firstProperty->default)) {
            return \false;
        }
        $varTagValueNode = $propertyPhpDocInfo->getVarTagValueNode();
        if (!$varTagValueNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode) {
            return \false;
        }
        if ($varTagValueNode->type instanceof \PHPStan\PhpDocParser\Ast\Type\NullableTypeNode) {
            return \false;
        }
        return $property->type instanceof \PhpParser\Node\NullableType;
    }
    /**
     * @param PropertyWithPhpDocInfo[] $requiredPropertiesWithPhpDocInfos
     */
    private function createConstructorClassMethod(array $requiredPropertiesWithPhpDocInfos) : \PhpParser\Node\Stmt\ClassMethod
    {
        $classMethod = new \PhpParser\Node\Stmt\ClassMethod(\Rector\Core\ValueObject\MethodName::CONSTRUCT, ['flags' => \PhpParser\Node\Stmt\Class_::MODIFIER_PUBLIC, 'params' => $this->createConstructParams($requiredPropertiesWithPhpDocInfos)]);
        $phpDocInfo = $this->phpDocInfoFactory->createFromNodeOrEmpty($classMethod);
        foreach ($requiredPropertiesWithPhpDocInfos as $requiredPropertyWithPhpDocInfo) {
            $paramTagValueNode = $requiredPropertyWithPhpDocInfo->getParamTagValueNode();
            $phpDocInfo->addTagValueNode($paramTagValueNode);
        }
        $this->paramTagRemover->removeParamTagsIfUseless($phpDocInfo, $classMethod);
        return $classMethod;
    }
}
