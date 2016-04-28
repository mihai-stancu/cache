<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle;

trait Namespacing
{
    protected $namespacing = array(
        'format' => '%1$s.%2$s/%3$s',

        'roles' => array(
            'lock' => 'lock',
            'tag' => 'tag',
            'tags' => 'tags',
            'value' => 'value',
        ),
    );

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
        $role = $this->namespacing['roles'][$role];

        if (is_string($key)) {
            return vsprintf($this->namespacing['format'], array($this->namespace, $role, $key));
        }

        return array_map(array($this, 'applyNamespace'), $key, array_fill(0, count($key), $role));
    }

    /**
     * @param string|string[] $key
     * @param string          $role
     *
     * @return string|string[]
     */
    public function removeNamespace($key, $role = null)
    {
        $role = $this->namespacing['roles'][$role];

        if (is_string($key)) {
            return substr($key, strlen($this->namespace) + strlen($role) + 2);
        }

        return array_map(array($this, 'removeNamespace'), $key, array_fill(0, count($key), $role));
    }

    /**
     * @param array|string[] $input
     * @param string         $base
     *
     * @return array
     */
    public function flattenTags($input = array(), $base = '')
    {
        $output = array();
        foreach ((array) $input as $key => $value) {
            switch (true) {
                case is_int($key) and (is_null($value) or is_scalar($value)):
                    $prefix = ($base ? $base.':' : '');
                    $value = is_string($value) ? $value : var_export($value, true);
                    $output[] = $prefix.$value;
                    break;

                case is_int($key) and (is_array($value) or is_object($value)):
                    $prefix = ($base ? $base : '');
                    $output = array_merge($output, $this->flattenTags((array) $value, $prefix));
                    break;

                case is_string($key) and (is_null($value) or is_scalar($value)):
                    $prefix = ($base ? $base.'.'.$key.':' : $key.':');
                    $value = is_string($value) ? $value : var_export($value, true);
                    $output[] = $prefix.$value;
                    break;

                case is_string($key) and (is_array($value) or is_object($value)):
                    $prefix = ($base ? $base.'.'.$key : $key);
                    $output = array_merge($output, $this->flattenTags((array) $value, $prefix));
                    break;
            }
        }

        ksort($output);

        return array_unique($output);
    }
}
