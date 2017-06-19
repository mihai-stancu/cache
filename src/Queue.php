<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

use MS\ContainerType\Interfaces\Queue as QueueInterface;

class Queue implements QueueInterface
{
    use Serializable;

    /** @var \Redis */
    protected $redis;

    /** @var NS */
    protected $ns;

    /** @var string */
    protected $name;

    /**
     * @param string $name
     * @param \Redis $redis
     * @param NS     $ns
     */
    public function __construct($name, \Redis $redis, NS $ns = null)
    {
        $this->name = $name;

        $this->redis = $redis;
        $this->ns = $ns ?: new NS();
    }

    /**
     * @param string $ns
     *
     * @return static
     */
    public function changeNS($ns)
    {
        $this->ns->use($ns);

        return $this;
    }

    /**
     * @param mixed[] $values,...
     *
     * @return bool
     */
    public function enqueue($values)
    {
        $nsName = $this->ns->apply($this->name);
        $values = array_map([$this, 'serialize'], func_get_args());

        $args = [$nsName, ['NX']];
        foreach ($values as $value) {
            $args[] = round(microtime(true) * 1000);
            $args[] = $value;
        }

        return call_user_func_array([$this->redis, 'zAdd'], $args);
    }

    /**
     * @param int $count
     *
     * @return mixed|mixed[]
     */
    public function peek($count = 1)
    {
        $nsName = $this->ns->apply($this->name);
        $values = $this->redis->zRange($nsName, 0, $count - 1);
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
        $nsName = $this->ns->apply($this->name);

        $values = $this->redis->zRange($nsName, 0, $count - 1);
        $this->redis->zRemRangeByRank($nsName, 0, $count - 1);
        $values = array_map([$this, 'deserialize'], $values);

        if (func_num_args() === 0) {
            return reset($values);
        }

        return $values;
    }
}
