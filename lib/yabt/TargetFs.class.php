<?php
//------------------------------------------------------------------------------
/* 	Yabt

	TargetFs class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//! Exception for target fs-related errors
class TargetFsException extends \Exception {}


//------------------------------------------------------------------------------
//! Abstraction for generic target filesystems
/*! Target filesystems may be the destination for dump/backup files */
//------------------------------------------------------------------------------
abstract class TargetFs
{
	//! [string] Subdirectory in the target FS
	protected $subdir = NULL;

	//--------------------------------------------------------------------------
	//! TODO: Improve!
	//--------------------------------------------------------------------------
	public static function joinPath($p1, $p2)
	{
		return Fs::joinPath($p1, $p2);
	}

	//--------------------------------------------------------------------------
	//! TODO: Improve!
	//--------------------------------------------------------------------------
	public static function build($location, $subdir)
	{
		$fs = LocalTargetFs::build($location, $subdir);
		if ($fs)
			return $fs;

		$fs = FtpTargetFs::build($location, $subdir);
		if ($fs)
			return $fs;

		$fs = Ssh2TargetFs::build($location, $subdir);
		if ($fs)
			return $fs;

		throw new TargetFsException("Unknown target: $location");
	}

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	protected function __construct($subdir)
	{
		$this->subdir = $subdir;
	}

	//--------------------------------------------------------------------------
	//! Create a directory, throw an exception upon failure
	//--------------------------------------------------------------------------
	abstract public function mkdir($dir, $mode = 0777, $recursive = FALSE);

	//--------------------------------------------------------------------------
	//! Return the available space at the location %path
	/*! \param $path [string] The path to check
		\returns (double) The available number of bytes. Note that the result is
			in double format in order to support wide partition sizes. */
	//--------------------------------------------------------------------------
	abstract public function getAvailableSpace($path);

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern
	//--------------------------------------------------------------------------
	abstract public function listFiles($path, $pattern = NULL);

	//--------------------------------------------------------------------------
	//! Tells whether the given path exists
	//--------------------------------------------------------------------------
	abstract public function exists($path);

	//--------------------------------------------------------------------------
	//! Get size of file $path
	//--------------------------------------------------------------------------
	abstract public function fileSize($path);

	//--------------------------------------------------------------------------
	//! Put a local file to target
	//--------------------------------------------------------------------------
	abstract public function putFile($source, $destination, $mode = 0777);

	//--------------------------------------------------------------------------
	//! Get a file from target and save to a local file
	//--------------------------------------------------------------------------
	abstract public function getFile($source, $destination, $mode = 0777);

	//--------------------------------------------------------------------------
	//! Put a local file to target and also send a MD5 of the file
	//--------------------------------------------------------------------------
	public function putFileWithMd5($source, $destination, $mode = 0644)
	{
		$this->putFile($source, $destination, $mode = 0644);

		// Also upload a .md5 file with the MD5 checksum
		$md5 = md5_file($source);
		$md5Name = basename($source).".md5";
		$tmpFile = Fs::mkExactTempName($md5Name);
		file_put_contents($tmpFile, $md5);
		$this->putFile($tmpFile, $destination.".md5", $mode);
		Fs::unlink($tmpFile);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function getFileMd5($path)
	{
		try {
			$md5Path = $path.".md5";
			if (!$this->getFileToTemp($md5Path, $tmpFName))
				return "";

			$md5 = file_get_contents($tmpFName);
			Fs::unlink($tmpFName);
			return $md5;
		}
		catch (Exception $e) {
			return "";
		}
	}

	//------------------------------------------------------------------------------
	//! Get a file from target and save to a local temporary file
	//------------------------------------------------------------------------------
	public function getFileToTemp($path, &$tmpFName)
	{
		$tmpFName = Fs::mkExactTempName(basename($path));
		if ($this->exists($path)) {
			$this->getFile($path, $tmpFName);
			return TRUE;
		}
		else
			return FALSE;
	}

	//--------------------------------------------------------------------------
	//! Tell whether the target filesystem supports (symbolic) links
	//--------------------------------------------------------------------------
	abstract public function supportsLinks();

	//--------------------------------------------------------------------------
	//! Tell whether $path is a (symbolic) link
	//--------------------------------------------------------------------------
	abstract public function isLink($path);

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	abstract public function readLink($path);

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	abstract public function symlink($path, $target);

	//--------------------------------------------------------------------------
	//! Unlink (delete) a file from target
	//--------------------------------------------------------------------------
	abstract public function unlink($path);
};

