<?php
//------------------------------------------------------------------------------
/* 	Yabt

	FtpTargetFs class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//! Exception for fs-related errors
class FtpTargetFsException extends TargetFsException {}


//------------------------------------------------------------------------------
//! Target FS located on a FTP server
/*! Supports both FTP and FTPS.

	To configure FTPS on proftpd: https://www.howtoforge.com/tutorial/install-proftpd-with-tls-on-ubuntu-16-04/
*/
//------------------------------------------------------------------------------
class FtpTargetFs
	extends TargetFs
{
	protected $secure;
	protected $username;
	protected $password;
	protected $hostname;
	protected $port;
	protected $path;

	protected $connection = FALSE;

	//--------------------------------------------------------------------------
	//! Factory method
	//--------------------------------------------------------------------------
	public static function build($location, $subdir)
	{
		$pattern = '|^ftp(s?)://(\w+):(\w+)@([\w-_.]+)(:(\d+))?/(.*)$|';
		if (preg_match($pattern, $location, $regs)) {
			$secure = ($regs[1] == 's');
			$username = $regs[2];
			$password = $regs[3];
			$hostname = $regs[4];
			$port = $regs[5] ? (int) $regs[5] : NULL;
			$path = $regs[7];
			return new FtpTargetFs($secure, $username, $password, $hostname, $port, $path, $subdir);
		}
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	protected function __construct($secure, $username, $password, $hostname, $port, $path, $subdir)
	{
		parent::__construct($subdir);

		$this->secure = (bool) $secure;
		$this->username = $username;
		$this->password = $password;
		$this->hostname = $hostname;
		$this->port = $port ? $port : 21;
		$this->path = $path;
	}

	//--------------------------------------------------------------------------
	//! Connect to remote host
	//--------------------------------------------------------------------------
	protected function _connect()
	{
		if (!$this->connection) {
			if ($this->secure)
				$connection = @ftp_ssl_connect($this->hostname, $this->port);
			else
				$connection = @ftp_connect($this->hostname, $this->port);

			if (!$connection)
				throw new FtpTargetFsException("FTP connection to {$this->hostname}:{$this->port} failed: ".error_get_last()['message']);

			$result = ftp_login($connection, $this->username, $this->password);
			if (!$result)
				throw new FtpTargetFsException("FTP Authentication failed while connecting to {$this->hostname}:{$this->port} with user '{$this->username}'");

			$this->connection = $connection;

			// Create destination directory
			$d = $this->_realPath(".");
			$this->_mkdir($d, TRUE);
		}
	}

	//--------------------------------------------------------------------------
	//! Produce the actual path
	//--------------------------------------------------------------------------
	protected function _realPath($path)
	{
		//~ echo __method__."('$path')\n";
		//~ var_dump($this->path);
		//~ var_dump($this->subdir);
		//~ var_dump($path);
		$p = self::joinPath(self::joinPath($this->path, $this->subdir), $path);
		//~ var_dump($p);
		return $p;
	}

	//--------------------------------------------------------------------------
	//! Create directory on the FTP server
	//--------------------------------------------------------------------------
	protected function _mkdir($path, $recursive = FALSE)
	{
		if (!$this->connection)
			throw new FtpTargetFsException("FTP Not connected");

		//~ static $i = 0;
		//~ echo __method__."('$path')\n";
		//~ if ($i++ == 10) die();
		if ($path == '.')
			return;

		$d = dirname($path);
		if ($recursive)
			$this->_mkdir($d, TRUE);

		$fnames = ftp_nlist($this->connection, $d);
		if ($fnames === FALSE)
			throw new FtpTargetFsException("FTP failed to get directory listing: $d");

		$n = basename($path);
		if (!in_array($path, $fnames)) {
			Log::submit(LOG_DEBUG, "FTP: Making directory: $path");
			$result = ftp_mkdir($this->connection, $path);
			if (!$result)
				throw new FtpTargetFsException("FTP failed to create directory: $path");
		}
	}

	//--------------------------------------------------------------------------
	//! Create a directory, throw an exception upon failure
	//--------------------------------------------------------------------------
	public function mkdir($path, $mode = 0777, $recursive = FALSE)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		$this->_mkdir($path, $recursive);
	}

	//--------------------------------------------------------------------------
	//! Return the available space at the location $path
	/*! \param $path [string] The path to check
		\returns (double) The available number of bytes. Note that the result is
			in double format in order to support wide partition sizes. */
	//--------------------------------------------------------------------------
	public function getAvailableSpace($path)
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//! List files at the remote destination $path whose names match $pattern
	//--------------------------------------------------------------------------
	public function listFiles($path, $pattern = NULL)
	{
		$this->_connect();
//		echo __method__."(): '$path', '$pattern'\n";
		$realPath = $this->_realPath($path);
//		echo "real path: $realPath\n";

		$fnames = ftp_nlist($this->connection, $realPath);
		if ($fnames === FALSE)
			throw new FtpTargetFsException("FTP: Can't get directory listing for: $path");
		$entries = array();
		foreach ($fnames as $fname) {
			$entry = basename($fname);
			if (!$pattern || preg_match($pattern, $entry))
				$entries[] = $entry;
		}
//		var_dump($entries);
		return $entries;
	}

	//--------------------------------------------------------------------------
	//! Tells whether file exists
	//--------------------------------------------------------------------------
	public function exists($path)
	{
		$this->_connect();
		$d = dirname($path);
		$n = basename($path);
		$realPath = $this->_realPath($d);
		$realPathN = $this->_realPath($path);
		$fnames = ftp_nlist($this->connection, $realPath);
		if ($fnames === FALSE)
			throw new FtpTargetFsException("FTP: Can't get directory listing for: $path");

		return in_array($realPathN, $fnames);
	}

	//--------------------------------------------------------------------------
	//! Get size of file $path
	//--------------------------------------------------------------------------
	public function fileSize($path)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		return ftp_size($this->connection, $realPath);
	}

	//--------------------------------------------------------------------------
	//! Put a local file to target
	//--------------------------------------------------------------------------
	public function putFile($source, $destination, $mode = 0777)
	{
		$this->_connect();
		$realPath = $this->_realPath($destination);
		Log::submit(LOG_DEBUG, "FTP: Copying '$source' to '$realPath'");

		if (!ftp_put($this->connection, $realPath, $source, FTP_BINARY))
			throw new FtpTargetFsException("FTP failed to PUT file to remote $realPath");
	}

	//--------------------------------------------------------------------------
	//! Get a file from target and save to a local file
	//--------------------------------------------------------------------------
	public function getFile($source, $destination, $mode = 0777)
	{
		$this->_connect();
		$realPath = $this->_realPath($source);
		Log::submit(LOG_DEBUG, "Copying '$realPath' to '$destination'");

		if (!ftp_get($this->connection, $destination, $realPath, FTP_BINARY))
			throw new FtpTargetFsException("FTP failed to GET remote file $realPath");
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function supportsLinks()
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function isLink($path)
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function readLink($path)
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function symlink($path, $target)
	{
		return FALSE;
	}

	//--------------------------------------------------------------------------
	//!
	//--------------------------------------------------------------------------
	public function unlink($path)
	{
		$this->_connect();
		$realPath = $this->_realPath($path);
		Log::submit(LOG_DEBUG, "FTP: Deleting: $realPath");

		if (!ftp_delete($this->connection, $realPath))
			throw new FtpTargetFsException("FTP failed to delete remote file: $realPath");

		// Also delete the related .md5 file, if it exists
		$md5RealPath = $realPath.".md5";
		Log::submit(LOG_DEBUG, "FTP: Deleting: $md5RealPath");
		ftp_delete($this->connection, $md5RealPath);
	}
};

