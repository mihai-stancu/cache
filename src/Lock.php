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
    /** @var \Redis */
    protected $redis;

    /** @var NS */
    protected $ns;

    /** @var string */
    protected $name;

    /** @var string */
    protected $secret;

    /**
     * @param string $name
     * @param string $secret
     * @param \Redis $redis
     * @param NS     $ns
     */
    public function __construct($name, $secret, \Redis $redis, NS $ns = null)
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
     * @param string $NS
     */
    public function changeNS($NS)
    {
        $this->ns->use($NS);
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
