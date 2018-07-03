<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Job class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Generic job
//------------------------------------------------------------------------------
abstract class Job
{
	const DEFAULT_PHASE = 5;
	const DEFAULT_RECURRENCE = "daily";
	const DEFAULT_AT_MONTHLY = "05, 03:30";		// Off daylight savings switch time
	const DEFAULT_AT_WEEKLY = "Sun, 03:30";		// Off daylight savings switch time
	const DEFAULT_AT_DAILY = "03:30";			// Off daylight savings switch time
	const DEFAULT_AT_HOURLY = ":30";

	public static $weekdays = array(
			"sunday"    => 0,
			"sun"       => 0,
			"monday"    => 1,
			"mon"       => 1,
			"tuesday"   => 2,
			"tue"       => 2,
			"wednesday" => 3,
			"wed"       => 3,
			"thursday"  => 4,
			"thu"       => 4,
			"friday"    => 5,
			"fri"       => 5,
			"saturday"  => 6,
			"sat"       => 6,
		);

	protected $conf = NULL;
	protected $enabled = NULL;
	protected $phase = NULL;
	protected $name;
	protected $recurrence;
	protected $at;
	protected $preCmd = NULL;
	protected $postCmd = NULL;

	protected $thisRunDt;
	protected $ts;
	protected $statusDir;

	protected $jobStatus;

	//--------------------------------------------------------------------------
	//! Instantiate a new job type
	//--------------------------------------------------------------------------
	public static function instantiateJob($class, JobConf $conf)
	{
		if (!class_exists($class))
			throw new \Exception("Not found: $class");

		return new $class($conf);
	}

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		$this->conf = $conf;

		$this->enabled = (bool) $this->conf->get('job', 'enabled', TRUE);
		$this->name = $this->conf->getRequired('job', 'name');
		$this->phase = $this->conf->get('job', 'phase', self::DEFAULT_PHASE);
		$this->preCmd = $this->conf->get('job', 'pre_cmd');
		$this->postCmd = $this->conf->get('job', 'post_cmd');

		$this->recurrence = $this->conf->get('job', 'recurrence', self::DEFAULT_RECURRENCE);
		switch ($this->recurrence) {
			case "daily":
			case "weekly":
			case "monthly":
			case "hourly":
				break;

			default:
				$this->conf->error("Unsupported recurrence: {$this->recurrence}");
		}

		if (!$this->enabled)
			return;

		// Prepare status directory
		$this->statusDir = Main::$statusDir."/".$this->name;
		if (!is_dir($this->statusDir))
			Fs::mkdir($this->statusDir);

		// Calculate date/time of next run
		$this->thisRunDt = $this->getNextRunDt();
	}

	//--------------------------------------------------------------------------
	//! Return TRUE if this job is enabled
	//--------------------------------------------------------------------------
	public function isEnabled()
	{
		return $this->enabled;
	}

	//--------------------------------------------------------------------------
	//! Return the job phase (a number)
	/*! The phase is used to sort the jobs. */
	//--------------------------------------------------------------------------
	public function getPhase()
	{
		return $this->phase;
	}

	//--------------------------------------------------------------------------
	//! Return the job name
	//--------------------------------------------------------------------------
	public function getName()
	{
		return $this->name;
	}

	//--------------------------------------------------------------------------
	//! Return the name of the file where the job status is stored
	//--------------------------------------------------------------------------
	public function getJobStatusFname()
	{
		return $this->statusDir."/job.status";
	}

	//--------------------------------------------------------------------------
	//! Calculate and return the date-time of next run
	//--------------------------------------------------------------------------
	public function getNextRunDt()
	{
		// No next run if job is disabled
		if (!$this->enabled)
			return NULL;

		// Calculate date/time of next run
		$now = new \DateTime("now");
		switch ($this->recurrence) {
			case "daily":
				$this->at = trim($this->conf->get('job', 'at', self::DEFAULT_AT_DAILY));
				if (!preg_match('/^([0-9]+):([0-9]+)$/', $this->at, $regs))
					$this->conf->error("Invalid value for 'at': {$this->at}");

				$hour = (int) $regs[1];
				$minute = (int) $regs[2];
				$nextRunDt = clone $now;
				$nextRunDt->setTime($hour, $minute);
				break;

			case "weekly":
				$this->at = trim($this->conf->get('job', 'at', self::DEFAULT_AT_WEEKLY));
				if (!preg_match('/^([a-z]+) *, *([0-9]+):([0-9]+)$/i', $this->at, $regs))
					$this->conf->error("Invalid value for 'at': '{$this->at}'");

				$wdName = strtolower($regs[1]);
				if (!isset(self::$weekdays[$wdName]))
					$this->conf->error("Invalid weekday name: ".$regs[1]);
				$weekday = self::$weekdays[$wdName];

				$hour = (int) $regs[2];
				$minute = (int) $regs[3];
				$nextRunDt = clone $now;
				$nextRunDt->setTime($hour, $minute);

				while ((int) $nextRunDt->format('w') != $weekday)
					$nextRunDt = $nextRunDt->add(new \DateInterval("P1D"));

				break;

			case "monthly":
				$this->at = trim($this->conf->get('job', 'at', self::DEFAULT_AT_MONTHLY));
				if (!preg_match('/^([0-9]+) *, *([0-9]+):([0-9]+)$/i', $this->at, $regs))
					$this->conf->error("Invalid value for 'at': '{$this->at}'");

				$day = (int) $regs[1];
				if ($day < 1 || $day > 31)
					$this->conf->error("Invalid day: ".$day);

				$hour = (int) $regs[2];
				$minute = (int) $regs[3];
				$nextRunDt = clone $now;
				$nextRunDt->setTime($hour, $minute);

				while ((int) $nextRunDt->format('d') != $day)
					$nextRunDt = $nextRunDt->add(new \DateInterval("P1D"));

				break;

			case "hourly":
			default:
				$this->conf->error("Unsupported recurrence: {$this->recurrence}");
		}

		return $nextRunDt;
	}

	//--------------------------------------------------------------------------
	//! Create a new job status object
	//--------------------------------------------------------------------------
	protected function newJobStatus($statusFname)
	{
		return new JobStatus($statusFname);
	}

	//--------------------------------------------------------------------------
	//! Load the status file and return a JobStatus object
	//--------------------------------------------------------------------------
	public function getJobStatus()
	{
		if ($this->jobStatus)
			return $this->jobStatus;

		$statusFname = $this->getJobStatusFname();
		$this->jobStatus = JobStatus::load($statusFname);
		if (!$this->jobStatus)
			$this->jobStatus = $this->newJobStatus($statusFname);

		return $this->jobStatus;
	}

	//--------------------------------------------------------------------------
	//! Run this job
	//--------------------------------------------------------------------------
	public final function run()
	{
		if (!$this->enabled)
			return;

		$this->log(LOG_DEBUG, "Running job: {$this->name}");

		// Read the job status from file
		$jobStatus = $this->getJobStatus();

		if ($jobStatus->lastAttemptedRunDt)
			$this->log(LOG_DEBUG, "Last attempted run: ".$jobStatus->lastAttemptedRunDt->format('Y-m-d H:i:s'));

		$this->log(LOG_DEBUG, "This run: ".$this->thisRunDt->format('Y-m-d H:i:s'));

		$now = new \DateTime("now");
		if (Main::$force) {
			$this->log(LOG_DEBUG, "Job execution forced");
		}
		elseif ($jobStatus->lastAttemptedRunDt
//		        && ($jobStatus->lastRunOk === TRUE)
		        && ($jobStatus->lastAttemptedRunDt >= $this->thisRunDt)) {
			// Already run for this cycle, skip
			$this->log(LOG_DEBUG, "Already run for this cycle");
			return;
		}
		elseif ($now < $this->thisRunDt) {
			// Not yet time
			$this->log(LOG_DEBUG, "Not yet time");
			return;
		}

		// Update last attempted run
		$jobStatus->lastAttemptedRunDt = $now;

		// Run the pre command.
		// If it fails, the backup job aborts here
		if ($this->preCmd) {
			$this->log(LOG_INFO, "Running pre_cmd: $this->preCmd");
			try {
				$result = Shell::exec($this->preCmd);
				if ($result)
					$this->log(LOG_INFO, $result);
			}
			catch (\Exception $e) {
				$this->log(LOG_ERR, $e->getMessage());
				$msg = "pre_cmd failed: ".$e->getMessage();
				$this->log(LOG_ERR, "pre_cmd failed, job execution skipped");

				$jobStatus->lastRunOk = FALSE;
				$jobStatus->lastRunErrorMsg = $msg;
				$jobStatus->save();
				return TRUE;
			}
		}

		// Run the job
		try {
			// Run the actual job
			$this->doRun();

			// Execution successful
			$jobStatus->lastRunDt = $now;
			$jobStatus->lastRunOk = TRUE;
			$jobStatus->lastRunErrorMsg = NULL;
		}
		catch (\Exception $e) {
			$jobStatus->lastRunOk = FALSE;
			$jobStatus->lastRunErrorMsg = $e->getMessage();
			$this->log(LOG_ERR, $e->getMessage());
		}

		// Run the post command (even if the backup job fails)
		if ($this->postCmd) {
			$this->log(LOG_INFO, "Running post_cmd: $this->preCmd");
			try {
				$result = Shell::exec($this->postCmd);
				if ($result)
					$this->log(LOG_INFO, $result);
			}
			catch (\Exception $e) {
				$this->log(LOG_ERR, $e->getMessage());
				$this->log(LOG_ERR, "post_cmd failed");
			}
		}

		// Save the job status to file
		$jobStatus->save();
		return TRUE;
	}

	//--------------------------------------------------------------------------
	//! Return the status array
	//--------------------------------------------------------------------------
	public function getStatusArray()
	{
		$s = array();
		$s['Job name'] = $this->name;
		if ($this->enabled) {
			$s['Enabled'] = 'YES';
			$s['Next run'] = $this->thisRunDt->format('Y-m-d H:i:s');
			$jobStatus = $this->getJobStatus();
			$s1 = $jobStatus->getStatusArray();
			$s = array_merge($s, $s1);
		}
		else
			$s['Enabled'] = 'NO';

		return $s;
	}

	//--------------------------------------------------------------------------
	//! Produce a log message
	//--------------------------------------------------------------------------
	protected function log($level, $msg)
	{
		Log::submit($level, $msg, $this->logContext());
	}

	//--------------------------------------------------------------------------
	//! Return context for log messages
	//--------------------------------------------------------------------------
	abstract protected function logContext();

	//--------------------------------------------------------------------------
	//! Returns the job type
	//--------------------------------------------------------------------------
	abstract public function getType();

	//--------------------------------------------------------------------------
	//! The actual job run method
	//--------------------------------------------------------------------------
	abstract protected function doRun();
};
