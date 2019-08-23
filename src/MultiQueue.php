<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

class MultiQueue extends Queue
{
    /** @var int */
    protected $count = 1;

    /**
     * @param string $name
     * @param int    $count
     * @param \Redis|\RedisCluster $redis
     * @param NS     $ns
     * @param array  $options
     */
    public function __construct($name, $count, $redis, NS $ns = null, array $options = [])
    {
        $this->name = $name;
        $this->count = $count;

        parent::__construct($name, $redis, $ns, $options);
    }

    /**
     * @param mixed[] $values
     *
     * @return int
     */
    public function enqueue($values)
    {
        $valuesPerBin = [];
        foreach (func_get_args() as $value) {
            $hash = sha1((string) $value);
            $hash = substr($hash, 0, 15);
            $hash = hexdec($hash);
            $i = $hash % $this->count;
            $valuesPerBin[$i][] = $value;
        }

        $count = 0;
        $name = $this->name;
        foreach ($valuesPerBin as $i => $values) {
            $this->name = $name.'_'.$i;
            $count += parent::enqueue(...$values);
        }
        $this->name = $name;

        return $count;
    }

    /**
     * @param int $count
     *
     * @return mixed|mixed[]
     */
    public function peek($count = 1)
    {
        $values = [];
        $countPerBin = ceil($count / $this->count);
        $name = $this->name;
        for ($i = 0; $i < $this->count; ++$i) {
            $countPerBin = min($countPerBin, $count - count($values));
            $this->name = $name.'_'.$i;
            $values = array_merge($values, parent::peek($countPerBin));
        }
        $this->name = $name;

        return $values;
    }

    /**
     * @param int $count
     *
     * @return mixed|mixed[]
     */
    public function dequeue($count = 1)
    {
        $values = [];
        $countPerBin = ceil($count / $this->count);
        $name = $this->name;
        for ($i = 0; $i < $this->count; ++$i) {
            $countPerBin = min($countPerBin, $count - count($values));
            $this->name = $name.'_'.$i;
            $values = array_merge($values, parent::dequeue($countPerBin));
        }
        $this->name = $name;

        return $values;
    }
}
