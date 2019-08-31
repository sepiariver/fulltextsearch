<?php
/**
 * @package fulltextsearch
 */
$xpdo_meta_map['FTSContent']= array (
  'package' => 'fulltextsearch',
  'version' => '1.1',
  'table' => 'fts_content',
  'extends' => 'xPDOSimpleObject',
  'tableMeta' => 
  array (
    'engine' => 'InnoDB',
  ),
  'fields' => 
  array (
    'content_id' => 0,
    'content_parent' => 0,
    'content_output' => NULL,
  ),
  'fieldMeta' => 
  array (
    'content_id' => 
    array (
      'dbtype' => 'int',
      'precision' => '10',
      'phptype' => 'integer',
      'attributes' => 'unsigned',
      'null' => false,
      'default' => 0,
      'index' => 'index',
    ),
    'content_parent' => 
    array (
      'dbtype' => 'int',
      'precision' => '10',
      'phptype' => 'integer',
      'attributes' => 'unsigned',
      'null' => false,
      'default' => 0,
      'index' => 'index',
    ),
    'content_output' => 
    array (
      'dbtype' => 'mediumtext',
      'phptype' => 'string',
      'index' => 'fulltext',
    ),
  ),
  'indexes' => 
  array (
    'fts_content_output' => 
    array (
      'alias' => 'fts_content_output',
      'primary' => false,
      'unique' => false,
      'type' => 'FULLTEXT',
      'columns' => 
      array (
        'content_output' => 
        array (
          'length' => '',
          'collation' => 'A',
        ),
      ),
    ),
  ),
);
