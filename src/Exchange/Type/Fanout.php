<?php

namespace MS\Cache\Exchange\Type;

use MS\Cache\Exchange\Type;

final class Fanout implements Type
{
    /** @var string[] */
    private $keys;

    public function __construct(array $keys)
    {
        $this->keys = $keys;
    }

    public function __invoke($value, $pattern = null): array
    {
        return $this->keys;
    }
}
