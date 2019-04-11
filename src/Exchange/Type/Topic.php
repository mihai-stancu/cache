<?php

namespace MS\Cache\Exchange\Type;

use MS\Cache\Exchange\Type;

final class Topic implements Type
{
    /** @var string[] */
    private $keys;

    public function __construct(array $keys)
    {
        $this->keys = $keys;
    }

    public function __invoke($value, $pattern = null): array
    {
        $keys = [];
        foreach ($this->keys as $candidate) {
            preg_match('/^'.$pattern.'$/', $candidate, $matches);
            $keys[] = $matches[0] ?? null;
        }

        return array_filter($keys);
    }
}
