<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

interface Cache
{
    public function beginTransaction();

    public function commit();

    public function rollback();

    /**
     * @param string $key
     *
     * @return bool
     */
    public function contains($key);

    /**
     * @param array|string[] $keys
     *
     * @return bool
     */
    public function containsMultiple($keys);

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function fetch($key);

    /**
     * @param array|string[] $keys
     *
     * @return array|mixed[]
     */
    public function fetchMultiple(array $keys = array());

    /**
     * @param array|string[] $tags
     * @param bool           $intersect
     *
     * @return array|mixed[]
     */
    public function fetchByTags(array $tags = array(), $intersect = true);

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function add($key, $value, $ttl = null, array $tags = array());

    /**
     * @param mixed[]        $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function addMultiple($values, $ttl = null, array $tags = array());

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function save($key, $value, $ttl = null, array $tags = array());

    /**
     * @param array|mixed[]  $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function saveMultiple($values, $ttl = null, array $tags = array());

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function replace($key, $value, $ttl = null, array $tags = array());

    /**
     * @param array|mixed[]  $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function replaceMultiple($values, $ttl = null, array $tags = array());

    /**
     * @param string $key
     *
     * @return bool
     */
    public function delete($key);

    /**
     * @param array|string[] $keys
     *
     * @return bool
     */
    public function deleteMultiple($keys);

    /**
     * @param array|string[] $tags
     * @param bool           $intersect
     *
     * @return bool
     */
    public function deleteByTags(array $tags = array(), $intersect = true);

    /**
     * @param array|string[] $keys
     * @param array|string[] $tags
     *
     * @return bool
     */
    public function tag($keys, array $tags = array());

    /**
     * @param array|string[] $keys
     *
     * @return bool
     */
    public function untag($keys);

    /**
     * @param string $name
     * @param string $secret
     *
     * @return bool
     */
    public function isLocked($name, $secret = null);

    /**
     * @param string $name
     * @param string $secret
     * @param int    $ttl
     * @param bool   $blocking
     *
     * @return bool
     */
    public function lock($name, $secret = null, $ttl = null, $blocking = false);

    /**
     * @param string $name
     * @param string $secret
     *
     * @return bool
     */
    public function unlock($name, $secret = null);
}
