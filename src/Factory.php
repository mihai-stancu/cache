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

    /** @var array */
    protected $options;

    /** @var Lock[] */
    protected $locks = [];

    /** @var Queue[] */
    protected $queues = [];

    /** @var Store */
    protected $store;

    /**
     * @param \Redis $redis
     * @param NS     $ns
     * @param array  $options
     */
    public function __construct(\Redis $redis, NS $ns = null, array $options = [])
    {
        $this->redis = $redis;
        $this->ns = $ns ?: new NS();
        $this->options = $options;
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

        return $this->locks[$name] = new Lock($name, $secret, $this->redis, $this->ns, $this->options);
    }

    /**
     * @param string $name
     * @param int    $multi
     * @param string $type
     * @return Queue
     */
    public function queue($name, $multi = 0, $type = 'simple')
    {
        if (isset($this->queues[$name])) {
            return $this->queues[$name];
        }

        if ($type === 'scoreboard') {
            return $this->queues[$name] = new ScoreboardQueue($name, $this->redis, $this->ns, $this->options);
        }

        if ($multi === 0) {
            return $this->queues[$name] = new Queue($name, $this->redis, $this->ns, $this->options);
        }

        return $this->queues[$name] = new MultiQueue($name, $multi, $this->redis, $this->ns, $this->options);
    }

    /**
     * @return Store
     */
    public function store()
    {
        if (isset($this->store)) {
            return $this->store;
        }

        return $this->store = new Store($this->redis, $this->ns, $this->options);
    }
}
