<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

use MS\ContainerType\Interfaces\Queue as QueueInterface;

final class Queue implements QueueInterface
{
    use Serializable;

    /** @var string */
    private $name;


    /** @var \Redis */
    private $redis;

    /** @var array */
    private $options;


    /**
     * @param string $name
     * @param \Redis $redis
     * @param array  $options
     */
    public function __construct($name, \Redis $redis, array $options = [])
    {
        $this->name = $name;

        $this->redis = $redis;
        $this->options = $options;
    }

    /**
     * @param mixed[] $values,...
     *
     * @return bool
     */
    public function enqueue(...$values)
    {
        $values = array_map([$this, 'serialize'], $values);
        $args = [$this->name, ['NX']];
        foreach ($values as $value) {
            $args[] = round(microtime(true) * 1000);
            $args[] = $value;
        }

        return \call_user_func_array([$this->redis, 'zAdd'], $args);
    }

    /**
     * @param int $count
     *
     * @return mixed|mixed[]
     */
    public function peek($count = 1)
    {
        $values = $this->redis->zRange($this->name, 0, $count - 1);
        $values = array_map([$this, 'deserialize'], $values);

        if (func_num_args() === 0) {
            return reset($values);
        }

        return $values;
    }

    /**
     * @param int $count
     *
     * @return mixed
     */
    public function dequeue($count = 1)
    {
        $values = $this->redis->zRange($this->name, 0, $count - 1);
        $this->redis->zRemRangeByRank($this->name, 0, $count - 1);
        $values = array_map([$this, 'deserialize'], $values);

        if (func_num_args() === 0) {
            return reset($values);
        }

        return $values;
    }
}
