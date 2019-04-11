<?php

namespace MS\Cache\Exchange;

interface Type
{
    public function __invoke($value, $pattern = null): array;
}
