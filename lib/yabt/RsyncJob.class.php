<?php
//------------------------------------------------------------------------------
/* 	Yabt

	RsyncJob class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Rsync copy job
//------------------------------------------------------------------------------
class RsyncJob
	extends BackupJob
{
	const SECTION = 'rsync';
	const DEFAULT_RSYNC_EXE = "/usr/bin/rsync";

	private $rsyncExe;
	private $source;
	private $destination;
	private $password;			//!< Only for rsync:// protocol

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		parent::__construct($conf);

		$this->source = $this->conf->getRequired(self::SECTION, 'source');
		if (!substr($this->source, -1) != '/')
			$this->source .= '/';
		$this->destination = $this->conf->getRequired(self::SECTION, 'destination');
		$this->destination = $this->destination.$this->getSubdir();
		if (!substr($this->destination, -1) != '/')
			$this->destination .= '/';
		$this->password = $this->conf->get(self::SECTION, 'password');
		$this->rsyncExe = $this->conf->get(self::SECTION, 'rsync_exe', self::DEFAULT_RSYNC_EXE);

		if (!file_exists($this->rsyncExe) && !is_executable($this->rsyncExe))
			throw new \Exception("Not found or not executable: ".$this->rsyncExe);
	}

	//--------------------------------------------------------------------------
	//! Return context for log messages
	//--------------------------------------------------------------------------
	protected function logContext()
	{
		return "rsync[{$this->name}]";
	}

	//------------------------------------------------------------------------------
	//! Run the copy job
	//------------------------------------------------------------------------------
	protected function doRun()
	{
		$cmd = sprintf("%s -aq %s %s",
					   escapeshellarg($this->rsyncExe),
					   escapeshellarg($this->source),
					   escapeshellarg($this->destination));

		$this->log(LOG_DEBUG, "Running: $cmd");

		if ($this->password)
			$cmd = sprintf("RSYNC_PASSWORD=%s %s", escapeshellarg($this->password), $cmd);

		Shell::exec($cmd);

		$this->log(LOG_INFO, "Rsync completed.");
		return TRUE;
	}

	//--------------------------------------------------------------------------
	//! Automated backup verification
	/*! Ensure that backups are healthy */
	//--------------------------------------------------------------------------
	protected function verify()
	{
	}
};
