<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle;

trait Serializer
{
    /**
     * @param mixed  $value
     * @param string $format
     * @param array  $context
     *
     * @return string
     */
    public function serialize($value, $format = null, array $context = array())
    {
        switch ($format) {
            case 'json':
                return json_encode($value);

            default:
            case 'serialize':
                return serialize($value);
        }
    }

    /**
     * @param string $value
     * @param string $type
     * @param string $format
     * @param array  $context
     *
     * @return mixed
     */
    public function deserialize($value, $type = null, $format = null, array $context = array())
    {
        switch ($format) {
            case 'json':
                return json_decode($value, true);

            default:
            case 'serialize':
                return unserialize($value);
        }
    }
}
