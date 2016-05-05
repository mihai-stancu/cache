<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle\Client;

use MS\CacheBundle\Accessible\ArrayAccessible;
use MS\CacheBundle\Accessible\PropertyAccessible;
use MS\CacheBundle\Cache;
use MS\CacheBundle\Namespacing;
use MS\CacheBundle\Serializer;
use Predis\Client as Predis;

class RedisClient implements Cache, \ArrayAccess
{
    use ArrayAccessible;
    use PropertyAccessible;

    use Namespacing;
    use Serializer;

    /**
     * @param \Redis|Predis $client
     * @param array         $namespacing
     */
    public function __construct($client, array $namespacing = array())
    {
        $this->client = $client;

        if (!empty($namespacing)) {
            $this->namespacing = $namespacing;
        }
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
        $this->getClient()->multi(\Redis::PIPELINE);
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
        $nsKey = $this->applyNamespace($key);
        $serializedValue = $this->getClient()->get($nsKey);
        $value = $this->deserialize($serializedValue);

        return $value;
    }

    /**
     * @param array|string[] $keys
     *
     * @return array|mixed[]
     */
    public function fetchMultiple(array $keys = array())
    {
        $nsKeys = $this->applyNamespace($keys);
        $serializedValues = $this->getClient()->mget($nsKeys);
        $values = array_map(array($this, 'deserialize'), $serializedValues);
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
        $tags = $this->flattenTags($tags);
        $nsTags = $this->applyNamespace($tags, 'tag');
        $keys = call_user_func_array(array($this->getClient(), 'sInter'), $nsTags);
        $values = empty($keys) ? array() : $this->fetchMultiple($keys);

        return (array) $values;
    }

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function add($key, $value, $ttl = null, array $tags = array())
    {
        $nsKey = $this->applyNamespace($key);
        $serializedValue = $this->serialize($value);

        $this->beginTransaction();
        $options = array('nx', 'px' => $ttl ? intval($ttl * 1000) : null);
        $this->getClient()->set($nsKey, $serializedValue, $options);
        $this->tag($key, $tags);

        return $this->commit();
    }

    /**
     * @param mixed[]        $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function addMultiple($values, $ttl = null, array $tags = array())
    {
        $keys = array_keys($values);
        $nsKeys = $this->applyNamespace($keys);

        $serializedValues = array_map(array($this, 'serialize'), $values);
        $nsValues = array_combine($nsKeys, $serializedValues);

        $this->beginTransaction();
        $this->getClient()->msetnx($nsValues);
        $this->tag($keys, $tags);

        return $this->commit();
    }

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function save($key, $value, $ttl = null, array $tags = array())
    {
        $nsKey = $this->applyNamespace($key);
        $serializedValue = $this->serialize($value);

        $this->beginTransaction();
        $options = array('px' => $ttl ? intval($ttl * 1000) : null);

        $this->getClient()->set($nsKey, $serializedValue, $options);
        $this->tag($key, $tags);

        return $this->commit();
    }

    /**
     * @param array|mixed[]  $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function saveMultiple($values, $ttl = null, array $tags = array())
    {
        $keys = array_keys($values);
        $nsKeys = $this->applyNamespace($keys);

        $serializedValues = array_map(array($this, 'serialize'), $values);
        $nsValues = array_combine($nsKeys, $serializedValues);

        $this->beginTransaction();
        $this->getClient()->mset($nsValues);
        $this->tag($keys, $tags);

        return $this->commit();
    }

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function replace($key, $value, $ttl = null, array $tags = array())
    {
        $nsKey = $this->applyNamespace($key);
        $serializedValue = $this->serialize($value);

        $this->beginTransaction();
        $options = array('xx', 'px' => $ttl ? intval($ttl * 1000) : null);
        $this->getClient()->set($nsKey, $serializedValue, $options);
        $this->tag($key, $tags);

        return $this->commit();
    }

    /**
     * @param array|mixed[]  $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function replaceMultiple($values, $ttl = null, array $tags = array())
    {
        $keys = array_keys($values);
        $nsKeys = $this->applyNamespace($keys);

        $serializedValues = array_map(array($this, 'serialize'), $values);
        $nsValues = array_combine($nsKeys, $serializedValues);

        if (!$this->containsMultiple($keys)) {
            return false;
        }

        $this->beginTransaction();
        $this->getClient()->mset($nsValues);
        $this->tag($keys, $tags);

        return $this->commit();
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
        $this->untag($keys);

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
        $tags = $this->flattenTags($tags);
        $nsTags = $this->applyNamespace($tags, 'tag');
        $keys = call_user_func_array(array($this->getClient(), 'sInter'), $nsTags);

        return empty($keys) ? true : $this->deleteMultiple($keys);
    }

    /**
     * @param string         $keys
     * @param array|string[] $tags
     *
     * @return bool
     */
    public function tag($keys, array $tags = array())
    {
        if (empty($tags)) {
            return true;
        }

        if (!is_array($keys)) {
            $keys = array($keys);
        }

        $tags = $this->flattenTags($tags);
        $serializedTags = $this->serialize($tags);
        $nsTagsKeys = $this->applyNamespace($keys, 'tags');
        $values = array_combine($nsTagsKeys, array_fill(0, count($nsTagsKeys), $serializedTags));
        $this->getClient()->mset($values);

        $nsTags = $this->applyNamespace($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            call_user_func_array(array($this->getClient(), 'sAdd'), array_merge(array($nsTag), $keys));
        }
    }

    /**
     * @param string $keys
     *
     * @return bool
     */
    public function untag($keys)
    {
        if (!is_array($keys)) {
            $keys = array($keys);
        }

        $nsTagsKeys = $this->applyNamespace($keys, 'tags');
        $serializedTagsList = $this->getClient()->mget($nsTagsKeys);
        $tagsList = array_map(array($this, 'deserialize'), $serializedTagsList);
        $tags = call_user_func_array('array_merge', $tagsList);
        $tags = array_unique($tags);

        $nsTags = $this->applyNamespace($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            $this->getClient()->sRem($nsTag, $keys);
            call_user_func_array(array($this->getClient(), 'sRem'), array_merge(array($nsTag), $keys));
        }

        return $this->getClient()->del($nsTagsKeys);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function isLocked($key)
    {
        $nsKey = $this->applyNamespace($key, 'lock');

        return (bool) $this->getClient()->get($nsKey);
    }

    /**
     * @param string $key
     * @param int    $ttl
     * @param bool   $blocking
     *
     * @return bool
     */
    public function lock($key, $ttl = null, $blocking = false)
    {
        $ttl = is_numeric($ttl) ? $ttl : 1;

        $nsKey = $this->applyNamespace($key, 'lock');

        $value = openssl_random_pseudo_bytes(32);
        $options = array('nx', 'px' => $ttl ? intval($ttl * 1000) : null);

        while (!($result = $this->getClient()->set($nsKey, $value, $options)) and $blocking) {
            usleep(5 * 1000);
        }

        return $result;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function unlock($key)
    {
        $nsKey = $this->applyNamespace($key, 'lock');

        return (bool) $this->getClient()->del($nsKey);
    }

    public function getStats()
    {
    }
}
