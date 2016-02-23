MSCacheBundle
================================================================================

A Cache system which allows cache items to be associated to tags. When saving a cache item you can also supply its associated tags.

Operations based on associated tags are:

- `findByTags($tags)`: the item must be associated with all specified tags;
- `deleteByTags($tags)`: the item must be associated with all specified tags;

The implementation leverages the `SET` data type provided by `Redis` and the [set intersection](http://redis.io/commands/SINTER) command.
