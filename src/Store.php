<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

final class Store
{
    use Serializable;

    /** @var \Redis */
    private $redis;

    /** @var NS */
    private $ns;

    /** @var array */
    private $options;

    /**
     * @param NS     $ns
     * @param \Redis $redis
     * @param array  $options
     */
    public function __construct(NS $ns, \Redis $redis, array $options = [])
    {
        $this->ns = $ns;
        $this->redis = $redis;
        $this->options = $options;
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
        $nsKeys = $this->ns->batchApply($keys);
        $results = array_map([$this->redis, 'exists'], ...$nsKeys);

        return \count($keys) === \array_sum($results);
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
        $nsKeys = $this->ns->batchApply($keys);
        $values = $this->redis->mget($nsKeys);
        $values = array_map([$this, 'deserialize'], $values);
        $values = array_combine($keys, $values);

        return $values;
    }

    /**
     * @param array|string[] $tags
     *
     * @return array|mixed[]
     */
    public function fetchByTags(array $tags = [])
    {
        $tags = $this->ns->flatten($tags);
        $nsTags = $this->ns->batchApply($tags, 'tag');
        $keys = $this->redis->sInter(...$nsTags);
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
        $this->tag([$key], $tags);

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
        $nsKeys = $this->ns->batchApply($keys);

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
        $this->tag([$key], $tags);

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
        $nsKeys = $this->ns->batchApply($keys);

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
        $this->tag([$key], $tags);

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
        $nsKeys = $this->ns->batchApply($keys);

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
        $this->untag([$key]);

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

        $nsKeys = $this->ns->batchApply($keys);

        return \count($keys) === $this->redis->del($nsKeys);
    }

    public function deleteByTags(array $tags = []): bool
    {
        $tags = $this->ns->flatten($tags);
        $nsTags = $this->ns->batchApply($tags, 'tag');
        $keys = $this->redis->sInter(...$nsTags);

        return empty($keys) ? true : $this->deleteMultiple($keys);
    }

    private function tag(array $keys, array $tags = [])
    {
        $tags = $this->ns->flatten($tags);
        $serializedTags = $this->serialize($tags);
        $nsTagsKeys = $this->ns->batchApply($keys, 'tags');
        $values = array_combine($nsTagsKeys, array_fill(0, \count($nsTagsKeys), $serializedTags));
        $this->redis->mset($values);

        $nsTags = $this->ns->batchApply($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            $this->redis->sAdd($nsTag, ...$keys);
        }
    }

    private function untag(array $keys = [])
    {
        $nsTagsKeys = $this->ns->batchApply($keys, 'tags');
        $serializedTagsList = $this->redis->mget($nsTagsKeys);
        if (!$serializedTagsList) {
            return;
        }

        $tagsList = array_map([$this, 'deserialize'], $serializedTagsList);
        $tagsList = array_filter($tagsList);
        if (\count($tagsList) < 2) {
            return;
        }

        $tags = array_merge(...$tagsList);
        $tags = array_unique($tags);

        $nsTags = $this->ns->batchApply($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            $this->redis->sRem($nsTag, ...$keys);
        }

        return $this->redis->del(...$nsTagsKeys);
    }
}
