<?php
//------------------------------------------------------------------------------
/* 	Yabt

	DuplicityJob class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Duplicity backup job
/*! Need to set up:
	* ssh key
	* gpg signature key */
//------------------------------------------------------------------------------
class DuplicityJob
	extends BackupJob
{
	const SECTION = 'duplicity';
	const DEFAULT_RETENTION_PERIOD = 60;
	const DEFAULT_FULL_PERIOD = 15;

	private $sourceDir;
	private $targetUrl;
	private $retentionPeriod;
	private $fullPeriod;
	private $passphrase;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		parent::__construct($conf);

		$this->sourceDir = $this->conf->getRequired(self::SECTION, 'source_dir');
		$this->targetUrl = $this->conf->getRequired(self::SECTION, 'target_url');
		$this->targetUrl = Fs::joinPath($this->targetUrl, $this->getSubdir());
		$this->retentionPeriod = (int) $this->conf->get(self::SECTION, 'retention_period', self::DEFAULT_RETENTION_PERIOD);
		$this->fullPeriod = (int) $this->conf->get(self::SECTION, 'full_period', self::DEFAULT_FULL_PERIOD);
		$this->passphrase = $this->conf->getRequired(self::SECTION, 'passphrase');
	}

	//--------------------------------------------------------------------------
	//! Return context for log messages
	//--------------------------------------------------------------------------
	protected function logContext()
	{
		return "duplicity[{$this->name}]";
	}

	//--------------------------------------------------------------------------
	//! Create a new job status object
	//--------------------------------------------------------------------------
	protected function newJobStatus($statusFname)
	{
		return new DuplicityJobStatus($statusFname);
	}

	//------------------------------------------------------------------------------
	//! Run the job
	//------------------------------------------------------------------------------
	protected function doRun()
	{
		$exe = "/usr/bin/duplicity";

		// Read the status file
		$sequence = $this->jobStatus->sequence;
		if (!$sequence)
			$sequence = new Sequence();
		else
			$sequence = $sequence->next($this->fullPeriod);

		// Prepare and run command
		$type = ($sequence->isFirst() ? "full" : "incremental");
		putenv("PASSPHRASE={$this->passphrase}");
//		$cmd = sprintf("duplicity %s %s %s",
		$cmd = sprintf("%s %s %s %s 2>&1",
					   $exe,
					   $type,
					   escapeshellarg($this->sourceDir),
					   escapeshellarg($this->targetUrl));

		$this->log(LOG_DEBUG, "Running: $cmd");
		Shell::exec($cmd);

		$this->log(LOG_INFO, "Created new $type backup (sequence: ".$sequence->get().")");

		// Run cleanup command
		$n = $this->retentionPeriod / $this->fullPeriod;
		$cmd = sprintf("%s remove-all-but-n-full %d --force %s",
					   $exe,
					   $n,
					   escapeshellarg($this->targetUrl));

		$this->log(LOG_INFO, "Cleaning old backups");
		$this->log(LOG_DEBUG, "Running: $cmd");
		$result = Shell::exec($cmd);
		$this->log(LOG_INFO, $result);

		// Save sequence
		$this->jobStatus->sequence = $sequence;

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
