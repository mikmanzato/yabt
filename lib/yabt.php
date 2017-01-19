<?php
/*==============================================================================

	yabt

	Main file

	$Id$

==============================================================================*/

// Set include path
ini_set('include_path', dirname(__FILE__).PATH_SEPARATOR.
                        ini_get('include_path'));

require_once 'yabt/autoload.php';

yabt\Main::run();
