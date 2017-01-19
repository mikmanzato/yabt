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
		$this->destination = Fs::joinPath($this->destination, $this->getSubdir());
		if (!substr($this->destination, -1) != '/')
			$this->destination .= '/';
		$this->password = $this->conf->get(self::SECTION, 'password');
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
		$exe = "/usr/bin/rsync";

		$cmd = sprintf("%s -aq %s %s",
					   $exe,
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
