<?php

namespace MS\Cache\Exchange\Type;

use MS\Cache\Exchange\Type;

final class Balanced implements Type
{
    /** @var string[] */
    private $keys;

    public function __construct($size)
    {
        $this->keys = range(0, $size-1);
    }

    public function __invoke($value, $pattern = null): array
    {
        $hash = sha1((string) $value);
        $hash = substr($hash, 0, 15);
        $hash = hexdec($hash);
        $pos = $hash % \count($this->keys);

        return [$this->keys[$pos]];
    }
}
