<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle;

interface Cache
{
    /**
     * @return object
     */
    public function getClient();

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
     *
     * @return array|mixed[]
     */
    public function fetchByTags(array $tags = array());

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function add($key, $value, array $tags = array(), $ttl = null);

    /**
     * @param mixed[]        $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function addMultiple($values, array $tags = array(), $ttl = null);

    /**
     * @param string         $key
     * @param mixed          $value
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function save($key, $value, array $tags = array(), $ttl = null);

    /**
     * @param array|mixed[]  $values
     * @param array|string[] $tags
     * @param int            $ttl
     *
     * @return bool
     */
    public function saveMultiple($values, array $tags = array(), $ttl = null);

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
     *
     * @return bool
     */
    public function deleteByTags(array $tags = array());
}
