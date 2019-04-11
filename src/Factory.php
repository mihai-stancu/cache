<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

final class Factory
{
    /** @var \Redis */
    private $redis;

    /** @var array */
    private $options;


    /** @var Lock[] */
    private $locks = [];

    /** @var Queue[] */
    private $queues = [];

    /** @var Exchange[] */
    private $exchanges = [];

    /** @var Store[] */
    private $stores = [];


    public function __construct(\Redis $redis, array $options = [])
    {
        $this->redis = $redis;
        $this->options = $options;
    }

    public function lock(string $ns, string $name, $secret = null): Lock
    {
        if (isset($this->locks[$name])) {
            return $this->locks[$name];
        }

        $name = (new NS($ns))->apply($name, 'lock');

        return $this->locks[$name] = new Lock($name, $secret, $this->redis);
    }

    public function queue(string $ns, string $name): Queue
    {
        if (isset($this->queues[$name])) {
            return $this->queues[$name];
        }

        $name = (new NS($ns))->apply($name, 'queue');

        return $this->queues[$name] = new Queue($name, $this->redis, $this->options);
    }

    public function exchange(string $ns, string $name, string $type, array $args = []): Exchange
    {
        if (isset($this->exchanges[$name])) {
            return $this->exchanges[$name];
        }

        $name = (new NS($ns))->apply($name, 'exchange');

        return $this->exchanges[$name] = new Exchange($name, $type, $args, $this);
    }

    public function store(string $ns): Store
    {
        if (isset($this->stores[$ns])) {
            return $this->stores[$ns];
        }

        return $this->stores[$ns] = new Store(new NS($ns), $this->redis, $this->options);
    }
}
