<?php

declare (strict_types=1);
namespace Rector\StaticTypeMapper;

use PhpParser\Node;
use PhpParser\Node\ComplexType;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use Rector\Core\Exception\NotImplementedYetException;
use Rector\NodeTypeResolver\Node\AttributeKey;
use Rector\PHPStanStaticTypeMapper\Enum\TypeKind;
use Rector\PHPStanStaticTypeMapper\PHPStanStaticTypeMapper;
use Rector\StaticTypeMapper\Mapper\PhpParserNodeMapper;
use Rector\StaticTypeMapper\Naming\NameScopeFactory;
use Rector\StaticTypeMapper\PhpDoc\PhpDocTypeMapper;
/**
 * Maps PhpParser <=> PHPStan <=> PHPStan doc <=> string type nodes between all possible formats
 * @see \Rector\Tests\NodeTypeResolver\StaticTypeMapper\StaticTypeMapperTest
 */
final class StaticTypeMapper
{
    /**
     * @var \Rector\StaticTypeMapper\Naming\NameScopeFactory
     */
    private $nameScopeFactory;
    /**
     * @var \Rector\PHPStanStaticTypeMapper\PHPStanStaticTypeMapper
     */
    private $phpStanStaticTypeMapper;
    /**
     * @var \Rector\StaticTypeMapper\PhpDoc\PhpDocTypeMapper
     */
    private $phpDocTypeMapper;
    /**
     * @var \Rector\StaticTypeMapper\Mapper\PhpParserNodeMapper
     */
    private $phpParserNodeMapper;
    public function __construct(\Rector\StaticTypeMapper\Naming\NameScopeFactory $nameScopeFactory, \Rector\PHPStanStaticTypeMapper\PHPStanStaticTypeMapper $phpStanStaticTypeMapper, \Rector\StaticTypeMapper\PhpDoc\PhpDocTypeMapper $phpDocTypeMapper, \Rector\StaticTypeMapper\Mapper\PhpParserNodeMapper $phpParserNodeMapper)
    {
        $this->nameScopeFactory = $nameScopeFactory;
        $this->phpStanStaticTypeMapper = $phpStanStaticTypeMapper;
        $this->phpDocTypeMapper = $phpDocTypeMapper;
        $this->phpParserNodeMapper = $phpParserNodeMapper;
    }
    public function mapPHPStanTypeToPHPStanPhpDocTypeNode(\PHPStan\Type\Type $phpStanType, \Rector\PHPStanStaticTypeMapper\Enum\TypeKind $typeKind) : \PHPStan\PhpDocParser\Ast\Type\TypeNode
    {
        return $this->phpStanStaticTypeMapper->mapToPHPStanPhpDocTypeNode($phpStanType, $typeKind);
    }
    /**
     * @return Name|ComplexType|null
     */
    public function mapPHPStanTypeToPhpParserNode(\PHPStan\Type\Type $phpStanType, \Rector\PHPStanStaticTypeMapper\Enum\TypeKind $typeKind) : ?\PhpParser\Node
    {
        return $this->phpStanStaticTypeMapper->mapToPhpParserNode($phpStanType, $typeKind);
    }
    public function mapPhpParserNodePHPStanType(\PhpParser\Node $node) : \PHPStan\Type\Type
    {
        return $this->phpParserNodeMapper->mapToPHPStanType($node);
    }
    public function mapPHPStanPhpDocTypeToPHPStanType(\PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode $phpDocTagValueNode, \PhpParser\Node $node) : \PHPStan\Type\Type
    {
        if ($phpDocTagValueNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode) {
            // special case
            $nameScope = $this->nameScopeFactory->createNameScopeFromNodeWithoutTemplateTypes($node);
            if ($phpDocTagValueNode->bound === null) {
                return new \PHPStan\Type\MixedType();
            }
            return $this->phpDocTypeMapper->mapToPHPStanType($phpDocTagValueNode->bound, $node, $nameScope);
        }
        if ($phpDocTagValueNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode || $phpDocTagValueNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode || $phpDocTagValueNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode || $phpDocTagValueNode instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode) {
            return $this->mapPHPStanPhpDocTypeNodeToPHPStanType($phpDocTagValueNode->type, $node);
        }
        throw new \Rector\Core\Exception\NotImplementedYetException(__METHOD__ . ' for ' . \get_class($phpDocTagValueNode));
    }
    public function mapPHPStanPhpDocTypeNodeToPHPStanType(\PHPStan\PhpDocParser\Ast\Type\TypeNode $typeNode, \PhpParser\Node $node) : \PHPStan\Type\Type
    {
        if ($node instanceof \PhpParser\Node\Param) {
            $classMethod = $node->getAttribute(\Rector\NodeTypeResolver\Node\AttributeKey::PARENT_NODE);
            if ($classMethod instanceof \PhpParser\Node\Stmt\ClassMethod) {
                // param does not hany any clue about template map, but class method has
                $node = $classMethod;
            }
        }
        $nameScope = $this->nameScopeFactory->createNameScopeFromNode($node);
        return $this->phpDocTypeMapper->mapToPHPStanType($typeNode, $node, $nameScope);
    }
}
