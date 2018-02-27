<?php
/**
 * FullTextSearch class.
 * @package FullTextSearch
 *
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

class FullTextSearch
{
    public $modx = null;
    public $namespace = 'fulltextsearch';
    public $options = [];

    public function __construct(modX &$modx, array $options = [])
    {
        $this->modx =& $modx;
        $this->namespace = $this->getOption('namespace', $options, 'fulltextsearch');

        $corePath = $this->getOption('core_path', $options, $this->modx->getOption('core_path', null, MODX_CORE_PATH) . 'components/fulltextsearch/');
        $assetsPath = $this->getOption('assets_path', $options, $this->modx->getOption('assets_path', null, MODX_ASSETS_PATH) . 'components/fulltextsearch/');
        $assetsUrl = $this->getOption('assets_url', $options, $this->modx->getOption('assets_url', null, MODX_ASSETS_URL) . 'components/fulltextsearch/');
        $dbPrefix = $this->getOption('table_prefix', $options, $this->modx->getOption('table_prefix', null, 'modx_'));

        /* load config defaults */
        $this->options = array_merge([
            'namespace' => $this->namespace,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'chunksPath' => $corePath . 'elements/chunks/',
            'snippetsPath' => $corePath . 'elements/snippets/',
            'templatesPath' => $corePath . 'templates/',
            'assetsPath' => $assetsPath,
            'assetsUrl' => $assetsUrl,
            'jsUrl' => $assetsUrl . 'js/',
            'cssUrl' => $assetsUrl . 'css/',
            'connectorUrl' => $assetsUrl . 'connector.php',
        ], $options);

        $this->modx->addPackage('fulltextsearch', $this->options['modelPath'], $dbPrefix);
        $this->modx->lexicon->load('fulltextsearch:default');
    }

    public function indexable($res)
    {
        $indexable = false;
        /* Required */
        if (!($res instanceof modResource)) {
            return $indexable;
        }
        /* Skip binary content types */
        if ($res->ContentType->get('binary')) {
            return $indexable;
        }
        /* Skip unpublished */
        if (!$res->get('published')) {
            return $indexable;
        }
        /* Skip deleted */
        if ($res->get('deleted')) {
            return $indexable;
        }
        /* Skip unsearchable */
        if (!$res->get('searchable')) {
            return $indexable;
        }
        /* Skip uncacheable (risk of caching secret content) */
        if (!$res->get('cacheable')) {
            return $indexable;
        }
        /* if specified, limit indexing by mime-type */
        $mimeTypes = $this->explodeAndClean($this->getOption('mimeTypes'));
        if (!empty($mimeTypes)) {
            $validMimeTypes = array_map('strtolower', $mimeTypes);
            if (!in_array(strtolower($res->ContentType->get('mime_type')), $validMimeTypes)) {
                return $indexable;
            };
        }
        /* if specified, limit indexing by ContentTypes */
        $contentTypes = $this->explodeAndClean($this->getOption('contentTypes'));
        if (!empty($contentTypes)) {
            if (!in_array($res->ContentType->get('id'), $contentTypes)) {
                return $indexable;
            }
        }
        /* After running the gauntlet */
        $indexable = true;
        return $indexable;
    }

    public function appendContent($options)
    {
        // Silently fail on these
        if (!is_array($options) || empty($options['resource'])) return '';
        // Quietly cast
        $resId = (int) $options['resource'];
        $appendContent = (string) $options['appendContent'];

        // Append rendered TV values
        if (!empty($options['appendRenderedTVIds']) && is_array($options['appendRenderedTVIds'])) {
            $c = $this->modx->newQuery('modTemplateVar');
            $c->where([
                'id:IN' => $options['appendRenderedTVIds'],
            ]);
            $tvs = $this->modx->getCollection('modTemplateVar', $c);
            foreach ($tvs as $tv) {
                $appendContent .= ' ' . $tv->renderOutput($resId);
            }
        }
        // Append arbitrary object field values
        if (!empty($options['appends'])) {
            // Convert from JSON
            $appends = (array) $this->modx->fromJSON($options['appends']);
            if (is_array($appends) && !empty($appends)) {
                // Fetch specified fields from specified objects
                foreach ($appends as $append) {
                    $appendObjects = $this->modx->getIterator($append['class'], [$append['resource_key'] => $resId]);
                    foreach ($appendObjects as $appendObject) {
                        // This is the raw content. Default values for TVs are not fetched this way.
                        $appendContent .= ' ' . $appendObject->get($append['field']);
                    }
                }
            }
        }
        // Return content to be appended
        return ' ' . $appendContent;
    }

    /* UTILITY METHODS (@theboxer) */
    /**
     * Get a local configuration option or a namespaced system setting by key.
     *
     * @param string $key The option key to search for.
     * @param array $options An array of options that override local options.
     * @param mixed $default The default value returned if the option is not found locally or as a
     * namespaced system setting; by default this value is null.
     * @return mixed The option value or the default value specified.
     */
    public function getOption($key, $options = array(), $default = null)
    {
        $option = $default;
        if (!empty($key) && is_string($key)) {
            if ($options != null && array_key_exists($key, $options)) {
                $option = $options[$key];
            } elseif (array_key_exists($key, $this->options)) {
                $option = $this->options[$key];
            } elseif (array_key_exists("{$this->namespace}.{$key}", $this->modx->config)) {
                $option = $this->modx->getOption("{$this->namespace}.{$key}");
            }
        }
        return $option;
    }

    public function explodeAndClean($array, $delimiter = ',')
    {
        $array = explode($delimiter, $array);     // Explode fields to array
        $array = array_map('trim', $array);       // Trim array's values
        $array = array_keys(array_flip($array));  // Remove duplicate fields
        $array = array_filter($array);            // Remove empty values from array

        return $array;
    }
    public function getChunk($tpl, $phs)
    {
        if (!is_array($phs)) $phs = [];
        if (strpos($tpl, '@INLINE ') !== false) {
            $content = str_replace('@INLINE', '', $tpl);
            /** @var \modChunk $chunk */
            $chunk = $this->modx->newObject('modChunk', array('name' => 'inline-' . uniqid()));
            $chunk->setCacheable(false);

            return $chunk->process($phs, $content);
        }

        return $this->modx->getChunk($tpl, $phs);
    }

}
