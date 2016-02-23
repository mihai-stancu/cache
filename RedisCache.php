<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle;

use Predis\Client as Predis;

class RedisCache extends AbstractCache
{
    /**
     * @param \Redis|Predis $client
     */
    public function __construct($client)
    {
        $this->client = $client;
    }

    /** @var  \Redis|Predis */
    protected $client;

    /**
     * @return \Redis|Predis
     */
    public function getClient()
    {
        return $this->client;
    }

    public function beginTransaction()
    {
        $this->getClient()->multi();
    }

    public function commit()
    {
        return $this->getClient()->exec();
    }

    public function rollback()
    {
        return $this->getClient()->discard();
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function contains($key)
    {
        $nsKey = $this->applyNamespace($key);

        return $this->getClient()->exists($nsKey);
    }

    /**
     * @param array|string[] $keys
     *
     * @return bool
     */
    public function containsMultiple($keys)
    {
        $nsKeys = $this->applyNamespace($keys);

        return count($keys) === $this->getClient()->exists($nsKeys);
    }

    /**
     * @param string $key
     *
     * @return bool|string
     */
    public function fetch($key)
    {
        $nsKeys = $this->applyNamespace($key);

        return $this->getClient()->get($nsKeys);
    }

    /**
     * @param array|string[] $keys
     *
     * @return array|mixed[]
     */
    public function fetchMultiple(array $keys = array())
    {
        $nsKeys = $this->applyNamespace($keys);
        $values = $this->getClient()->mget($nsKeys);
        $values = array_combine($keys, $values);

        return $values;
    }

    /**
     * @param array|string[] $tags
     *
     * @return array|mixed[]
     */
    public function fetchByTags(array $tags = array())
    {
        $nsTags = $this->applyNamespace($tags, 'tag');
        $keys = call_user_func_array(array($this->getClient(), 'sInter'), $nsTags);
        $values = empty($keys) ? array() : $this->fetchMultiple($keys);

        return $values;
    }

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function add($key, $value, array $tags = array(), $ttl = null)
    {
        $nsKey = $this->applyNamespace($key);

        return $this->getClient()->setnx($nsKey, $value);
    }

    /**
     * @param mixed[]        $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function addMultiple($values, array $tags = array(), $ttl = null)
    {
        $keys = array_keys($values);
        $nsKeys = $this->applyNamespace($keys);
        $nsValues = array_combine($nsKeys, $values);

        return $this->getClient()->msetnx($nsValues);
    }

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function save($key, $value, array $tags = array(), $ttl = null)
    {
        $nsKey = $this->applyNamespace($key);

        $this->beginTransaction();

        if ($ttl !== null) {
            $this->getClient()->setex($nsKey, $ttl, $value);
        } else {
            $this->getClient()->set($nsKey, $value);
        }

        if (!empty($tags)) {
            $this->tag($key, $tags);
        }

        return $this->commit();
    }

    /**
     * @param array|mixed[]  $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function saveMultiple($values, array $tags = array(), $ttl = null)
    {
        $keys = array_keys($values);
        $nsKeys = $this->applyNamespace($keys);
        $nsValues = array_combine($nsKeys, $values);

        return $this->getClient()->mset($nsValues);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function delete($key)
    {
        $this->untag($key);

        $nsKey = $this->applyNamespace($key);

        return 1 === $this->getClient()->del($nsKey);
    }

    /**
     * @param array|string[] $keys
     *
     * @return int
     */
    public function deleteMultiple($keys)
    {
        $nsKeys = $this->applyNamespace($keys);

        return count($keys) === $this->getClient()->del($nsKeys);
    }

    /**
     * @param array|string[] $tags
     *
     * @return bool
     */
    public function deleteByTags(array $tags = array())
    {
        $nsTags = $this->applyNamespace($tags, 'tag');
        $keys = call_user_func_array(array($this->getClient(), 'sInter'), $nsTags);

        return empty($keys) ? true : $this->deleteMultiple($keys);
    }

    /**
     * @param string         $key
     * @param array|string[] $tags
     *
     * @return bool
     */
    public function tag($key, array $tags = array())
    {
        $serializedTags = serialize($tags);
        $nsTagsKey = $this->applyNamespace($key, 'tags');
        $this->getClient()->set($nsTagsKey, $serializedTags);

        $nsTags = $this->applyNamespace($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            $this->getClient()->sAdd($nsTag, $key);
        }
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function untag($key)
    {
        $nsTagsKey = $this->applyNamespace($key, 'tags');
        $serializedTags = $this->getClient()->get($nsTagsKey);
        $tags = unserialize($serializedTags);

        $nsTags = $this->applyNamespace($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            $this->getClient()->sRem($nsTag, $key);
        }

        return $this->getClient()->del($nsTagsKey);
    }
}
