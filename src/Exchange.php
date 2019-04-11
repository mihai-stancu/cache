<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

use MS\Cache\Exchange\Type;

final class Exchange
{
    /** @var string */
    private $name;

    /** @var Type */
    private $type;


    /** @var Factory */
    private $factory;


    /**
     * @param string   $name
     * @param string   $type
     * @param array    $args
     * @param Factory  $factory
     */
    public function __construct(string $name, string $type, array $args, Factory $factory)
    {
        $class = class_exists($type) ? $type : 'MS\\Cache\\Exchange\\Type\\'.ucfirst($type);
        $type = new $class(...$args);

        $this->name = $name;
        $this->type = $type;

        $this->factory = $factory;
    }

    /**
     * @param mixed    $value
     * @param string[] $pattern
     *
     * @return int
     */
    public function publish($value, $pattern = null)
    {
        $keys = \call_user_func($this->type, $value, $pattern);
        $values = array_fill_keys($keys, $value);

        $count = 0;
        foreach ($values as $k => $v) {
            $count += $this->queue($k)->enqueue($v);
        }

        return $count;
    }

    public function queue(string $key): Queue
    {
        return $this->factory->queue($this->name, $key);
    }
}
