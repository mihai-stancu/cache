<?php

/*
 * Copyright (c) 2016 Mihai Stancu <stancu.t.mihai@gmail.com>
 *
 * This source file is subject to the license that is bundled with this source
 * code in the LICENSE.md file.
 */

namespace MS\CacheBundle\HttpCache;

use MS\CacheBundle\Cache;
use MS\CacheBundle\Cache;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

class Store implements StoreInterface
{
    /** @var Cache  */
    protected $cache;

    /** @var  callable */
    protected $keyCallback;

    /** @var  callable */
    protected $tagCallback;

    /**
     * @param Cache $cache
     * @param callable       $keyCallback
     * @param callable       $tagCallback
     */
    public function __construct(Cache $cache, callable $keyCallback = null, callable $tagCallback = null)
    {
        $this->cache = $cache;
        $this->cache->setNamespace('reverse-proxy');

        $this->keyCallback = $keyCallback;
        $this->tagCallback = $tagCallback;
    }

    /**
     * @param Request $request
     *
     * @return string
     */
    public function getKey(Request $request)
    {
        if ($this->keyCallback and $key = $this->keyCallback($request)) {
            return $key;
        }

        return sha1(
            sprintf(
                '%s %s',
                $request->getMethod(),
                $request->getUri()
            )
        );
    }

    /**
     * @param Request $request
     *
     * @return array
     */
    public function getTags(Request $request)
    {
        if ($this->tagCallback and $tags = $this->tagCallback($request)) {
            $this->cache->flattenTags($tags);

            return $tags;
        }

        $tags = array_merge(
            $request->attributes->get('_route_params'),
            $request->query->all()
        );

        $this->cache->flattenTags($tags);

        return $tags;
    }

    /**
     * Locates a cached Response for the Request provided.
     *
     * @param Request $request A Request instance
     *
     * @return Response|null A Response instance, or null if no cache entry was found
     */
    public function lookup(Request $request)
    {
        $key = $this->getKey($request);
        if ($value = $this->cache->fetch($key)) {
            return Response::create($value, 200);
        }

        $tags = $this->getTags($request);
        if ($values = $this->cache->fetchByTags($tags)) {
            return Response::create($value, 200);
        }
    }

    /**
     * Writes a cache entry to the store for the given Request and Response.
     *
     * Existing entries are read and any that match the response are removed. This
     * method calls write with the new list of cache entries.
     *
     * @param Request  $request  A Request instance
     * @param Response $response A Response instance
     *
     * @return string The key under which the response is stored
     */
    public function write(Request $request, Response $response)
    {
        $key = $this->getKey($request);
        $ttl = $response->getMaxAge();
        $tags = $this->getTags($request);

        if ($this->cache->save($key, $response->getContent(), $ttl, $tags)) {
            return $key;
        }
    }

    /**
     * Invalidates all cache entries that match the request.
     *
     * @param Request $request A Request instance
     */
    public function invalidate(Request $request)
    {
    }

    /**
     * Returns whether or not a lock exists.
     *
     * @param Request $request A Request instance
     *
     * @return bool true if lock exists, false otherwise
     */
    public function isLocked(Request $request)
    {
        $key = $this->getKey($request);

        return $this->cache->isLocked($key);
    }

    /**
     * Locks the cache for a given Request.
     *
     * @param Request $request A Request instance
     *
     * @return bool|string true if the lock is acquired, the path to the current lock otherwise
     */
    public function lock(Request $request)
    {
        $key = $this->getKey($request);

        return $this->cache->lock($key, null, false);
    }

    /**
     * Releases the lock for the given Request.
     *
     * @param Request $request A Request instance
     *
     * @return bool False if the lock file does not exist or cannot be unlocked, true otherwise
     */
    public function unlock(Request $request)
    {
        $key = $this->getKey($request);

        return $this->cache->unlock($key);
    }

    /**
     * Purges data for the given URL.
     *
     * @param string $url A URL
     *
     * @return bool true if the URL exists and has been purged, false otherwise
     */
    public function purge($url)
    {
        $request = Request::create($url);
        $key = $this->getKey($request);

        return $this->cache->delete($key);
    }

    /**
     * Cleanups storage.
     */
    public function cleanup()
    {
    }
}
