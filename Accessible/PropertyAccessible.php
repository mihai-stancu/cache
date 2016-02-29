<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle\Accessible;

/**
 * @method bool  contains($key)
 * @method mixed fetch($key)
 * @method bool  add($key, $value, $ttl = null, $tags = array())
 * @method bool  save($key, $value, $ttl = null, $tags = array())
 * @method bool  replace($key, $value, $ttl = null, $tags = array())
 * @method bool  delete($key)
 */
trait PropertyAccessible
{
    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset($name)
    {
        return $this->contains($name);
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        return $this->fetch($name);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return bool
     */
    public function __set($name, $value)
    {
        return $this->save($name, $value);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __unset($name)
    {
        return $this->delete($name);
    }
}
