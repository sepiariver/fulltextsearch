<?php

// Options
$limit = (int) $modx->getOption('limit', $scriptProperties, 0);
$parents = array_filter(array_map('trim', explode(',', $modx->getOption('parents', $scriptProperties, ''))));
$excludeIds = array_filter(array_map('trim', explode(',', $modx->getOption('excludeIds', $scriptProperties, ''))));
$scoreThreshold = $modx->getOption('scoreThreshold', $scriptProperties, 1.0, true);
$allowQueryExpansion = $modx->getOption('allowQueryExpansion', $scriptProperties, true);
$searchStyle = $modx->getOption('searchStyle', $scriptProperties, 'manual');
$manualSearchParam = $modx->getOption('manualSearchParam', $scriptProperties, 'search', true);
$outputSeparator = $modx->getOption('outputSeparator', $scriptProperties, ',');
$toPlaceholder = $modx->getOption('toPlaceholder', $scriptProperties, '');
$debug = $modx->getOption('debug', $scriptProperties, 0);
$tablePrefix = $modx->getOption('table_prefix');

//Prepare Search
$search = '';
switch ($searchStyle) {
    case 'manual':
        $search = $modx->getOption($manualSearchParam, $_REQUEST, '');
        break;
    case 'url':
        // Parse request
        $req = $_REQUEST[$modx->getOption('request_param_alias', null, 'q')];
        $req = explode('/', rtrim($req, pathinfo($req, PATHINFO_EXTENSION)));

        // Prepare search phrase
        $search = array();
        foreach ($req as $part) {
            $part = str_replace(array('-', '_', '.', ';', '"'), ' ', $part);
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

// Prepare IDs
$parentIdString = '';
if (count($parents) > 0) {
    foreach ($parents as $pk => $pv) {
        $parents[$pk] = $modx->quote($pv, PDO::PARAM_INT);
    }
    $parentIdString = " AND fts.content_parent IN (" . implode(',', $parents) . ") ";
}

$excludeIdString = '';
if (count($excludeIds) > 0) {
    foreach ($excludeIds as $ek => $ev) {
        $excludeIds[$ek] = $modx->quote($ev, PDO::PARAM_INT);
    }
    $excludeIdString = " AND fts.id NOT IN (" . implode(',', $excludeIds) . ") ";
}

// Prepare limit
$limitString = '';
if ($limit > 0) {
    $limitString = "LIMIT " . (int) $limit;
}

// Full-text query
$ftQuery = $modx->query("SELECT fts.content_id, fts.score
    FROM (SELECT
        id,
        searchable,
        published,
        deleted,
        parent,
        MATCH (`pagetitle`,`longtitle`,`description`,`introtext`,`content`) AGAINST ({$search} IN NATURAL LANGUAGE MODE) AS score
        FROM {$table}
        WHERE MATCH (`pagetitle`,`longtitle`,`description`,`introtext`,`content`) AGAINST ({$search} IN NATURAL LANGUAGE MODE)
    ) AS res
    WHERE res.searchable = 1 AND res.published = 1 AND res.deleted = 0
    {$parentIdString} {$excludeIdString} {$limitString}; ");
$results = $ftQuery->fetchAll(PDO::FETCH_ASSOC);

// Only run expanded query if zero results
if (count($results) < 1 && $allowQueryExpansion) {
    $ftQuery = $modx->query("SELECT res.id, res.score
        FROM (SELECT
            id,
            searchable,
            published,
            deleted,
            parent,
            MATCH (`pagetitle`,`longtitle`,`description`,`introtext`,`content`) AGAINST ({$search} WITH QUERY EXPANSION) AS score
            FROM {$table}
            WHERE MATCH (`pagetitle`,`longtitle`,`description`,`introtext`,`content`) AGAINST ({$search} WITH QUERY EXPANSION)
        ) AS res
        WHERE res.searchable = 1 AND res.published = 1 AND res.deleted = 0
        {$parentIdString} {$excludeIdString} {$limitString}; ");
    $results = $ftQuery->fetchAll(PDO::FETCH_ASSOC);
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
