<?php


public function indexable($res)
{
    /* Skip binary content types */
    //$modx->resource->ContentType->get('binary')
    // check for published, not deleted, searchable, cacheable

    // maybe?
    /* if specified, limit caching by mime-type
    if (!empty($mimeTypes)) {
        $validMimeTypes = array_walk(explode(',', strtolower($mimeTypes)), 'trim');
        if (!in_array(strtolower($modx->resource->ContentType->get('mime_type')), $validMimeTypes)) break;
    } */
    /* if specified, limit caching by ContentTypes
    if (!empty($contentTypes)) {
        $validContentTypes = array_walk(explode(',', $contentTypes), 'trim');
        if (!in_array($modx->resource->ContentType->get('id'), $validContentTypes)) break;
    }*/
}
