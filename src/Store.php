<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

class Store
{
    use Serializable;

    /** @var \Redis|\RedisCluster */
    protected $redis;

    /** @var NS */
    protected $ns;

    /** @var array */
    protected $options;

    /**
     * @param \Redis|\RedisCluster $redis
     * @param NS     $ns
     * @param array  $options
     */
    public function __construct($redis, NS $ns = null, array $options = [])
    {
        $this->redis = $redis;
        $this->ns = $ns ?: new NS();
        $this->options = $options;
    }

    /**
     * @param string $ns
     *
     * @return static
     */
    public function changeNS($ns)
    {
        $this->ns->use($ns);

        return $this;
    }

    /**
     * @param bool $atomic
     * @param bool $buffer
     *
     * @return array
     */
    public function transaction($atomic = null, $buffer = null)
    {
        if (isset($buffer) and $buffer) {
            $this->redis->multi(\Redis::PIPELINE);
        }

        if (isset($atomic) and $atomic) {
            $this->redis->multi(\Redis::MULTI);
        }
    }

    public function commit()
    {
        return $this->redis->exec();
    }

    public function rollback()
    {
        return $this->redis->discard();
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function contains($key)
    {
        $nsKey = $this->ns->apply($key);

        return $this->redis->exists($nsKey);
    }

    /**
     * @param array|string[] $keys
     *
     * @return bool
     */
    public function containsMultiple($keys)
    {
        $nsKeys = $this->ns->apply($keys);

        return count($keys) === $this->redis->exists($nsKeys);
    }

    /**
     * @param string $key
     *
     * @return bool|string
     */
    public function fetch($key)
    {
        $nsKey = $this->ns->apply($key);
        $value = $this->redis->get($nsKey);
        $value = $this->deserialize($value);

        return $value;
    }

    /**
     * @param array|string[] $keys
     *
     * @return array|mixed[]
     */
    public function fetchMultiple(array $keys = [])
    {
        if (empty($keys)) {
            return [];
        }

        $nsKeys = $this->ns->apply($keys);
        $values = $this->redis->mget($nsKeys);
        $values = array_map([$this, 'deserialize'], $values);
        $values = array_combine($keys, $values);

        return $values;
    }

    /**
     * @param array|string[] $tags
     * @param bool           $intersect
     *
     * @return array|mixed[]
     */
    public function fetchByTags(array $tags = [], $intersect = true)
    {
        $tags = $this->ns->flatten($tags);
        $nsTags = $this->ns->apply($tags, 'tag');
        $keys = call_user_func_array([$this->redis, $intersect ? 'sInter' : 'sUnion'], $nsTags);
        $values = empty($keys) ? [] : $this->fetchMultiple($keys);

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
    public function add($key, $value, $ttl = null, array $tags = [])
    {
        $nsKey = $this->ns->apply($key);
        $value = $this->serialize($value);

        $options = ['nx', 'px' => $ttl ? (int) ($ttl * 1000) : null];
        $options = array_filter($options);
        $out = $this->redis->set($nsKey, $value, $options);
        $this->tag($key, $tags);

        return $out;
    }

    /**
     * @param mixed[]        $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function addMultiple($values, $ttl = null, array $tags = [])
    {
        $keys = array_keys($values);
        $nsKeys = $this->ns->apply($keys);

        $values = array_map([$this, 'serialize'], $values);
        $nsValues = array_combine($nsKeys, $values);

        $out = $this->redis->msetnx($nsValues);
        $this->tag($keys, $tags);

        return $out;
    }

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function save($key, $value, $ttl = null, array $tags = [])
    {
        $nsKey = $this->ns->apply($key);
        $value = $this->serialize($value);

        $options = ['px' => $ttl ? (int) ($ttl * 1000) : null];
        $options = array_filter($options);

        $out = $this->redis->set($nsKey, $value, $options);
        $this->tag($key, $tags);

        return $out;
    }

    /**
     * @param array|mixed[]  $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function saveMultiple($values, $ttl = null, array $tags = [])
    {
        $values = (array) $values;
        $keys = array_keys($values);
        $nsKeys = $this->ns->apply($keys);

        $values = array_map([$this, 'serialize'], $values);
        $nsValues = array_combine($nsKeys, $values);

        $out = $this->redis->mset($nsValues);
        $this->tag($keys, $tags);

        return $out;
    }

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function replace($key, $value, $ttl = null, array $tags = [])
    {
        $nsKey = $this->ns->apply($key);
        $value = $this->serialize($value);

        $options = ['xx', 'px' => $ttl ? (int) ($ttl * 1000) : null];
        $options = array_filter($options);
        $out = $this->redis->set($nsKey, $value, $options);
        $this->tag($key, $tags);

        return $out;
    }

    /**
     * @param array|mixed[]  $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function replaceMultiple($values, $ttl = null, array $tags = [])
    {
        $keys = array_keys($values);
        $nsKeys = $this->ns->apply($keys);

        $values = array_map([$this, 'serialize'], $values);
        $nsValues = array_combine($nsKeys, $values);

        if (!$this->containsMultiple($keys)) {
            return false;
        }

        $out = $this->redis->mset($nsValues);
        $this->tag($keys, $tags);

        return $out;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function delete($key)
    {
        $this->untag($key);

        $nsKey = $this->ns->apply($key);

        return 1 === $this->redis->del($nsKey);
    }

    /**
     * @param array|string[] $keys
     *
     * @return int
     */
    public function deleteMultiple($keys)
    {
        $this->untag($keys);

        $nsKeys = $this->ns->apply($keys);

        return count($keys) === $this->redis->del($nsKeys);
    }

    /**
     * @param array|string[] $tags
     * @param bool           $intersect
     *
     * @return bool
     */
    public function deleteByTags(array $tags = [], $intersect = true)
    {
        $tags = $this->ns->flatten($tags);
        $nsTags = $this->ns->apply($tags, 'tag');
        $keys = call_user_func_array([$this->redis, $intersect ? 'sInter' : 'sUnion'], $nsTags);

        return empty($keys) ? true : $this->deleteMultiple($keys);
    }

    /**
     * @param string         $keys
     * @param array|string[] $tags
     *
     * @return bool
     */
    public function tag($keys, array $tags = [])
    {
        if (empty($tags)) {
            return true;
        }

        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $tags = $this->ns->flatten($tags);
        $serializedTags = $this->serialize($tags);
        $nsTagsKeys = $this->ns->apply($keys, 'tags');
        $values = array_combine($nsTagsKeys, array_fill(0, count($nsTagsKeys), $serializedTags));
        $this->redis->mset($values);

        $nsTags = $this->ns->apply($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            call_user_func_array([$this->redis, 'sAdd'], array_merge([$nsTag], $keys));
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
            $keys = [$keys];
        }

        $nsTagsKeys = $this->ns->apply($keys, 'tags');
        $serializedTagsList = $this->redis->mget($nsTagsKeys);
        if (!$serializedTagsList) {
            return false;
        }

        $tagsList = array_map([$this, 'deserialize'], $serializedTagsList);
        $tagsList = array_filter($tagsList);
        if (count($tagsList) < 2) {
            return false;
        }

        $tags = call_user_func_array('array_merge', $tagsList);
        $tags = array_unique((array) $tags);

        $nsTags = $this->ns->apply($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            $this->redis->sRem($nsTag, $keys);
            call_user_func_array([$this->redis, 'sRem'], array_merge([$nsTag], $keys));
        }

        return $this->redis->del($nsTagsKeys);
    }
}
