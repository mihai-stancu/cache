<?php

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
            $outer->addMethodCall('setNamespace', [isset($tags[0]['namespace']) ? $tags[0]['namespace'] : $id]);
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

            case class_exists('Memcached') and is_subclass_of($class, 'Memcached', true):
                return MemcachedClient::class;
        }
    }
}
