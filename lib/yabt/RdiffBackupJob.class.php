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

	/*
	time_spec can be either an absolute time, like "2002-01-04", or a time interval.  The
		time interval is an integer followed by the character s, m, h, D, W, M, or Y,  indicating  seconds,  min‐
		utes, hours, days, weeks, months, or years respectively, or a number of these concatenated.  For example,
		32m means 32 minutes, and 3W2D10h7s means 3 weeks, 2 days, 10 hours, and 7 seconds.  In this  context,  a
		month means 30 days, a year is 365 days, and a day is always 86400 seconds.*/

	private $source;
	private $destination;
	private $retentionPeriod;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		parent::__construct($conf);

		$this->source = $this->conf->getRequired(self::SECTION, 'source');
		$this->destination = $this->conf->getRequired(self::SECTION, 'destination');
		$this->destination = $this->destination.$this->getSubdir();
		$this->retentionPeriod = $this->conf->get(self::SECTION, 'retention_period', self::DEFAULT_RETENTION_PERIOD);
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
		$exe = "/usr/bin/rdiff-backup";

		// Prepare and run command
		$cmd = sprintf("%s %s %s",
					   $exe,
					   escapeshellarg($this->source),
					   escapeshellarg($this->destination));
		$this->log(LOG_DEBUG, "Running: $cmd");
		Shell::exec($cmd);

		$this->log(LOG_INFO, "Created new backup");

		// Run cleanup command
		$cmd = sprintf("%s --remove-older-than %s %s",
					   $exe,
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
