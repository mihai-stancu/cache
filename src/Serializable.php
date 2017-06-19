<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\Cache;

trait Serializable
{
    /**
     * @param mixed $value
     *
     * @return string
     */
    protected function serialize($value)
    {
        return json_encode($value);
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    protected function deserialize($value)
    {
        return json_decode($value, true);
    }
}
