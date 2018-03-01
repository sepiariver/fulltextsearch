# FullTextSearch
MySQL FULLTEXT search for MODX CMS.

## Properties
(All optional)
- limit = (int) Limit number of query results. Default: 0 (no limit).
- parents = (csv) Specify IDs of parents. Only the children of these Resources will be included. Default ''.
- excludeIds = (csv) Specify IDs of Resources to exclude. Default ''.
- scoreThreshold = (float) Specify relevancy score, below which Resources will not be returned. Default 1.0.
- expandQuery = (boolean) Allow MySQL FULLTEXT query expansion, which matches on related terms. Default true.
- searchParam = (string) The $_REQUEST parameter with the search phrase. Default 'search'.
- outputSeparator = (string) Separate output. The Snippet returns matching Resource IDs, so the default ',' is most commonly used.
- toPlaceholder = (string) Send the output to a placeholder of this key. Default '' (return directly).
- debug = (string) Set to 'dump' for var_dump or 'log' for the MODX error log. Default '' (no debug).

## Example Usage

Default behaviour, sorted by relevancy. If you made this call on the Resource, the ID of which is set as your MODX `error_page`, then any 404s would trigger a search based on the URL.
```
[[!getResources?
    &parents=`-1`
    &resources=`[[!FullTextSearch]]`
    &tpl=`search_results.tpl`
    &sortby=`FIELD(modResource.id,[[!FullTextSearch]])`
    &limit=`0`
]]
```

Don't expand query (more restrictive matching) but lower the scoreThreshold, sorted by latest publishedon date.
```
[[!getResources?
    &parents=`-1`
    &resources=`[[!FullTextSearch? &expandQuery=`0` &scoreThreshold=`0.5`]]`
    &tpl=`search_results.tpl`
    &limit=`0`
]]
```
