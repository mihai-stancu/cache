<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

class Lock
{
    /** @var string */
    protected $name;

    /** @var string */
    protected $secret;

    /** @var \Redis|\RedisCluster */
    protected $redis;

    /** @var NS */
    protected $ns;

    /** @var array */
    protected $options;

    /**
     * @param string $name
     * @param string $secret
     * @param \Redis|\RedisCluster $redis
     * @param NS     $ns
     * @param array  $options
     */
    public function __construct($name, $secret, $redis, NS $ns = null, array $options = [])
    {
        if ($secret === null) {
            $secret = openssl_random_pseudo_bytes(32);
            $secret = base64_encode($secret);
            $secret = rtrim($secret, '=');
        }

        $this->name = $name;
        $this->secret = $secret;

        $this->redis = $redis;
        $this->ns = $ns;
        $this->options = $options;
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
     * @return bool
     */
    public function check()
    {
        $nsName = $this->name;

        return $this->redis->get((string) $nsName) === $this->secret;
    }

    /**
     * @param int  $ttl
     * @param bool $blocking
     *
     * @return bool
     */
    public function acquire($ttl = null, $blocking = false)
    {
        $ttl = is_numeric($ttl) ? $ttl : 1;
        $options = ['nx', 'px' => $ttl ? (int) ($ttl * 1000) : null];
        $options = array_filter($options);

        $nsName = $this->ns->apply($this->name);
        while (!($result = $this->redis->set((string) $nsName, $this->secret, $options)) and $blocking) {
            usleep(5 * 1000);
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function release()
    {
        if (!$this->check()) {
            return false;
        }

        $nsName = $this->ns->apply($this->name);
        if (!$this->redis->del($nsName)) {
            return false;
        }

        return true;
    }
}
