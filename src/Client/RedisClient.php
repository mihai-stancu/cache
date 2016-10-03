<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache\Client;

use MS\Cache\NS;

class RedisClient implements Cache
{
    /** @var  \Redis */
    protected $client;

    /** @var NS  */
    protected $ns;

    /** @var array|string[] */
    protected $locks = [];

    /**
     * @param \Redis    $client
     * @param NS $ns
     */
    public function __construct($client, NS $ns = null)
    {
        $this->client = $client;

        $this->ns = $ns ?: new NS();
    }

    public function beginTransaction()
    {
        $this->client->multi(\Redis::PIPELINE);
    }

    public function commit()
    {
        return $this->client->exec();
    }

    public function rollback()
    {
        return $this->client->discard();
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function contains($key)
    {
        $nsKey = $this->ns->apply($key);

        return $this->client->exists($nsKey);
    }

    /**
     * @param array|string[] $keys
     *
     * @return bool
     */
    public function containsMultiple($keys)
    {
        $nsKeys = $this->ns->apply($keys);

        return count($keys) === $this->client->exists($nsKeys);
    }

    /**
     * @param string $key
     *
     * @return bool|string
     */
    public function fetch($key)
    {
        $nsKey = $this->ns->apply($key);
        $serializedValue = $this->client->get($nsKey);
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
        $nsKeys = $this->ns->apply($keys);
        $serializedValues = $this->client->mget($nsKeys);
        $values = array_map(array($this, 'deserialize'), $serializedValues);
        $values = array_combine($keys, $values);

        return $values;
    }

    /**
     * @param array|string[] $tags
     * @param bool           $intersect
     *
     * @return array|mixed[]
     */
    public function fetchByTags(array $tags = array(), $intersect = true)
    {
        $tags = $this->flattenTags($tags);
        $nsTags = $this->ns->apply($tags, 'tag');
        $keys = call_user_func_array(array($this->client, $intersect ? 'sInter' : 'sUnion'), $nsTags);
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
        $nsKey = $this->ns->apply($key);

        $this->beginTransaction();
        $options = array('nx', 'px' => $ttl ? intval($ttl * 1000) : null);
        $options = array_filter($options);
        $this->client->set($nsKey, (string) $value, $options);
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
        $nsKeys = $this->ns->apply($keys);

        $values = array_map(array($this, 'strval'), $values);
        $nsValues = array_combine($nsKeys, $values);

        $this->beginTransaction();
        $this->client->msetnx($nsValues);
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
        $nsKey = $this->ns->apply($key);

        $this->beginTransaction();
        $options = array('px' => $ttl ? intval($ttl * 1000) : null);
        $options = array_filter($options);

        $this->client->set($nsKey, $value, $options);
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
        $nsKeys = $this->ns->apply($keys);

        $values = array_map(array($this, 'strval'), $values);
        $nsValues = array_combine($nsKeys, $values);

        $this->beginTransaction();
        $this->client->mset($nsValues);
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
        $nsKey = $this->ns->apply($key);

        $this->beginTransaction();
        $options = array('xx', 'px' => $ttl ? intval($ttl * 1000) : null);
        $options = array_filter($options);
        $this->client->set($nsKey, (string) $value, $options);
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
        $nsKeys = $this->ns->apply($keys);

        $values = array_map(array($this, 'strval'), $values);
        $nsValues = array_combine($nsKeys, $values);

        if (!$this->containsMultiple($keys)) {
            return false;
        }

        $this->beginTransaction();
        $this->client->mset($nsValues);
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

        $nsKey = $this->ns->apply($key);

        return 1 === $this->client->del($nsKey);
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

        return count($keys) === $this->client->del($nsKeys);
    }

    /**
     * @param array|string[] $tags
     * @param bool           $intersect
     *
     * @return bool
     */
    public function deleteByTags(array $tags = array(), $intersect = true)
    {
        $tags = $this->ns->flatten($tags);
        $nsTags = $this->ns->apply($tags, 'tag');
        $keys = call_user_func_array(array($this->client, $intersect ? 'sInter' : 'sUnion'), $nsTags);

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

        $tags = $this->ns->flatten($tags);
        $nsTagsKeys = $this->ns->apply($keys, 'tags');
        $values = array_combine($nsTagsKeys, array_fill(0, count($nsTagsKeys), json_encode($tags)));
        $this->client->mset($values);

        $nsTags = $this->ns->apply($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            call_user_func_array(array($this->client, 'sAdd'), array_merge(array($nsTag), $keys));
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

        $nsTagsKeys = $this->ns->apply($keys, 'tags');
        $tagsList = $this->client->mget($nsTagsKeys);
        $tagsList = array_map(array($this, 'json_decode'), $tagsList);
        $tags = call_user_func_array('array_merge', $tagsList);
        $tags = array_unique($tags);

        $nsTags = $this->ns->apply($tags, 'tag');
        foreach ($nsTags as $nsTag) {
            $this->client->sRem($nsTag, $keys);
            call_user_func_array(array($this->client, 'sRem'), array_merge(array($nsTag), $keys));
        }

        return $this->client->del($nsTagsKeys);
    }

    /**
     * @param string $name
     * @param string $secret
     *
     * @return bool
     */
    public function isLocked($name, $secret = null)
    {
        $nsName = $this->ns->apply($name, 'lock');

        if ($secret === null and isset($this->locks[$nsName])) {
            $secret = $this->locks[$nsName];
        }

        return $this->client->get($nsName) === $secret;
    }

    /**
     * @param string $name
     * @param string $secret
     * @param int    $ttl
     * @param bool   $blocking
     *
     * @return bool
     */
    public function lock($name, $secret = null, $ttl = null, $blocking = false)
    {
        if ($secret === null) {
            $secret = openssl_random_pseudo_bytes(32);
            $secret = base64_encode($secret);
            $secret = rtrim($secret, '=');
        }

        $ttl = is_numeric($ttl) ? $ttl : 1;

        $nsName = $this->ns->apply($name, 'lock');

        $options = array('nx', 'px' => $ttl ? intval($ttl * 1000) : null);
        $options = array_filter($options);

        while (!($result = $this->client->set($nsName, $secret, $options)) and $blocking) {
            usleep(5 * 1000);
        }

        if ($result) {
            $this->locks[$nsName] = $secret;
        }

        return $result;
    }

    /**
     * @param string $name
     * @param string $secret
     *
     * @return bool
     */
    public function unlock($name, $secret = null)
    {
        $nsName = $this->ns->apply($name, 'lock');

        if (!$this->isLocked($name, $secret)) {
            return false;
        }

        if (!$this->client->del($nsName)) {
            return false;
        }

        unset($this->locks[$nsName]);

        return true;
    }

    /**
     * @param mixed  $value
     * @param string $format
     * @param array  $context
     *
     * @return string
     */
    public function serialize($value, $format = null, array $context = array())
    {
        switch ($format) {
            default:
            case 'json':
                return json_encode($value);

            case 'serialize':
                return serialize($value);
        }
    }

    /**
     * @param string $value
     * @param string $type
     * @param string $format
     * @param array  $context
     *
     * @return mixed
     */
    public function deserialize($value, $type = null, $format = null, array $context = array())
    {
        switch ($format) {
            default:
            case 'json':
                return json_decode($value, true);

            case 'serialize':
                return unserialize($value);
        }
    }
}
