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
        $resourceFields = array_filter(array_map('trim', explode(',', $this->getProperty('resourceFields', ''))));
        $classObjects = $this->getProperty('classObjects', ''); // JSON
        $renderedTVIds = array_filter(array_map('trim', explode(',', $this->getProperty('renderedTVIds', ''))));
        $appendAlways = $this->getProperty('appendAlways', ''); // String content stop-words
        $contexts = array_filter(array_map('trim', explode(',', $this->getProperty('contexts', ''))));

        if (empty($resourceFields)) {
            return $this->failure('No resource fields specified to index');
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
        $failed = 0;
        foreach ($resources as $resource) {
            $total++;
            $resId = $resource->get('id');
            // Create or update
            $ftsContent = $this->modx->getObject('FTSContent', ['content_id' => $resId]);
            if (!$ftsContent) {
                $ftsContent = $this->modx->newObject('FTSContent');
                $created++;
            } else {
                $updated++;
            }
            $content = '';
            foreach ($resourceFields as $field) {
                $content .= ' ' . $resource->get($field);
            }
//$this->modx->log(modX::LOG_LEVEL_ERROR, print_r($this->fts, true));
            $processedContent = $this->fts->appendContent([
                'resource' => $resId,
                'appends' => $classObjects,
                'appendRenderedTVIds' => $renderedTVIds,
                'appendContent' => $appendAlways,
            ], $content, true); // processContent
            $this->modx->log(modX::LOG_LEVEL_ERROR, $processedContent);
            $ftsContent->fromArray([
                'content_id' => $resId,
                'content_parent' => $resource->get('parent'),
                'content_output' => $processedContent,
            ]);
            /* Attempt to save the complete Resource output to the index */
            if (!$ftsContent->save()) {
                $this->failure('Failed to index Resource: ' . $resId);
                $failed++;
            }
        }

        
        return $this->success('Total processed: ' . $total . ' Attempted to create: ' . $created . ' Attempted to update: ' . $updated . ' Failed: ' . $failed);
    }
}
return 'IndexRefreshProcessor';
