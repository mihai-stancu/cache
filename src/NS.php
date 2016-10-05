<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

class NS
{
    /** @var  string */
    protected $value;

    /**
     * @var array
     */
    protected $config = array(
        'format' => '%1$s.%2$s/%3$s',

        'roles' => array(
            'lock' => 'lock',
            'tag' => 'tag',
            'tags' => 'tags',
            'value' => 'value',
        ),
    );

    /**
     * @param string $value
     * @param array  $config
     */
    public function __construct($value = null, array $config = [])
    {
        $this->value = $value;
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function get()
    {
        return $this->value;
    }

    /**
     * @param string $value
     */
    public function set($value)
    {
        $this->value = $value;
    }

    /**
     * @param string|string[] $key
     * @param string          $role
     *
     * @return string|string[]
     */
    public function apply($key, $role = 'value')
    {
        $role = $this->config['roles'][$role];

        if (is_string($key)) {
            return vsprintf($this->config['format'], array($this->value, $role, $key));
        }

        return array_map(array($this, 'apply'), $key, array_fill(0, count($key), $role));
    }

    /**
     * @param string|string[] $key
     * @param string          $role
     *
     * @return string|string[]
     */
    public function remove($key, $role = null)
    {
        $role = $this->config['roles'][$role];

        if (is_string($key)) {
            return substr($key, strlen($this->value) + strlen($role) + 2);
        }

        return array_map(array($this, 'remove'), $key, array_fill(0, count($key), $role));
    }

    /**
     * @param array|string[] $input
     * @param string         $base
     *
     * @return array
     */
    public function flatten($input = array(), $base = '')
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
                    $output = array_merge($output, $this->flatten((array) $value, $prefix));
                    break;

                case is_string($key) and (is_null($value) or is_scalar($value)):
                    $prefix = ($base ? $base.'.'.$key.':' : $key.':');
                    $value = is_string($value) ? $value : var_export($value, true);
                    $output[] = $prefix.$value;
                    break;

                case is_string($key) and (is_array($value) or is_object($value)):
                    $prefix = ($base ? $base.'.'.$key : $key);
                    $output = array_merge($output, $this->flatten((array) $value, $prefix));
                    break;
            }
        }

        ksort($output);

        return array_unique($output);
    }
}
