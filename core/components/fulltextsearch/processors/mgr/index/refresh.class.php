<?php
class IndexRefreshProcessor extends modProcessor {
    public $fts = null;

    public function checkPermissions() 
    {
        return $this->modx->hasPermission('empty_cache'); // Why not
    }

    public function initialize()
    {
        // Paths
        $ftsPath = $this->modx->getOption('fulltextsearch.core_path', null, $this->modx->getOption('core_path') . 'components/fulltextsearch/');
        $ftsPath .= 'model/fulltextsearch/';
        $this->fts = $this->modx->getService('fulltextsearch', 'FullTextSearch', $ftsPath);

        return parent::initialize();
    }

    public function process() {

        $resourceIds = array_filter(array_map('trim', explode(',', $this->getProperty('resourceIds', ''))));
        $excludeIds = array_filter(array_map('trim', explode(',', $this->getProperty('excludeIds', ''))));
        $resourceFields = array_filter(array_map('trim', explode(',', $this->getProperty('resourceFields', $this->fts->getOption('index_resource_fields', null, false)))));
        $classObjects = $this->getProperty('classObjects', $this->fts->getOption('append_class_objects', null, '')); // JSON
        $renderedTVIds = array_filter(array_map('trim', explode(',', $this->getProperty('renderedTVIds', $this->fts->getOption('append_rendered_tv_ids', null, '')))));
        $appendAlways = $this->getProperty('appendAlways', $this->fts->getOption('append_always', null, '')); // String content stop-words
        $contexts = array_filter(array_map('trim', explode(',', $this->getProperty('contexts', ''))));

        if (empty($resourceFields)) {
            $msg = 'No resource fields specified to index';
            $this->modx->log(modX::LOG_LEVEL_ERROR, $msg);
            return $this->failure($msg);
        }

        $c = $this->modx->newQuery('modResource');
        $where = [];
        if (!empty($resourceIds)) $where['id:IN'] = $resourceIds;
        if (!empty($excludeIds)) $where['id:NOT IN'] = $excludeIds;
        if (!empty($contexts)) $where['context_key:IN'] = $contexts;

        if (!empty($where)) $c->where($where);

        $resources = $this->modx->getCollection('modResource', $c);
        $total = 0;
        $created = 0;
        $updated = 0;
        $removed = 0;
        $failed = 0;
        foreach ($resources as $resource) {
            // Count stuff
            $total++;
            // Setup Resource
            $resId = $resource->get('id');
            $this->modx->log(modX::LOG_LEVEL_INFO, 'Evaluating Resource: ' . $resId);
            // Check indexable Resource
            if (!$this->fts->indexable($resource)) {
                // Delete Resource from index
                $removal = $this->fts->removeIndex($resId);
                $this->modx->log(modX::LOG_LEVEL_INFO, 'Unindexable Resource: ' . $resId);
                if ($removal) $removed++;
                continue;
            } 
            // Create or update FTS Content
            $ftsContent = $this->modx->getObject('FTSContent', ['content_id' => $resId]);
            if (!$ftsContent) {
                $ftsContent = $this->modx->newObject('FTSContent');
                $created++;
            } else {
                $updated++;
            }
            // Setup content
            $content = '';
            foreach ($resourceFields as $field) {
                $content .= ' ' . $resource->get($field);
            }
            // Process content
            $processedContent = $this->fts->appendContent([
                'resource' => $resource,
                'appends' => $classObjects,
                'appendRenderedTVIds' => $renderedTVIds,
                'appendContent' => $appendAlways,
            ], $content, true); // processContent
            // Populate FTS Content object
            $ftsContent->fromArray([
                'content_id' => $resId,
                'content_parent' => $resource->get('parent'),
                'content_output' => $processedContent,
            ]);
            // Attempt to save FTS Content
            if (!$ftsContent->save()) {
                $this->failure('Failed to index Resource: ' . $resId);
                $failed++;
            }
        }
        // Output
        $msg = 'Total processed: ' . $total . ' Attempted to create: ' . $created . ' Attempted to update: ' . $updated .  ' Removed: ' . $removed . ' Failed: ' . $failed;
        $this->modx->log(modX::LOG_LEVEL_INFO, $msg);
        return $this->success($msg);
    }
}
return 'IndexRefreshProcessor';
