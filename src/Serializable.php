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
        $serializer = $this->options['serializer'];
        $arguments = array_merge([$value], $this->options['serializer_arguments'] ?? []);

        return call_user_func_array($serializer, $arguments);
    }

    /**
     * @param string $value
     *
     * @return mixed
     */
    protected function deserialize($value)
    {

        $deserializer = $this->options['deserializer'];
        $arguments = array_merge([$value], $this->options['deserializer_arguments'] ?? []);

        return call_user_func_array($deserializer, $arguments);
    }
}
