<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace RectorPrefix20211118\Symfony\Component\Config\Loader;

use RectorPrefix20211118\Symfony\Component\Config\Exception\LoaderLoadException;
/**
 * Loader is the abstract class used by all built-in loaders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Loader implements \RectorPrefix20211118\Symfony\Component\Config\Loader\LoaderInterface
{
    protected $resolver;
    protected $env;
    public function __construct(string $env = null)
    {
        $this->env = $env;
    }
    /**
     * {@inheritdoc}
     */
    public function getResolver()
    {
        return $this->resolver;
    }
    /**
     * {@inheritdoc}
     * @param \Symfony\Component\Config\Loader\LoaderResolverInterface $resolver
     */
    public function setResolver($resolver)
    {
        $this->resolver = $resolver;
    }
    /**
     * Imports a resource.
     *
     * @param mixed       $resource A resource
     * @param string|null $type     The resource type or null if unknown
     *
     * @return mixed
     */
    public function import($resource, $type = null)
    {
        return $this->resolve($resource, $type)->load($resource, $type);
    }
    /**
     * Finds a loader able to load an imported resource.
     *
     * @param mixed       $resource A resource
     * @param string|null $type     The resource type or null if unknown
     *
     * @return $this|LoaderInterface
     *
     * @throws LoaderLoadException If no loader is found
     */
    public function resolve($resource, $type = null)
    {
        if ($this->supports($resource, $type)) {
            return $this;
        }
        $loader = null === $this->resolver ? \false : $this->resolver->resolve($resource, $type);
        if (\false === $loader) {
            throw new \RectorPrefix20211118\Symfony\Component\Config\Exception\LoaderLoadException($resource, null, 0, null, $type);
        }
        return $loader;
    }
}
