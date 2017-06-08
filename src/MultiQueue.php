<?php

namespace MS\Cache;

class MultiQueue extends Queue
{
    /** @var int */
    protected $count = 1;

    /**
     * @param string  $name
     * @param int     $count
     * @param \Redis  $redis
     * @param NS      $ns
     */
    public function __construct($name, $count, \Redis $redis, NS $ns = null)
    {
        $this->name    = $name;
        $this->count   = $count;

        parent::__construct($name, $redis, $ns);
    }

    /**
     * @param mixed[] $values
     *
     * @return int
     */
    public function enqueue($values)
    {
        $valuesPerBin = [];
        foreach (func_get_args() as $value) {
            $hash = sha1((string)$value);
            $hash = substr($hash, 0, 4);
            $hash = hexdec($hash);

            $i = $hash % $this->count;

            $valuesPerBin[$i][] = $value;
        }

        $count = 0;
        $name = $this->name;
        foreach ($valuesPerBin as $i => $values) {
            $this->name = $name.'_'.$i;
            $count += parent::enqueue(...$values);
        }
        $this->name = $name;

        return $count;
    }


    /**
     * @param int $count
     *
     * @return mixed|mixed[]
     */
    public function peek($count = 1)
    {
        $values = [];
        $countPerBin = ceil($count/$this->count);
        $name = $this->name;
        for ($i = 0; $i < $this->count; $i++) {
            $countPerBin = min($countPerBin, $count - count($values));
            $this->name = $name.'_'.$i;
            $values = array_merge($values, parent::peek($countPerBin));
        }
        $this->name = $name;

        return $values;
    }

    /**
     * @param int $count
     *
     * @return mixed|mixed[]
     */
    public function dequeue($count = 1)
    {
        $values = [];
        $countPerBin = ceil($count/$this->count);
        $name = $this->name;
        for ($i = 0; $i < $this->count; $i++) {
            $countPerBin = min($countPerBin, $count - count($values));
            $this->name = $name.'_'.$i;
            $values = array_merge($values, parent::dequeue($countPerBin));
        }
        $this->name = $name;

        return $values;
    }
}
