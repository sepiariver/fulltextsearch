<?php
// Options
$limit = (int) $modx->getOption('limit', $scriptProperties, 0);
$offset = (int) $modx->getOption('offset', $scriptProperties, 0);
$parents = array_filter(array_map('trim', explode(',', $modx->getOption('parents', $scriptProperties, ''))));
$excludeIds = array_filter(array_map('trim', explode(',', $modx->getOption('excludeIds', $scriptProperties, ''))));
$scoreThreshold = $modx->getOption('scoreThreshold', $scriptProperties, 1.0, true);
$expandQuery = $modx->getOption('expandQuery', $scriptProperties, false);
$searchParam = $modx->getOption('searchParam', $scriptProperties, 'search', true);
$outputSeparator = $modx->getOption('outputSeparator', $scriptProperties, ',');
$toPlaceholder = $modx->getOption('toPlaceholder', $scriptProperties, '');
$debug = $modx->getOption('debug', $scriptProperties, '');
$tablePrefix = $modx->getOption('table_prefix');

//Prepare Search
$search = '';
$searchInputMode = ($modx->resource && ((int) $modx->resource->get('id') === (int) $modx->getOption('error_page'))) ? 'url' : 'manual';
switch ($searchInputMode) {
    case 'manual':
        $search = preg_replace('/[^\w\+\-"]|_/', ' ', $modx->getOption($searchParam, $_REQUEST, ''));
        break;
    case 'url':
        // Parse request
        $req = $_REQUEST[$modx->getOption('request_param_alias', null, 'q')];
        $req = explode('/', rtrim($req, pathinfo($req, PATHINFO_EXTENSION)));

        // Prepare search phrase
        $search = array();
        foreach ($req as $part) {
            $part = preg_replace('/[^\w\+\-"]|_/', ' ', $part);
            $part = explode(' ', $part);
            foreach ($part as $term) {
                if (empty($term) || is_numeric($term)) continue;
                $search[] = $term;
            }
        }
        $search = implode(' ', $search);
        break;
    default:
        break;
}

// Important!
$search = $modx->quote(' ' . $search);
$table = $tablePrefix . 'fts_content';

// Prepare IDs and set conditions
$wheres = ["fts.score > " . floatval($scoreThreshold)];
if (is_array($parents) && count($parents) > 0) {
    foreach ($parents as $pk => $pv) {
        $parents[$pk] = $modx->quote($pv, PDO::PARAM_INT);
    }
    $wheres[] = "fts.content_parent IN (" . implode(',', $parents) . ")";
}
if (is_array($excludeIds) && count($excludeIds) > 0) {
    foreach ($excludeIds as $ek => $ev) {
        $excludeIds[$ek] = $modx->quote($ev, PDO::PARAM_INT);
    }
    $wheres[] = "fts.content_id NOT IN (" . implode(',', $excludeIds) . ")";
}
$whereString = '';
if (!empty($wheres)) {
    $whereString = 'WHERE ' . implode(' AND ', $wheres);
}

// Prepare limit
$limitString = '';
if ($limit > 0) {
    $limitString = " LIMIT " . (int) $limit;
}

// Prepare offset
$offsetString = '';
if ($offset > 0) {
    $offsetString = " OFFSET " . (int) $offset;
}

// Set mode
$modeString = ($expandQuery) ? "WITH QUERY EXPANSION" : "IN NATURAL LANGUAGE MODE";
$modeString =
	(strpos($search, ' -') === false) &&
	(strpos($search, ' +') === false) &&
	(strpos($search, '"') === false) ? $modeString : "IN BOOLEAN MODE";

// Full-text query
$queryString = "SELECT fts.content_id
    FROM (SELECT
        content_id,
	content_parent,
        MATCH (content_output) AGAINST ({$search} {$modeString}) AS score
        FROM {$table}
        WHERE MATCH (content_output) AGAINST ({$search}  {$modeString})
    ) AS fts
    {$whereString} ORDER BY fts.score DESC
    {$limitString} {$offsetString};
    ";
$ftQuery = $modx->query($queryString);

// Search
$results = [];
try {
    $results = $ftQuery->fetchAll(PDO::FETCH_COLUMN, 0);
} catch (Exception $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, __FUNCTION__ . ' ' . __LINE__ . ' ' . $e->getMessage());
}

// Debugging
switch ($debug) {
    case 'dump':
        var_dump($queryString);
        var_dump($results);
        return __LINE__;
        break;
    case 'log':
        $modx->log(modX::LOG_LEVEL_ERROR, $queryString);
        $modx->log(modX::LOG_LEVEL_ERROR, print_r($results, true));
        return __LINE__;
        break;
    default:
        break;
}

// Handle results
if (count($results) < 1) return ''; // Pass empty string to wrapping Snippet
$output = implode($outputSeparator, $results); // Output list of Resource ID's
if (empty($toPlaceholder)) return $output;
$modx->setPlaceholder($toPlaceholder, $output);