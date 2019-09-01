# FullTextSearch
_MySQL FULLTEXT search for MODX CMS._

MySQL is not a search engine. For full-fledged, enterprise-ready search solutions, MODX CMS supports Solr and Elasticsearch with the well-adopted SimpleSearch Extra. This is the recommended approach for the sites that have sophisticated search requirements and extreme scalability requirements. SimpleSearch represents a lot of community contribution and the work of amazing developers like Jan Peca. FullTextSearch is not a "competitor", rather something a little different.

> Read the blog post [here](https://sepiariver.com/modx/mysql-fulltext-search-for-modx-cms/).

## Why?
For "simple" implementations, without a third-party engine, SimpleSearch uses relatively limited `LIKE` queries. AdvSearch relies on the Zend Search library and is arguably more complex to implement. Both of those packages do a lot of work with regards to templating the output, and providing configurability of the executed MySQL queries, but both query against the standard MODX tables—sometimes multiple tables, which is when performance can become an issue.

### Custom index
It seems the most common need amongst MODX users, that is not addressed with the above Extras, is the ability to customize a search _index_: "Include the rendered output of this TV, include that Resource field, but not this one, etc..." Common workarounds include parsing multiple Resource fields and TVs, and storing the result in a specific Resource field that serves as a "search index".

FullTextSearch is meant to fill the gap. It builds a custom search index in a dedicated table, based on configuration of the FullTextSearchIndex Plugin. This table has a `FULLTEXT` MySQL index that facilitates search features like exclusion of terms ("cars -ford"), stop-words and relevancy scores based on the built-in MySQL algorithm.

### Interoperability and separation of concerns
FullTextSearch defers to listing Snippets like getResources or pdoResources for templating power, and further filtering if required. The FullTextSearch Snippet returns a comma-separated list of Resource IDs, to pass to the `resources` property of the above. Combined with TaggerGetResourcesWhere, search results can easily be filtered by "tag" or "category", for example.

### Performance
The additional query to fetch Resource IDs does not, in and of itself, add a lot of overhead, because the `MATCH ... AGAINST` statements executed by FullTextSearch are designed to use the `FULLTEXT` index in a performant way. With Resource IDs passed-in, getResources executes a fast query, utilizing other indexed properties on the standard MODX `site_content` table. (Curiously, pdoResources doesn't seem as efficient with this setup.)

In testing, most searches seem to trigger query times on-par, if not slightly faster, than those of SimpleSearch—yet the parse time can be much faster, because again SimpleSearch does a lot of work. With getPage rendering pagination, getResources templating, and FullTextSearch, the overall timings are better, or at worst on par, with SimpleSearch, even with SimpleSearch's `showExtract` disabled.

### Implementation
FullTextSearch is about as easy to set up as SimpleSearch, adds useful search features without third-party dependencies, and in some cases provides improved performance. You can do interesting things with it like power "smart" 404 pages. It _might_ be a really good fit for your use case.

## Why not?
Then again, it might not.

MySQL's `FULLTEXT` search prefers wide vocabularies—what you commonly see on blogs or "natural language" sites with a high volume of pages and word count. The performance benefits over standard `LIKE` queries diminishes for small vocabularies. If each row of the FTSContent search index table has very few words in it, FullTextSearch may not be the best solution. For example, if you only care about searching the Resource `pagetitle` field, SimpleSearch is likely better.

Depending on the MySQL storage engine at play, the minimum length of words indexed is 4 characters. If your content has a lot of high-value, often-searched words that are 3 characters or shorter, FullTextSearch isn't the right solution.

Your situation might call for more features than FullTextSearch currently supports. For example, SimpleSearch will generate an extract of each search result, and optionally add an html class attribute to the results template, for highlighting search terms.

After installing FullTextSearch, you need to build the index. This can be done by clearing the site cache and crawling it. By enabling the System Setting `index_full_rendered_output`, each Resource's rendered output is added to the index on the event `OnBeforeSaveWebPageCache`. Alternatively, the index will build itself over time whenever a visitor requests a page. If configured as such, the index only builds when a CMS user saves a Resource. There's also a manager menu item that re-indexes all Resources based on System Settings.

**FullTextSearch can only return search results from the set of indexed Resources.**

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
(All optional. System Settings are installed using snake_case_keys (e.g. `fulltextsearch.index_resource_fields`) per the established pattern for Settings. Settings serve as defaults for the following Plugin Properties.

- indexFullRenderedOutput = (boolean) If enabled, Resources will be indexed as they are saved to cache, with the text of the fully-rendered response. This would include content from global nav elements, however the words therein would be _excluded automatically_ from search queries via MySQL's built-in algorithm. (Any word that appears in more than 50% of the index will be ignored.) If this property is disabled, Resources are only indexed when a CMS user saves a document or uses the menu action (in which case only the field(s) configured in `indexResourceFields` will be included). Strips `<style>` and `<script>` tags. Default disabled.
- indexResourceFields = (csv) MODX Resource fields, the content of which will be indexed. Only used if `indexFullRenderedOutput` is disabled, in which case something needs to be specified here or in `append...` properties, in order for anything to be indexed. Default ''.
- appendClassObjects = (json) A JSON array of objects, each with the keys `class`, `resource_key`, and `field`. The array will be iterated and objects of the specified class will be retrieved. The value of the object's `field` attribute will be added to the indexed content. NOTE: this can only be used with objects where the value and the Resource ID reference is on a single table. Default ''.
- appendRenderedTVIds = (csv) TV IDs (or names), the rendered output of which will be added to the indexed content. Default ''.
- appendAlways = (string) Add a string to every indexed item. IMPORTANT: given MySQL's built-in algorithm, this would have the effect of adding the words in the string to the list of stop-words—the _opposite_ of making the words more prominent in search results. Default ''.

**IMPORTANT:** currently the Plugin will only ever index a Resource if it is:
- published and not deleted, because why give users search results for content they can't access?
- searchable, to provide CMS users discretion to exclude a Resource from the index
- cacheable, to avoid indexing content that is meant to be dynamic or worse, private to specific sessions. On the event `OnBeforeSaveWebPageCache`, the `$modx->resource->_output` may contain uncacheable MODX tags. (Similarly if one of the `indexResourceFields` contains uncacheable tags, they will be indexed.) It's expected that these will show up in the index and _not_ be populated with values. This is similar to the behaviour of StatCache, Jason Coward's fast and awesome caching plugin.

When a Resource is deleted, unpublished, or saved, the Plugin will check these properties and behave accordingly.

### FullTextSearch Menu Action

The manager menu has an action under "Clear Cache" that populates the FullTextSearch custom index. It uses the System Settings under the `fulltextsearch` namespace to determine which Resource fields to include.

IMPORTANT: on a site with thousands of Resources, the process of batch indexing can take a long time and cause unwanted server load. In this case, do NOT use the menu action, but instead set up the Plugin with `indexFullRenderedOutput` so each page will be added to index as it's requested and cached, distributing the processing over time.

### FullTextSearch Snippet

#### Properties
(All optional)
- limit = (int) Limit number of query results. Default: 0 (no limit).
- parents = (csv) Specify IDs of parents. Only the children of these Resources will be included. Default ''.
- excludeIds = (csv) Specify IDs of Resources to exclude. Default ''.
- scoreThreshold = (float) Specify relevancy score, below which Resources will not be returned. Default 1.0.
- expandQuery = (boolean) Allow MySQL `FULLTEXT` query expansion, which matches on related terms. Note: this can consume a lot more processing power than without query expansion, especially on very large datasets. Default false.
- searchParam = (string) The `$_REQUEST` parameter with the search phrase. Default 'search'.
- outputSeparator = (string) Separate output. The Snippet returns matching Resource IDs, so the default ',' is most commonly used.
- toPlaceholder = (string) Send the output to a placeholder of this key. Default '' (return directly).
- debug = (string) Set to 'dump' for `var_dump` or 'log' for the MODX error log. Default '' (no debug).

#### Example Usage

Default behaviour, sorted by relevancy. If you made this call on the MODX `error_page` Resource, then any 404s would trigger a search based on the URL.
```
[[!FullTextSearch? &toPlaceholder=`fts_results`]]
[[!getResources?
    &parents=`-1`
    &resources=`[[!+fts_results]]`
    &limit=`20`
    &sortby=`FIELD(modResource.id, [[!+fts_results]])`
    &sortdir=`ASC`
    &tpl=`search_results.tpl`
]]
```

Expand query (matching "related" terms based on MySQL's built-in algorithm) but increase the `scoreThreshold`, sorted by latest `publishedon` date.
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
