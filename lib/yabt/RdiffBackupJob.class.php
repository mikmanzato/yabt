<?php
//------------------------------------------------------------------------------
/* 	Yabt

	RdiffBackupJob class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! RdiffBackup backup job
/*! Need to set up:
	* ssh key
	* gpg signature key */
//------------------------------------------------------------------------------
class RdiffBackupJob
	extends BackupJob
{
	const SECTION = 'rdiff-backup';
	const DEFAULT_RETENTION_PERIOD = "2M";
	const DEFAULT_RDIFFBACKUP_EXE = "/usr/bin/rdiff-backup";

	private $rdiffbackupExe;
	private $source;
	private $destination;

	/*	can be either an absolute time, like "2002-01-04", or a time interval.  The
		time interval is an integer followed by the character s, m, h, D, W, M, or Y,  indicating  seconds,  min‐
		utes, hours, days, weeks, months, or years respectively, or a number of these concatenated.  For example,
		32m means 32 minutes, and 3W2D10h7s means 3 weeks, 2 days, 10 hours, and 7 seconds.  In this  context,  a
		month means 30 days, a year is 365 days, and a day is always 86400 seconds.*/
	private $retentionPeriod;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		parent::__construct($conf);

		$this->source = $this->conf->getRequired(self::SECTION, 'source');
		$this->destination = $this->conf->getRequired(self::SECTION, 'destination');
		$this->destination = Fs::joinPath($this->destination, $this->getSubdir());
		$this->retentionPeriod = $this->conf->get(self::SECTION, 'retention_period', self::DEFAULT_RETENTION_PERIOD);
		$this->rdiffbackupExe = $this->conf->get(self::SECTION, 'rdiffbackup_exe', self::DEFAULT_RDIFFBACKUP_EXE);

		if (!file_exists($this->rdiffbackupExe) && !is_executable($this->rdiffbackupExe))
			throw new \Exception("Not found or not executable: ".$this->rdiffbackupExe);
	}

	//--------------------------------------------------------------------------
	//! Return context for log messages
	//--------------------------------------------------------------------------
	protected function logContext()
	{
		return "rdiff-backup[{$this->name}]";
	}

	//------------------------------------------------------------------------------
	//! Run the job
	//------------------------------------------------------------------------------
	protected function doRun()
	{
		// Prepare and run command
		$cmd = sprintf("%s %s %s",
					   $this->rdiffbackupExe,
					   escapeshellarg($this->source),
					   escapeshellarg($this->destination));
		$this->log(LOG_DEBUG, "Running: $cmd");
		Shell::exec($cmd);

		$this->log(LOG_INFO, "Created new backup");

		// Run cleanup command
		$cmd = sprintf("%s --remove-older-than %s %s",
					   $this->rdiffbackupExe,
					   escapeshellarg($this->retentionPeriod),
					   escapeshellarg($this->destination));

		$this->log(LOG_DEBUG, "Cleaning old data");
		$this->log(LOG_DEBUG, "Running: $cmd");
		$result = Shell::exec($cmd);
		$this->log(LOG_INFO, $result);

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
