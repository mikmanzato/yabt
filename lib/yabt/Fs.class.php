<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Fs class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//! Exception for fs-related errors
class FsException extends \Exception {}


//------------------------------------------------------------------------------
//! Contains filesystem-related functions
//------------------------------------------------------------------------------
abstract class Fs
{
	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public static function getTempDir()
	{
		return sys_get_temp_dir();
	}

	//--------------------------------------------------------------------------
	//! Create a temporary directory and returns its name
	/*! \see http://stackoverflow.com/a/17280327 */
	//--------------------------------------------------------------------------
	public static function mkTempDir($dir = NULL, $prefix = "")
	{
		$template = "{$prefix}XXXXXX";

		if (!$dir || !is_dir($dir))
			$dir = self::getTempDir();

		$cmd = sprintf("mktemp -d --tmpdir=%s %s",
		               escapeshellarg($dir),
		               escapeshellarg($template));
		$result = Shell::exec($cmd);
		return $result;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public static function mkTempName($prefix, $dir = NULL)
	{
		if (!$dir || !is_dir($dir))
			$dir = self::getTempDir();

		return tempnam($dir, $prefix);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public static function mkExactTempName($name)
	{
		$tmpFName = Fs::joinPath(Fs::getTempDir(), $name);
		if (file_exists($tmpFName))
			Fs::unlink($tmpFName);

		return $tmpFName;
	}

	//--------------------------------------------------------------------------
	//! TODO: Improve!
	//--------------------------------------------------------------------------
	public static function joinPath($p1, $p2)
	{
		if (($p1 == '.') || ($p1 == ''))
			return $p2;
		elseif (($p2 == '.') || ($p2 == ''))
			return $p1;
		elseif (substr($p1, -1) == DIRECTORY_SEPARATOR)
			return $p1.$p2;
		else
			return $p1.DIRECTORY_SEPARATOR.$p2;
	}

	//--------------------------------------------------------------------------
	//! Create a directory, throw an exception upon failure
	//--------------------------------------------------------------------------
	public static function mkdir($dir, $mode = 0777, $recursive = FALSE)
	{
		if (!@mkdir($dir, $mode, $recursive)) {
			$error = error_get_last();
			throw new FsException("Failed to create directory '$dir': ".$error['message']);
		}
	}

	//--------------------------------------------------------------------------
	//! Open a file, throw an exception upon failure
	//--------------------------------------------------------------------------
	public static function fopen($fname, $mode)
	{
		$fp = @fopen($fname, $mode);
		if (!is_resource($fp)) {
			$error = error_get_last();
			throw new FsException("Failed to open file '$fname': ".$error['message']);
		}
		return $fp;
	}

	//--------------------------------------------------------------------------
	//! Return the available space in the partition containing the passed path
	/*! \param $path [string] The path to check
		\returns (double) The available number of bytes. Note that the result is
			in double format in order to support wide partition sizes. */
	//--------------------------------------------------------------------------
	public static function getAvailableSpace($path)
	{
		$cmd = sprintf('echo $(($(stat -f --format="%%a*%%S" %s)))', escapeshellarg($path));
		$result = Shell::exec($cmd);
		return (double) $result;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public static function isLink($path)
	{
		return is_link($path);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public static function unlink($path)
	{
		@unlink($path);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public static function exists($path)
	{
		return file_exists($path);
	}
};

