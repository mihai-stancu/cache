<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

final class Lock
{
    /** @var string */
    private $name;

    /** @var string */
    private $secret;


    /** @var \Redis */
    private $redis;


    public function __construct(string $name, ?string $secret, \Redis $redis)
    {
        if ($secret === null) {
            $secret = openssl_random_pseudo_bytes(32);
            $secret = base64_encode($secret);
            $secret = rtrim($secret, '=');
        }

        $this->name = $name;
        $this->secret = $secret;

        $this->redis = $redis;
    }

    public function check(): bool
    {
        return $this->redis->get($this->name) === $this->secret;
    }

    public function acquire(int $ttl = 60, bool $blocking = false): bool
    {
        $options = ['nx', 'px' => $ttl * 1000];
        while (!($result = $this->redis->set($this->name, $this->secret, $options)) and $blocking) {
            usleep(5 * 1000);
        }

        return $result;
    }

    public function release(): bool
    {
        return $this->check() and $this->redis->del($this->name);
    }
}
