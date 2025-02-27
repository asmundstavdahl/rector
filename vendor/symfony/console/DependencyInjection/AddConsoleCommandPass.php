<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace RectorPrefix20211118\Symfony\Component\Console\DependencyInjection;

use RectorPrefix20211118\Symfony\Component\Console\Command\Command;
use RectorPrefix20211118\Symfony\Component\Console\Command\LazyCommand;
use RectorPrefix20211118\Symfony\Component\Console\CommandLoader\ContainerCommandLoader;
use RectorPrefix20211118\Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use RectorPrefix20211118\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use RectorPrefix20211118\Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use RectorPrefix20211118\Symfony\Component\DependencyInjection\ContainerBuilder;
use RectorPrefix20211118\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use RectorPrefix20211118\Symfony\Component\DependencyInjection\Reference;
use RectorPrefix20211118\Symfony\Component\DependencyInjection\TypedReference;
/**
 * Registers console commands.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class AddConsoleCommandPass implements \RectorPrefix20211118\Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface
{
    private $commandLoaderServiceId;
    private $commandTag;
    private $noPreloadTag;
    private $privateTagName;
    public function __construct(string $commandLoaderServiceId = 'console.command_loader', string $commandTag = 'console.command', string $noPreloadTag = 'container.no_preload', string $privateTagName = 'container.private')
    {
        if (0 < \func_num_args()) {
            trigger_deprecation('symfony/console', '5.3', 'Configuring "%s" is deprecated.', __CLASS__);
        }
        $this->commandLoaderServiceId = $commandLoaderServiceId;
        $this->commandTag = $commandTag;
        $this->noPreloadTag = $noPreloadTag;
        $this->privateTagName = $privateTagName;
    }
    /**
     * @param \Symfony\Component\DependencyInjection\ContainerBuilder $container
     */
    public function process($container)
    {
        $commandServices = $container->findTaggedServiceIds($this->commandTag, \true);
        $lazyCommandMap = [];
        $lazyCommandRefs = [];
        $serviceIds = [];
        foreach ($commandServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $definition->addTag($this->noPreloadTag);
            $class = $container->getParameterBag()->resolveValue($definition->getClass());
            if (isset($tags[0]['command'])) {
                $aliases = $tags[0]['command'];
            } else {
                if (!($r = $container->getReflectionClass($class))) {
                    throw new \RectorPrefix20211118\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
                }
                if (!$r->isSubclassOf(\RectorPrefix20211118\Symfony\Component\Console\Command\Command::class)) {
                    throw new \RectorPrefix20211118\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException(\sprintf('The service "%s" tagged "%s" must be a subclass of "%s".', $id, $this->commandTag, \RectorPrefix20211118\Symfony\Component\Console\Command\Command::class));
                }
                $aliases = $class::getDefaultName();
            }
            $aliases = \explode('|', $aliases ?? '');
            $commandName = \array_shift($aliases);
            if ($isHidden = '' === $commandName) {
                $commandName = \array_shift($aliases);
            }
            if (null === $commandName) {
                if (!$definition->isPublic() || $definition->isPrivate() || $definition->hasTag($this->privateTagName)) {
                    $commandId = 'console.command.public_alias.' . $id;
                    $container->setAlias($commandId, $id)->setPublic(\true);
                    $id = $commandId;
                }
                $serviceIds[] = $id;
                continue;
            }
            $description = $tags[0]['description'] ?? null;
            unset($tags[0]);
            $lazyCommandMap[$commandName] = $id;
            $lazyCommandRefs[$id] = new \RectorPrefix20211118\Symfony\Component\DependencyInjection\TypedReference($id, $class);
            foreach ($aliases as $alias) {
                $lazyCommandMap[$alias] = $id;
            }
            foreach ($tags as $tag) {
                if (isset($tag['command'])) {
                    $aliases[] = $tag['command'];
                    $lazyCommandMap[$tag['command']] = $id;
                }
                $description = $description ?? $tag['description'] ?? null;
            }
            $definition->addMethodCall('setName', [$commandName]);
            if ($aliases) {
                $definition->addMethodCall('setAliases', [$aliases]);
            }
            if ($isHidden) {
                $definition->addMethodCall('setHidden', [\true]);
            }
            if (!$description) {
                if (!($r = $container->getReflectionClass($class))) {
                    throw new \RectorPrefix20211118\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException(\sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
                }
                if (!$r->isSubclassOf(\RectorPrefix20211118\Symfony\Component\Console\Command\Command::class)) {
                    throw new \RectorPrefix20211118\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException(\sprintf('The service "%s" tagged "%s" must be a subclass of "%s".', $id, $this->commandTag, \RectorPrefix20211118\Symfony\Component\Console\Command\Command::class));
                }
                $description = $class::getDefaultDescription();
            }
            if ($description) {
                $definition->addMethodCall('setDescription', [$description]);
                $container->register('.' . $id . '.lazy', \RectorPrefix20211118\Symfony\Component\Console\Command\LazyCommand::class)->setArguments([$commandName, $aliases, $description, $isHidden, new \RectorPrefix20211118\Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument($lazyCommandRefs[$id])]);
                $lazyCommandRefs[$id] = new \RectorPrefix20211118\Symfony\Component\DependencyInjection\Reference('.' . $id . '.lazy');
            }
        }
        $container->register($this->commandLoaderServiceId, \RectorPrefix20211118\Symfony\Component\Console\CommandLoader\ContainerCommandLoader::class)->setPublic(\true)->addTag($this->noPreloadTag)->setArguments([\RectorPrefix20211118\Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass::register($container, $lazyCommandRefs), $lazyCommandMap]);
        $container->setParameter('console.command.ids', $serviceIds);
    }
}
