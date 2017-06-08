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
        $args = array_merge([$nsName], $values);

        return call_user_func_array([$this->redis, 'sAdd'], $args);
    }

    /**
     * @param int $count
     *
     * @return mixed|mixed[]
     */
    public function peek($count = 1)
    {
        $nsName = $this->ns->apply($this->name);
        $values = $this->redis->sRandMember($nsName, $count);
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

        $values = [];
        while ($count-- > 0) {
            $values[] = $this->redis->sPop($nsName);
        }

        $values = array_unique($values);
        $values = array_filter($values);
        $values = array_map([$this, 'deserialize'], $values);

        if (func_num_args() === 0) {
            return reset($values);
        }

        return $values;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    protected function serialize($value)
    {
        return json_encode($value);
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    protected function deserialize($value)
    {
        return json_decode($value, true);
    }
}
