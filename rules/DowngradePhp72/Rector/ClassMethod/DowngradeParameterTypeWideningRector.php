<?php

declare (strict_types=1);
namespace Rector\DowngradePhp72\Rector\ClassMethod;

use PhpParser\Node;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Reflection\ClassReflection;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Exception\ShouldNotHappenException;
use Rector\Core\Rector\AbstractRector;
use Rector\Core\Reflection\ReflectionResolver;
use Rector\DowngradePhp72\NodeAnalyzer\BuiltInMethodAnalyzer;
use Rector\DowngradePhp72\NodeAnalyzer\OverrideFromAnonymousClassMethodAnalyzer;
use Rector\DowngradePhp72\NodeAnalyzer\SealedClassAnalyzer;
use Rector\DowngradePhp72\PhpDoc\NativeParamToPhpDocDecorator;
use Rector\TypeDeclaration\NodeAnalyzer\AutowiredClassMethodOrPropertyAnalyzer;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use RectorPrefix20211118\Webmozart\Assert\Assert;
/**
 * @changelog https://www.php.net/manual/en/migration72.new-features.php#migration72.new-features.param-type-widening
 * @see https://3v4l.org/fOgSE
 *
 * @see \Rector\Tests\DowngradePhp72\Rector\ClassMethod\DowngradeParameterTypeWideningRector\DowngradeParameterTypeWideningRectorTest
 */
final class DowngradeParameterTypeWideningRector extends \Rector\Core\Rector\AbstractRector implements \Rector\Core\Contract\Rector\ConfigurableRectorInterface
{
    /**
     * @var string
     */
    public const SAFE_TYPES = 'safe_types';
    /**
     * @var string
     */
    public const SAFE_TYPES_TO_METHODS = 'safe_types_to_methods';
    /**
     * @var string[]
     */
    private $safeTypes = [];
    /**
     * @var array<string, string[]>
     */
    private $safeTypesToMethods = [];
    /**
     * @var \Rector\DowngradePhp72\PhpDoc\NativeParamToPhpDocDecorator
     */
    private $nativeParamToPhpDocDecorator;
    /**
     * @var \Rector\Core\Reflection\ReflectionResolver
     */
    private $reflectionResolver;
    /**
     * @var \Rector\TypeDeclaration\NodeAnalyzer\AutowiredClassMethodOrPropertyAnalyzer
     */
    private $autowiredClassMethodOrPropertyAnalyzer;
    /**
     * @var \Rector\DowngradePhp72\NodeAnalyzer\BuiltInMethodAnalyzer
     */
    private $builtInMethodAnalyzer;
    /**
     * @var \Rector\DowngradePhp72\NodeAnalyzer\OverrideFromAnonymousClassMethodAnalyzer
     */
    private $overrideFromAnonymousClassMethodAnalyzer;
    /**
     * @var \Rector\DowngradePhp72\NodeAnalyzer\SealedClassAnalyzer
     */
    private $sealedClassAnalyzer;
    public function __construct(\Rector\DowngradePhp72\PhpDoc\NativeParamToPhpDocDecorator $nativeParamToPhpDocDecorator, \Rector\Core\Reflection\ReflectionResolver $reflectionResolver, \Rector\TypeDeclaration\NodeAnalyzer\AutowiredClassMethodOrPropertyAnalyzer $autowiredClassMethodOrPropertyAnalyzer, \Rector\DowngradePhp72\NodeAnalyzer\BuiltInMethodAnalyzer $builtInMethodAnalyzer, \Rector\DowngradePhp72\NodeAnalyzer\OverrideFromAnonymousClassMethodAnalyzer $overrideFromAnonymousClassMethodAnalyzer, \Rector\DowngradePhp72\NodeAnalyzer\SealedClassAnalyzer $sealedClassAnalyzer)
    {
        $this->nativeParamToPhpDocDecorator = $nativeParamToPhpDocDecorator;
        $this->reflectionResolver = $reflectionResolver;
        $this->autowiredClassMethodOrPropertyAnalyzer = $autowiredClassMethodOrPropertyAnalyzer;
        $this->builtInMethodAnalyzer = $builtInMethodAnalyzer;
        $this->overrideFromAnonymousClassMethodAnalyzer = $overrideFromAnonymousClassMethodAnalyzer;
        $this->sealedClassAnalyzer = $sealedClassAnalyzer;
    }
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Change param type to match the lowest type in whole family tree', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample(<<<'CODE_SAMPLE'
interface SomeInterface
{
    public function test(array $input);
}

final class SomeClass implements SomeInterface
{
    public function test($input)
    {
    }
}
CODE_SAMPLE
, <<<'CODE_SAMPLE'
interface SomeInterface
{
    /**
     * @param mixed[] $input
     */
    public function test($input);
}

final class SomeClass implements SomeInterface
{
    public function test($input)
    {
    }
}
CODE_SAMPLE
, [self::SAFE_TYPES => [], self::SAFE_TYPES_TO_METHODS => []])]);
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
        $classLike = $this->betterNodeFinder->findParentType($node, \PhpParser\Node\Stmt\ClassLike::class);
        if (!$classLike instanceof \PhpParser\Node\Stmt\ClassLike) {
            return null;
        }
        $ancestorOverridableAnonymousClass = $this->overrideFromAnonymousClassMethodAnalyzer->matchAncestorClassReflectionOverrideable($classLike, $node);
        if ($ancestorOverridableAnonymousClass instanceof \PHPStan\Reflection\ClassReflection) {
            return $this->processRemoveParamTypeFromMethod($ancestorOverridableAnonymousClass, $node);
        }
        $classReflection = $this->reflectionResolver->resolveClassAndAnonymousClass($classLike);
        if (!$classReflection instanceof \PHPStan\Reflection\ClassReflection) {
            throw new \Rector\Core\Exception\ShouldNotHappenException();
        }
        return $this->processRemoveParamTypeFromMethod($classReflection, $node);
    }
    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration) : void
    {
        $safeTypes = $configuration[self::SAFE_TYPES] ?? [];
        \RectorPrefix20211118\Webmozart\Assert\Assert::isArray($safeTypes);
        \RectorPrefix20211118\Webmozart\Assert\Assert::allString($safeTypes);
        $this->safeTypes = $safeTypes;
        $safeTypesToMethods = $configuration[self::SAFE_TYPES_TO_METHODS] ?? [];
        \RectorPrefix20211118\Webmozart\Assert\Assert::isArray($safeTypesToMethods);
        foreach ($safeTypesToMethods as $key => $value) {
            \RectorPrefix20211118\Webmozart\Assert\Assert::string($key);
            \RectorPrefix20211118\Webmozart\Assert\Assert::allString($value);
        }
        $this->safeTypesToMethods = $safeTypesToMethods;
    }
    private function shouldSkip(\PHPStan\Reflection\ClassReflection $classReflection, \PhpParser\Node\Stmt\ClassMethod $classMethod) : bool
    {
        if ($classMethod->params === []) {
            return \true;
        }
        if ($classMethod->isPrivate()) {
            return \true;
        }
        if ($classMethod->isMagic()) {
            return \true;
        }
        if ($this->sealedClassAnalyzer->isSealedClass($classReflection)) {
            return \true;
        }
        if ($this->autowiredClassMethodOrPropertyAnalyzer->detect($classMethod)) {
            return \true;
        }
        if ($this->hasParamAlreadyNonTyped($classMethod)) {
            return \true;
        }
        return $this->isSafeType($classReflection, $classMethod);
    }
    private function processRemoveParamTypeFromMethod(\PHPStan\Reflection\ClassReflection $classReflection, \PhpParser\Node\Stmt\ClassMethod $classMethod) : ?\PhpParser\Node\Stmt\ClassMethod
    {
        if ($this->shouldSkip($classReflection, $classMethod)) {
            return null;
        }
        if ($this->builtInMethodAnalyzer->isImplementsBuiltInInterface($classReflection, $classMethod)) {
            return null;
        }
        // Downgrade every scalar parameter, just to be sure
        foreach (\array_keys($classMethod->params) as $paramPosition) {
            $this->removeParamTypeFromMethod($classMethod, $paramPosition);
        }
        return $classMethod;
    }
    private function removeParamTypeFromMethod(\PhpParser\Node\Stmt\ClassMethod $classMethod, int $paramPosition) : void
    {
        $param = $classMethod->params[$paramPosition] ?? null;
        if (!$param instanceof \PhpParser\Node\Param) {
            return;
        }
        // Add the current type in the PHPDoc
        $this->nativeParamToPhpDocDecorator->decorate($classMethod, $param);
        $param->type = null;
    }
    private function hasParamAlreadyNonTyped(\PhpParser\Node\Stmt\ClassMethod $classMethod) : bool
    {
        foreach ($classMethod->params as $param) {
            if ($param->type !== null) {
                return \false;
            }
        }
        return \true;
    }
    private function isSafeType(\PHPStan\Reflection\ClassReflection $classReflection, \PhpParser\Node\Stmt\ClassMethod $classMethod) : bool
    {
        $classReflectionName = $classReflection->getName();
        foreach ($this->safeTypes as $safeType) {
            if ($classReflection->isSubclassOf($safeType)) {
                return \true;
            }
            // skip self too
            if ($classReflectionName === $safeType) {
                return \true;
            }
        }
        foreach ($this->safeTypesToMethods as $safeType => $safeMethods) {
            if (!$this->isNames($classMethod, $safeMethods)) {
                continue;
            }
            if ($classReflection->isSubclassOf($safeType)) {
                return \true;
            }
            // skip self too
            if ($classReflectionName === $safeType) {
                return \true;
            }
        }
        return \false;
    }
}
