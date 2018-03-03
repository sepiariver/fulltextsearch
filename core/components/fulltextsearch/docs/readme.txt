# FullTextSearch
_MySQL FULLTEXT search for MODX CMS._

MySQL is not a search engine. For full-fledged, enterprise-ready search solutions, MODX CMS supports Solr and Elasticsearch with the well-adopted SimpleSearch Extra. This is the recommended approach for the sites that have sophisticated search requirements and extreme scalability requirements. SimpleSearch represents a lot of community contribution and the work of amazing developers like Jan Peca. FullTextSearch is not a 'competitor', rather something a little different.

## Why?
For "simple" implementations, SimpleSearch uses relatively limited 'LIKE' queries. AdvSearch relies on the Zend Search library and is arguably more complex to implement. Both of those packages do a lot of work with regards to templating the output, and allowing for configurability, yet MODX users continue to implement custom search solutions.

It seems the most common need, that is not addressed with the above Extras, is the ability to customize a search index's content: "Include the rendered output of this TV, include that Resource field, but not this one, exclude this search term, etc..."

FullTextSearch is meant to fill the gap. It builds a custom search index in a dedicated table, based on configuration of the FullTextSearchIndex Plugin. This table has a FULLTEXT index that facilitates high-performance search queries, with features like exclusion of terms ("cars -ford"), stop-words and relevancy scores based on the built-in MySQL algorithm.

FullTextSearch defers to listing Snippets like getResources or pdoResources for templating power, and further filtering if required. The FullTextSearch Snippet returns a comma-separated list of Resource IDs, to pass to the `resources` property of the above. The additional query does not, in and of itself, add a lot of overhead, because the `MATCH ... AGAINST` statements executed by FullTextSearch are designed to use the FULLTEXT index in a performant way.

FullTextSearch is about as easy to set up as SimpleSearch, has no external dependencies, and provides improved performance and features that may fit your use case very well. Use it to easily power "smart" 404 pages.

## Why not?
MySQL's FULLTEXT search prefers wide vocabularies--what you would commonly see on a blog or a marketing site with many pages. The performance benefits over standard 'LIKE' queries disappear for small vocabularies. If each row of the FTSContent search index table has very few words in it, FullTextSearch likely isn't the best solution--SimpleSearch would be faster in this case.

Depending on the MySQL storage engine at play, the minimum length of words indexed is 4 characters. If your content has a lot of high-value, often-searched words that are 3 characters or shorter, FullTextSearch isn't the right solution.

Your situation might call for more features than FullTextSearch currently supports. For example, SimpleSearch will generate an extract of each search result, and optionally add an html class attribute to the results template, for highlighting search terms. It also supports faceted search--FullTextSearch does not.

After installing FullTextSearch, you need to build the index. This can be done by clearing the site cache and crawling it--by default a Resource's rendered output is added to the index on the event `OnBeforeSaveWebPageCache`. Alternatively, the index will build itself over time whenever a visitor requests a page. If configured as such, the index only builds when a CMS user saves a Resource. 

**Search results can only be returned from the set of indexed Resources.**

NOTE: MySQL's relevancy algorithm is based on the concept of "rarity". If a word appears in very few Resources, those that have it will score very high for it. If a word appears in over 50% of Resources, it is ignored. To get the most intuitive search results for your site's content, experiment with the `scoreThreshold` property of the FullTextSearch Snippet, and possibly `expandQuery`. Also try different indexing methods via the Plugin.

```
# Recursively crawl all resources with html extension at specified URL, waiting 1s between requests.
# Response will be saved as static files in the current local directory.

wget -r -w 1 -A html http://example.com/

# If the above doesn't work, the following is less restrictive, but will download assets and images to your local directory. For huge sites this may be undesirable.

wget -r -w 1 http://example.com

# If your robots.txt denies crawling, do this to get around it.

wget -r -w 1 -e robots=off http://example.com
```

## Usage

### FullTextSearchIndex Plugin

#### Properties
(All optional)
- indexFullRenderedOutput = (boolean) If enabled, Resources will be indexed as they are saved to cache, with the text of the fully-rendered response. This _does_ include content in global nav elements, however the words therein would be _excluded automatically_ from search queries via MySQL's built-in algorithm. (Any word that appears in more than 50% of the index will be ignored.) If this property is disabled, Resources are only indexed when a CMS user saves a document, and only the field(s) configured below will be added to the index. Default enabled.
- appendResourceFields = (csv) MODX Resource fields, the content of which will be appended to the content being indexed. If `indexFullRenderedOutput` is disabled, something needs to be specified here, or in properties below, in order for any content to be indexed. Default ''.
- appendClassObjects = (json) A JSON array of objects, each with the keys 'class', 'resource_key', and 'field'. The array will be iterated and objects of the specified class will be retrieved. The value of the object's `field` attribute will be added to the indexed content. Default ''.
- appendRenderedTVIds = (csv) TV IDs (or names), the rendered output of which will be added to the indexed content. Default ''.
- appendAlways = (string) Add a string to every indexed item. IMPORTANT: given MySQL's built-in algorithm, this would have the effect of adding the words in the string to the list of stop-words--the OPPOSITE of making the words more prominent in search results. Default ''.

**IMPORTANT:** currently the Plugin will only ever index a Resource if it is:
- published and not deleted, because why give users search results for content they can't access?
- cacheable, to avoid indexing content that is meant to be dynamic or worse, private to specific sessions. On the event `OnBeforeSaveWebPageCache`, the `$modx->resource->_output` may contain uncacheable MODX tags. It's expected that these will show up in the index content and _not_ be populated with values. This is similar to the behaviour of StatCache, Jason Coward's fast and awesome caching plugin.
- searchable, to provide CMS users discretion to exclude a Resource from the index

When a Resource is deleted, unpublished, or saved, the Plugin will check these properties and behave accordingly.

### FullTextSearch Snippet

#### Properties
(All optional)
- limit = (int) Limit number of query results. Default: 0 (no limit).
- parents = (csv) Specify IDs of parents. Only the children of these Resources will be included. Default ''.
- excludeIds = (csv) Specify IDs of Resources to exclude. Default ''.
- scoreThreshold = (float) Specify relevancy score, below which Resources will not be returned. Default 1.0.
- expandQuery = (boolean) Allow MySQL FULLTEXT query expansion, which matches on related terms. Note: this can consume a lot more processing power than without query expansion, especially on very large datasets. Default false.
- searchParam = (string) The $\_REQUEST parameter with the search phrase. Default 'search'.
- outputSeparator = (string) Separate output. The Snippet returns matching Resource IDs, so the default ',' is most commonly used.
- toPlaceholder = (string) Send the output to a placeholder of this key. Default '' (return directly).
- debug = (string) Set to 'dump' for var_dump or 'log' for the MODX error log. Default '' (no debug).

#### Example Usage

Default behaviour, sorted by relevancy. If you made this call on the MODX `error_page` Resource, then any 404s would trigger a search based on the URL.
```
[[!getResources?
    &parents=`-1`
    &resources=`[[!FullTextSearch]]`
    &tpl=`search_results.tpl`
    &sortby=`FIELD(modResource.id,[[!FullTextSearch]])`
    &limit=`0`
]]
```

Expand query (matching "related" terms based on MySQL's built-in algorithm) but increase the scoreThreshold, sorted by latest publishedon date.
```
[[!getResources?
    &parents=`-1`
    &resources=`[[!FullTextSearch?
        &scoreThreshold=`1.5`
        &expandQuery=`1`
    ]]`
    &tpl=`search_results.tpl`
    &limit=`0`
]]
```

Dump the SQL, and the results. If you run the SQL in your database client, you can see what’s happening behind the scenes...

```
[[!FullTextSearch? &debug=`dump`]]
```

