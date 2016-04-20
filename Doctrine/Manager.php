<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle\Doctrine;

use Doctrine\Common\Cache\Cache as DoctrineCache;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use MS\CacheBundle\Cache as MSCache;

class Manager implements ObjectManager
{
    /** @var DoctrineCache|MSCache  */
    public $cache;

    /** @var array|Repository[]  */
    protected $repositories = [];

    /** @var  object[][] */
    protected $objects = [];

    /**
     * @param DoctrineCache|MSCache $cache
     */
    public function __construct(DoctrineCache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param object $object
     *
     * @return bool
     */
    public function contains($object)
    {
        $className = get_class($object);
        $tags = $object->getTags();
        $id = $this->getRepository($className)->getId($tags);

        return isset($this->objects[$className][$id]);
    }

    /**
     * @param string $className
     * @param string $id
     *
     * @return object
     */
    public function find($className, $id)
    {
        return $this->getRepository($className)->find($id);
    }

    /**
     * @param object $object
     *
     * @return object|void
     */
    public function refresh($object)
    {
        $className = get_class($object);
        $tags = $object->getTags();
        $id = $this->getRepository($className)->getId($tags);

        return $this->find($className, $id);
    }

    /**
     * @param object $object
     *
     * @return object
     */
    public function merge($object)
    {
        $className = get_class($object);
        $tags = $object->getTags();
        $id = $this->getRepository($className)->getId($tags);

        return $this->objects[$className][$id];
    }

    /**
     * @param object $object
     */
    public function persist($object)
    {
        $className = get_class($object);
        $tags = $object->getTags();
        $id = $this->getRepository($className)->getId($tags);

        $this->objects[$className][$id] = $object;
    }

    /**
     * @param object $object
     */
    public function detach($object)
    {
        $className = get_class($object);
        $tags = $object->getTags();
        $id = $this->getRepository($className)->getId($tags);

        unset($this->objects[$className][$id]);
    }

    /**
     * @param object $object
     *
     * @return bool
     */
    public function remove($object)
    {
        $className = get_class($object);
        $this->cache->setNamespace($className);
        $tags = $object->getTags();
        $id = $this->getRepository($className)->getId($tags);

        return $this->cache->delete($id);
    }

    public function flush()
    {
        foreach ($this->objects as $className => $objects) {
            $this->cache->setNamespace($className);

            $this->cache->saveMultiple($objects);
        }
    }

    /**
     * @param string $className
     */
    public function clear($className = null)
    {
        if (isset($className)) {
            unset($this->objects[$className]);
        } else {
            $this->objects = [];
        }
    }

    /**
     * @param object $object
     *
     * @return object
     */
    public function initializeObject($object)
    {
        return $object;
    }

    /**
     * @return ClassMetadata
     */
    public function getMetadataFactory()
    {
        return;
    }

    /**
     * @param string $className
     *
     * @return ClassMetadata
     */
    public function getClassMetadata($className)
    {
        return;
    }

    /**
     * @param string $className
     *
     * @return ObjectRepository|Repository
     */
    public function getRepository($className)
    {
        if (!isset($this->repositories[$className])) {
            $this->repositories[$className] = new Repository($this->cache, $className);
        }

        return $this->repositories[$className];
    }
}
