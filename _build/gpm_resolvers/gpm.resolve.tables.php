<?php
/**
 * Resolve creating db tables
 *
 * THIS RESOLVER IS AUTOMATICALLY GENERATED, NO CHANGES WILL APPLY
 *
 * @package fulltextsearch
 * @subpackage build
 *
 * @var mixed $object
 * @var modX $modx
 * @var array $options
 */

if ($object->xpdo) {
    $modx =& $object->xpdo;
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $modelPath = $modx->getOption('fulltextsearch.core_path', null, $modx->getOption('core_path') . 'components/fulltextsearch/') . 'model/';
            
            $modx->addPackage('fulltextsearch', $modelPath, null);


            $manager = $modx->getManager();

            $manager->createObjectContainer('FTSContent');

            break;
    }
}

return true;