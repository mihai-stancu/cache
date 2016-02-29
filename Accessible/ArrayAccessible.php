<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle\Accessible;

/**
 * @method bool          contains($key)
 * @method bool          containsMultiple(array $keys)
 * @method mixed         fetch($key)
 * @method array|mixed[] fetchMultiple(array $keys)
 * @method bool          add($key, $value, $ttl = null, $tags = array())
 * @method bool          save($key, $value, $ttl = null, $tags = array())
 * @method bool          replace($key, $value, $ttl = null, $tags = array())
 * @method bool          delete($key)
 * @method bool          deleteMultiple(array $keys)
 */
trait ArrayAccessible
{
    /**
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        if (is_array($offset)) {
            return $this->containsMultiple($offset);
        }

        return $this->contains($offset);
    }

    /**
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if (is_array($offset)) {
            return $this->fetchMultiple($offset);
        }

        return $this->fetch($offset);
    }

    /**
     * @param string $offset
     * @param string $value
     *
     * @return bool
     */
    public function offsetSet($offset, $value)
    {
        if ($offset === null) {
            return $this->add($offset, $value);
        }

        return $this->save($offset, $value);
    }

    /**
     * @param string $offset
     *
     * @return bool
     */
    public function offsetUnset($offset)
    {
        if (is_array($offset)) {
            return $this->deleteMultiple($offset);
        }

        return $this->delete($offset);
    }
}
