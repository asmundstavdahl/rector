<?php

declare (strict_types=1);
namespace Rector\Php81\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\ClassMethod;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\ValueObject\MethodName;
use Rector\Core\ValueObject\PhpVersionFeature;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
/**
 * @see \Rector\Tests\Php81\Rector\ClassMethod\NewInInitializerRector\NewInInitializerRectorTest
 */
final class NewInInitializerRector extends \Rector\Core\Rector\AbstractRector implements \Rector\VersionBonding\Contract\MinPhpVersionInterface
{
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Replace property declaration of new state with direct new', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\CodeSample(<<<'CODE_SAMPLE'
class SomeClass
{
    private Logger $logger;

    public function __construct(
        ?Logger $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger;
    }
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
class SomeClass
{
    private Logger $logger;

    public function __construct(
        Logger $logger = new NullLogger,
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
        return [\PhpParser\Node\Stmt\ClassMethod::class];
    }
    /**
     * @param ClassMethod $node
     */
    public function refactor(\PhpParser\Node $node) : ?\PhpParser\Node
    {
        if (!$this->isName($node, \Rector\Core\ValueObject\MethodName::CONSTRUCT)) {
            return null;
        }
        if ($node->params === []) {
            return null;
        }
        if ($node->stmts === []) {
            return null;
        }
        foreach ($node->params as $param) {
            if (!$param->type instanceof \PhpParser\Node\NullableType) {
                continue;
            }
            /** @var string $paramName */
            $paramName = $this->getName($param->var);
            $toPropertyAssigns = $this->betterNodeFinder->findClassMethodAssignsToLocalProperty($node, $paramName);
            foreach ($toPropertyAssigns as $toPropertyAssign) {
                if (!$toPropertyAssign->expr instanceof \PhpParser\Node\Expr\BinaryOp\Coalesce) {
                    continue;
                }
                /** @var NullableType $currentParamType */
                $currentParamType = $param->type;
                $param->type = $currentParamType->type;
                $coalesce = $toPropertyAssign->expr;
                $param->default = $coalesce->right;
                $this->removeNode($toPropertyAssign);
            }
        }
        return $node;
    }
    public function provideMinPhpVersion() : int
    {
        return \Rector\Core\ValueObject\PhpVersionFeature::NEW_INITIALIZERS;
    }
}
