<?php
// Options
$limit = (int) $modx->getOption('limit', $scriptProperties, 0);
$parents = array_filter(array_map('trim', explode(',', $modx->getOption('parents', $scriptProperties, ''))));
$excludeIds = array_filter(array_map('trim', explode(',', $modx->getOption('excludeIds', $scriptProperties, ''))));
$scoreThreshold = $modx->getOption('scoreThreshold', $scriptProperties, 1.0, true);
$expandQuery = $modx->getOption('expandQuery', $scriptProperties, true);
$searchParam = $modx->getOption('searchParam', $scriptProperties, 'search', true);
$outputSeparator = $modx->getOption('outputSeparator', $scriptProperties, ',');
$toPlaceholder = $modx->getOption('toPlaceholder', $scriptProperties, '');
$debug = $modx->getOption('debug', $scriptProperties, 0);
$tablePrefix = $modx->getOption('table_prefix');

//Prepare Search
$search = '';
$searchInputMode = ($modx->resource && ((int) $modx->resource->get('id') === (int) $modx->getOption('error_page'))) ? 'url' : 'manual';
switch ($searchInputMode) {
    case 'manual':
        $search = preg_replace('/[^\w+-]|_/', ' ', $modx->getOption($searchParam, $_REQUEST, ''));
        break;
    case 'url':
        // Parse request
        $req = $_REQUEST[$modx->getOption('request_param_alias', null, 'q')];
        $req = explode('/', rtrim($req, pathinfo($req, PATHINFO_EXTENSION)));

        // Prepare search phrase
        $search = array();
        foreach ($req as $part) {
            $part = preg_replace('/[^\w+-]|_/', ' ', $part);
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
$search = $modx->quote($search);
$table = $tablePrefix . 'fts_content';

// Prepare IDs and set conditions
$wheres = [];
if (count($parents) > 0) {
    foreach ($parents as $pk => $pv) {
        $parents[$pk] = $modx->quote($pv, PDO::PARAM_INT);
    }
    $wheres[] = "fts.content_parent IN (" . implode(',', $parents) . ")";
}
if (count($excludeIds) > 0) {
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
    $limitString = "LIMIT " . (int) $limit;
}

// Set mode
$modeString = ($expandQuery) ? "WITH QUERY EXPANSION" : "IN NATURAL LANGUAGE MODE";
$modeString = (strpos($search, '-') === false) && (strpos($search, '+') === false) ? $modeString : "IN BOOLEAN MODE";

// Full-text query
$ftQuery = $modx->query("SELECT fts.content_id, fts.score
    FROM (SELECT
        content_id,
        MATCH (content_output) AGAINST ({$search} {$modeString}) AS score
        FROM {$table}
        WHERE MATCH (content_output) AGAINST ({$search}  {$modeString})
    ) AS fts
    {$whereString}
    {$limitString};
");

// Search
$results = [];
try {
    $results = $ftQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $modx->log(modX::LOG_LEVEL_ERROR, __FUNCTION__ . ' ' . __LINE__ . ' ' . $e->getMessage());
}
if (count($results) < 1) return '';

// Handle results
$output = [];
if ($scoreThreshold > 0) {
    foreach ($results as $result) {
        if ($result['score'] < $scoreThreshold) continue;
        $output[] = $result['id'];
    }
}
$output = implode($outputSeparator, $output);
if (empty($toPlaceholder)) return $output;
$modx->setPlaceholder($toPlaceholder, $output);