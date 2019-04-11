<?php

namespace MS\Cache\Exchange\Type;

use MS\Cache\Exchange\Type;

final class Direct implements Type
{
    public function __invoke($value, $pattern = null): array
    {
        return [$pattern];
    }
}
