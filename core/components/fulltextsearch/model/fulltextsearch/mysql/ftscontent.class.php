<?php
/**
 * @package fulltextsearch
 */
require_once (strtr(realpath(dirname(dirname(__FILE__))), '\\', '/') . '/ftscontent.class.php');
class FTSContent_mysql extends FTSContent {}
?>