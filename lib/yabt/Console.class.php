<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Console class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Console management class
//------------------------------------------------------------------------------
abstract class Console
{
	//! [resource] File handle for standard output
	private static $stdout;

	//! [resource] File handle for standard error
	private static $stderr;

	//------------------------------------------------------------------------------
	//! Open the stderr file for writing
	//------------------------------------------------------------------------------
	private static function openStderr()
	{
		if (!is_resource(self::$stderr))
			self::$stderr = fopen('php://stderr', 'w');
	}

	//------------------------------------------------------------------------------
	//! Open the stdout file for writing
	//------------------------------------------------------------------------------
	private static function openStdout()
	{
		if (!is_resource(self::$stdout))
			self::$stdout = fopen('php://stdout', 'w');
	}

	//------------------------------------------------------------------------------
	//! Display a warning message on the standard error
	//------------------------------------------------------------------------------
	public function displayWarning($msg)
	{
		self::openStderr();
		fprintf(self::$stderr, "Warning: $msg\n");
	}

	//------------------------------------------------------------------------------
	//! Display an error message on the standard error
	//------------------------------------------------------------------------------
	public static function displayError($msg)
	{
		self::openStderr();
		fprintf(self::$stderr, "Error: $msg\n");
	}

	//------------------------------------------------------------------------------
	//! Display a message on the standard output
	//------------------------------------------------------------------------------
	public static function displayMsg($msg)
	{
		self::openStdout();
		fprintf(self::$stdout, "$msg\n");
	}
};
