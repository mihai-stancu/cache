<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle\Doctrine;

use Doctrine\Common\Cache\Cache as DoctrineCache;
use Doctrine\Common\Persistence\ObjectRepository;
use MS\CacheBundle\Cache as MSCache;

class Repository implements ObjectRepository
{
    /** @var DoctrineCache|MSCache  */
    protected $cache;

    /** @var string  */
    protected $className;

    /**
     * @param DoctrineCache|MSCache $cache
     * @param string                $className
     */
    public function __construct(DoctrineCache $cache, $className)
    {
        $this->cache = $cache;
        $this->className = $className;
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @param array|string[] $tags
     *
     * @return string
     */
    public function getId($tags)
    {
        ksort($tags);
        $tags = serialize($tags);

        $id = md5($tags, true);
        $id = base64_encode($id);
        $id = trim($id, '=');

        return $id;
    }

    /**
     * @param string $id
     *
     * @return object
     */
    public function find($id)
    {
        $this->cache->setNamespace($this->className);

        return $this->cache->fetch($id);
    }

    /**
     * @return object[]
     */
    public function findAll()
    {
        return array();
    }

    /**
     * @param array      $criteria
     * @param array|null $orderBy
     * @param int        $limit
     * @param int        $offset
     *
     * @return object[]
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        $this->cache->setNamespace($this->className);

        return $this->cache->fetchByTags($criteria);
    }

    /**
     * @param array $criteria
     *
     * @return object[]
     */
    public function findOneBy(array $criteria)
    {
        $this->cache->setNamespace($this->className);
        $id = $this->getId($criteria);

        return $this->cache->fetch($id);
    }
}
