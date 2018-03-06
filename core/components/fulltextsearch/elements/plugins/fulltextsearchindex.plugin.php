<?php
/**
 * FTSIndex
 *
 * Manages content in the fts_content table, for indexing.
 *
 * OPTIONS:
 * appendResourceFields - (csv)     List of Resource fields' content to index
 * appendClassObjects - (json)      @TODO
 * appendRenderedTVIds - (csv)      ID or name of TV content to render and index
 * appendAlways - (string)          Always append to index, adding to list of stop-words
 * indexFullRenderedOutput - (bool) Enable / disable indexing full rendered content on cache event
 *
 * @package FullTextSearch
 * @author YJ Tso <info@sepiariver.com>
 * Copyright 2018 YJ Tso
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 **/

// OPTIONS
$appendResourceFields = array_filter(array_map('trim', explode(',', $modx->getOption('appendResourceFields', $scriptProperties, ''))));
$appendClassObjects = $modx->getOption('appendClassObjects', $scriptProperties, '');
$appendRenderedTVIds = array_filter(array_map('trim', explode(',', $modx->getOption('appendRenderedTVIds', $scriptProperties, ''))));
$appendAlways = $modx->getOption('appendAlways', $scriptProperties, '');
$indexFullRenderedOutput = $modx->getOption('indexFullRenderedOutput', $scriptProperties, true);

// Paths
$ftsPath = $modx->getOption('fulltextsearch.core_path', null, $modx->getOption('core_path') . 'components/fulltextsearch/');
$ftsPath .= 'model/fulltextsearch/';

// Get Class
if (file_exists($ftsPath . 'fulltextsearch.class.php')) $fts = $modx->getService('fulltextsearch', 'FullTextSearch', $ftsPath, $scriptProperties);
if (!($fts instanceof FullTextSearch)) {
    $modx->log(modX::LOG_LEVEL_ERROR, __FUNCTION__ . ' could not load the required class on line: ' . __LINE__);
    return;
}

switch ($modx->event->name) {

    // Index on cache generation
    case 'OnBeforeSaveWebPageCache':
        // Bypass indexing of full rendered output
        if (!$indexFullRenderedOutput) break;
        if (!$modx->resource) {
            $modx->log(modX::LOG_LEVEL_ERROR, __FUNCTION__ . ' could not load the required Resource on line: ' . __LINE__);
            break;
        }
        /* Write Resource output to index before caching it in MODX */
        if ($fts->indexable($modx->resource)) {
            $resId = $modx->resource->get('id');
            $ftsContent = $modx->getObject('FTSContent', ['content_id' => $resId]);
            if (!$ftsContent) $ftsContent = $modx->newObject('FTSContent');
            $contentOutput = $fts->appendContent([
                'resource' => $resId,
                'appends' => $appendClassObjects,
                'appendRenderedTVIds' => $appendRenderedTVIds,
                'appendContent' => $appendAlways,
            ], $modx->resource->_output);
            $ftsContent->fromArray([
                'content_id' => $resId,
                'content_parent' => $modx->resource->get('parent'),
                'content_output' => $contentOutput,
            ]);
            /* Attempt to save the complete Resource output to the index */
            if (!$ftsContent->save()) {
                $modx->log(modX::LOG_LEVEL_ERROR, __FUNCTION__ . ' could not index the output from Resource: ' . $resId . ' on line: ' . __LINE__);
            }
        }
        break;
    // Remove from index on UD events
    case 'OnResourceDelete':
    case 'OnDocUnPublished':
        if (!$modx->resource) {
            $modx->log(modX::LOG_LEVEL_ERROR, __FUNCTION__ . ' could not load the required Resource, on line: ' . __LINE__);
            break;
        }
        /* Delete Resource from index */
        $fts->removeIndex($modx->resource->get('id'));
        break;
    case 'OnDocFormSave':
        if (!$resource) {
            $modx->log(modX::LOG_LEVEL_ERROR, __FUNCTION__ . ' could not load the required Resource on line: ' . __LINE__);
            break;
        }
        $resId = $resource->get('id');
        /* Delete Resource from index if indexable status has changed */
        if (!$fts->indexable($resource)) {
            /* Delete Resource from index */
            $fts->removeIndex($resId);
        } else {
            // Defer to cache save event if indexing full rendered output
            if ($indexFullRenderedOutput) break;
            // Create or update
            $ftsContent = $modx->getObject('FTSContent', ['content_id' => $resId]);
            if (!$ftsContent) $ftsContent = $modx->newObject('FTSContent');
            $content = '';
            foreach ($appendResourceFields as $field) {
                $content .= ' ' . $resource->get($field);
            }
            $content = $fts->appendContent([
                'resource' => $resId,
                'appends' => $appendClassObjects,
                'appendRenderedTVIds' => $appendRenderedTVIds,
                'appendContent' => $appendAlways,
            ], $content, true); // processContent
            $ftsContent->fromArray([
                'content_id' => $resId,
                'content_parent' => $resource->get('parent'),
                'content_output' => $content,
            ]);
            /* Attempt to save the complete Resource output to the index */
            if (!$ftsContent->save()) {
                $modx->log(modX::LOG_LEVEL_ERROR, __FUNCTION__ . ' could not index the output from Resource: ' . $resId . ' on line: ' . __LINE__);
            }
        }
        break;
    default:
        break;
}
