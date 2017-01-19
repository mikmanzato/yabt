<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Shell class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//! Exception thrown if shell command execution results in an error
class ShellException
	extends \Exception
{
};

//------------------------------------------------------------------------------
//! Console management class
//------------------------------------------------------------------------------
abstract class Shell
{
	//------------------------------------------------------------------------------
	//! Open the stderr file for writing
	//------------------------------------------------------------------------------
	public static function exec($cmd, &$output = NULL, &$exitValue = NULL	)
	{
		$result = exec($cmd, $output, $exitValue);

		if ($exitValue != 0)
			throw new ShellException("Shell exec failed: exit_value=$exitValue, cmd=[$cmd], result=[$result]");

		return $result;
	}
};
