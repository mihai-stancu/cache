<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

/**
 * @internal
 */
final class NS
{
    /** @var string */
    private $value;

    /** @var string */
    private $format = '%1$s:%2$s:%3$s';


    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function apply(string $key, string $role = 'value'): string
    {
        return \sprintf($this->format, $this->value, $role, $key);
    }

    public function batchApply(array $keys, string $role = 'value'): array
    {
        $nsKeys = [];
        foreach ($keys as $key) {
            $nsKeys[] = $this->apply($key, $role);
        }

        return $nsKeys;
    }

    /**
     * @param array|string[] $input
     * @param string         $base
     *
     * @return array
     */
    public function flatten($input = [], $base = '')
    {
        $output = [];
        foreach ((array) $input as $key => $value) {
            switch (true) {
                case is_int($key) and (null === $value or is_scalar($value)):
                    $prefix = ($base ? $base.'=' : '');
                    $value = is_string($value) ? $value : var_export($value, true);
                    $output[] = $prefix.$value;
                    break;
                case is_int($key) and (is_array($value) or is_object($value)):
                    $prefix = ($base ? $base : '');
                    $output = array_merge($output, $this->flatten((array) $value, $prefix));
                    break;
                case is_string($key) and (null === $value or is_scalar($value)):
                    $prefix = ($base ? $base.':'.$key.'=' : $key.'=');
                    $value = is_string($value) ? $value : var_export($value, true);
                    $output[] = $prefix.$value;
                    break;
                case is_string($key) and (is_array($value) or is_object($value)):
                    $prefix = ($base ? $base.':'.$key : $key);
                    $output = array_merge($output, $this->flatten((array) $value, $prefix));
                    break;
            }
        }

        ksort($output);

        return array_unique($output);
    }
}
