<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Main class implementation.

	$Id$ */
//------------------------------------------------------------------------------

// Register class autoloader
spl_autoload_register(function ($class) {
//		echo "Autoload: $class\n";
//		$dir = dirname(dirname(__FILE__));
		$class = str_replace("\\", "/", $class);
		require_once "{$class}.class.php";
	});
