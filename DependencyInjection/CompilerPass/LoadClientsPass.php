<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle\DependencyInjection\CompilerPass;

use MS\CacheBundle\Client\MemcachedClient;
use MS\CacheBundle\Client\RedisClient;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class LoadClientsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $services = $container->findTaggedServiceIds('ms.client');

        if (empty($services)) {
            return;
        }

        foreach ($services as $id => $tags) {
            $inner = $container->findDefinition($id);
            $innerClass = $inner->getClass();
            $outerClass = $this->getOuterClass($innerClass);

            if (!$outerClass) {
                return;
            }

            $container->setDefinition($id.'.inner', $inner);

            $outer = new Definition();
            $outer->setClass($outerClass);
            $outer->setArguments([new Reference($id.'.inner')]);

            $namespace = isset($tags[0]['namespace']) ? $tags[0]['namespace'] : $id;
            $outer->addMethodCall('setNamespace', [$namespace]);
            $container->setDefinition($id, $outer);
        }
    }

    /**
     * @param string $class
     *
     * @return string
     */
    protected function getOuterClass($class)
    {
        switch (true) {
            case class_exists('Redis') and ($class === 'Redis' or is_subclass_of($class, 'Redis', true)):
                return RedisClient::class;

            case class_exists('Memcached') or is_subclass_of($class, 'Memcached', true):
                return MemcachedClient::class;
        }
    }
}
