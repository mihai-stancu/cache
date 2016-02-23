<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle;

abstract class AbstractCache implements Cache, \ArrayAccess
{
    const NAMESPACE_FORMAT = '%1$s/%2$s/%3$s';

    #region Namespaces

    protected $namespace;

    /**
     * @return mixed
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param mixed $namespace
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    /**
     * @param string|string[] $key
     * @param string          $role
     *
     * @return string|string[]
     */
    public function applyNamespace($key, $role = 'value')
    {
        if (is_string($key)) {
            return vsprintf(static::NAMESPACE_FORMAT, array($this->namespace, $role, $key));
        }

        return array_map(array($this, 'applyNamespace'), $key, array_fill(0, count($key), $role));
    }

    /**
     * @param string|string[] $key
     * @param string          $role
     *
     * @return string|string[]
     */
    public function removeNamespace($key, $role = 'value')
    {
        if (is_string($key)) {
            return substr($key, strlen($this->namespace) + strlen($role) + 2);
        }

        return array_map(array($this, 'removeNamespace'), $key, array_fill(0, count($key), $role));
    }

    #endregion

    #region Magic properties

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

    #endregion

    #region ArrayAccess

    /**
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
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
            return $this->fetchByTags($offset);
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
        return $this->delete($offset);
    }

    #endregion
}
