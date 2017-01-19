<?php
//------------------------------------------------------------------------------
/* 	Yabt

	LocalTargetFs class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//! Exception for fs-related errors
class LocalTargetFsException extends TargetFsException {}


//------------------------------------------------------------------------------
//! Target FS located on the local server
//------------------------------------------------------------------------------
class LocalTargetFs
	extends TargetFs
{
	//! The root path
	protected $path;

	//--------------------------------------------------------------------------
	//! Factory method
	//--------------------------------------------------------------------------
	public static function build($location, $subdir)
	{
		$pattern = '|^file://(.*)$|';
		if (preg_match($pattern, $location, $regs))
			return new LocalTargetFs($regs[1], $subdir);
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	protected function __construct($path, $subdir)
	{
		parent::__construct($subdir);
		$this->path = $path;

		$p = $this->_realPath(".");
		if (!file_exists($p))
			Fs::mkdir($p, 0777, TRUE);
	}

	//--------------------------------------------------------------------------
	//! Produce the actual path
	//--------------------------------------------------------------------------
	protected function _realPath($path)
	{
		return Fs::joinPath(Fs::joinPath($this->path, $this->subdir), $path);
	}

	//--------------------------------------------------------------------------
	//! Create a directory, throw an exception upon failure
	//--------------------------------------------------------------------------
	public function mkdir($path, $mode = 0777, $recursive = FALSE)
	{
		$realPath = $this->_realPath($path);
		Fs::mkdir($realPath, $mode, $recursive);
	}

	//--------------------------------------------------------------------------
	//! Return the available space at the location $path
	/*! \param $path [string] The path to check
		\returns (double) The available number of bytes. Note that the result is
			in double format in order to support wide partition sizes. */
	//--------------------------------------------------------------------------
	public function getAvailableSpace($path)
	{
		return Fs::getAvailableSpace($path);
	}

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern
	//--------------------------------------------------------------------------
	public function listFiles($path, $pattern = NULL)
	{
		$realPath = $this->_realPath($path);
		$entries = array();
		$dir = opendir($realPath);
		while ($entry = readdir($dir)) {
			if (!$pattern || preg_match($pattern, $entry))
				$entries[] = $entry;
		}
		closedir($dir);
		return $entries;
	}

	//--------------------------------------------------------------------------
	//! Tells whether file exists
	//--------------------------------------------------------------------------
	public function exists($path)
	{
		$realPath = $this->_realPath($path);
		return file_exists($realPath);
	}

	//--------------------------------------------------------------------------
	//! Get size of file $path
	//--------------------------------------------------------------------------
	public function fileSize($path)
	{
		$realPath = $this->_realPath($path);
		return filesize($realPath);
	}

	//--------------------------------------------------------------------------
	//! Put a local file to target
	//--------------------------------------------------------------------------
	public function putFile($source, $destination, $mode = 0777)
	{
		$realPath = $this->_realPath($destination);
		Log::submit(LOG_DEBUG, "Copying '$source' to '$realPath'");
		copy($source, $realPath);
	}

	//--------------------------------------------------------------------------
	//! Get a file from target and save to a local file
	//--------------------------------------------------------------------------
	public function getFile($source, $destination, $mode = 0777)
	{
		$realPath = $this->_realPath($source);
		Log::submit(LOG_DEBUG, "Copying '$realPath' to '$destination'");
		copy($realPath, $destination);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function supportsLinks()
	{
		return TRUE;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function isLink($path)
	{
		$realPath = $this->_realPath($path);
		return Fs::isLink($realPath);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function readLink($path)
	{
		$realPath = $this->_realPath($path);
		return readlink($realPath);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function symlink($path, $target)
	{
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "Local: Symlink $realPath --> $target");
		symlink($target, $realPath);

		$md5RealPath = $realPath.".md5";
		$md5Target = $target.".md5";
		Log::submit(LOG_DEBUG, "Local: Symlink $md5RealPath --> $md5Target");
		symlink($md5Target, $md5RealPath);
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function unlink($path)
	{
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "Deleting: '$realPath'");
		Fs::unlink($realPath);

		// Also delete the related .md5 file, if it exists
		$md5RealPath = $realPath.".md5";
		Log::submit(LOG_DEBUG, "Deleting: '$md5RealPath'");
		Fs::unlink($md5RealPath);
	}
};

