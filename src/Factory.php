<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

class Factory
{
    /** @var \Redis */
    protected $redis;

    /** @var NS */
    protected $ns;

    /** @var Lock[] */
    protected $locks = [];

    /** @var Queue[] */
    protected $queues = [];

    /** @var Store */
    protected $store;

    /**
     * @param \Redis $redis
     * @param NS     $ns
     */
    public function __construct(\Redis $redis, NS $ns = null)
    {
        $this->redis = $redis;
        $this->ns = $ns ?: new NS();
    }

    /**
     * @param string $name
     * @param string $secret
     *
     * @return Lock
     */
    public function lock($name, $secret = null)
    {
        if (isset($this->locks[$name])) {
            return $this->locks[$name];
        }

        return $this->locks[$name] = new Lock($name, $secret, $this->redis, $this->ns);
    }

    /**
     * @param string $name
     * @param int    $multi
     *
     * @return Queue
     */
    public function queue($name, $multi = 0)
    {
        if (isset($this->queues[$name])) {
            return $this->queues[$name];
        }

        if ($multi === 0) {
            return $this->queues[$name] = new Queue($name, $this->redis, $this->ns);
        }

        return $this->queues[$name] = new MultiQueue($name, $multi, $this->redis, $this->ns);
    }

    /**
     * @return Store
     */
    public function store()
    {
        if (isset($this->store)) {
            return $this->store;
        }

        return $this->store = new Store($this->redis, $this->ns);
    }
}
