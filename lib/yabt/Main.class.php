<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Main class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Main program class
//------------------------------------------------------------------------------
abstract class Main
{
	const VERSION="1.1.3";

	public static $confDir = NULL;
	public static $varDir = '/var';
	public static $lockDir = NULL;
	public static $jobsConfDir = NULL;
	public static $mainConf = array();
	public static $jobQueue = NULL;
	public static $statusDir = NULL;
	public static $lockFile = NULL;
	public static $force = NULL;

	public static $jobName = NULL;
	public static $cmd = NULL;

	protected static $consoleLogLevel = 6;
	protected static $verbose = FALSE;
	protected static $disableReport = FALSE;

	//--------------------------------------------------------------------------
	//! Initialize global parameters
	//--------------------------------------------------------------------------
	protected static function init()
	{
		global $argv;

		// Parse command-line parameters
		array_shift($argv);	// skip 0
		while (!empty($argv)) {
			$arg = array_shift($argv);
			switch ($arg) {
				case '-c':
				case '--condfdir':
					self::$confDir = array_shift($argv);
					if (!self::$confDir)
						throw new \Exception("Missing parameter to -c/--confdir option");
					break;

				case '-d':
				case '--disable-report':
					self::$disableReport = TRUE;
					break;

				case '-f':
				case '--force':
					self::$force = TRUE;
					break;

				case '-j':
				case '--job':
					self::$jobName = array_shift($argv);
					if (!self::$jobName)
						throw new \Exception("Missing parameter to -j/--job option");
					break;

				case '-l':
				case '--loglevel':
					$logLevel = (int) array_shift($argv);
					if (($logLevel < LOG_EMERG) || ($logLevel > LOG_DEBUG))
						throw new \Exception("Invalid log level: $logLevel");

					self::$consoleLogLevel = $logLevel;
					self::$verbose = TRUE;
					break;

				case '-v':
				case '--verbose':
					self::$verbose = TRUE;
					break;

				case '--version':
					echo self::VERSION."\n";
					exit(0);

				case '--vardir':
					self::$varDir = array_shift($argv);
					if (!self::$varDir)
						throw new \Exception("Missing parameter to --vardir option");
					break;

				case 'execute':
					self::$cmd = 'execute';
					break;

				case 'status':
					self::$cmd = 'status';
					break;

				case 'notify-status':
					self::$cmd = 'notify-status';
					break;

				default:
					throw new \Exception("Unexpected command-line argument: $arg");
			}
		}

		// Default command is 'execute'
		if (!self::$cmd)
			self::$cmd = 'execute';

		if (!self::$confDir) {
			// Set the configuration directory
			if (dirname(__FILE__) == '/usr/share/yabt/yabt')
				self::$confDir = "/etc/yabt";
			elseif (dirname(__FILE__) == '/usr/local/share/yabt/yabt')
				self::$confDir = "/usr/local/etc/yabt";
			else
				throw new \Exception("Can't locate configuration directory, use -c/--confdir parameter");
		}

		// Location of job configuration files
		self::$jobsConfDir = Fs::joinPath(self::$confDir, "jobs.d");

		// Lock directory
		self::$lockDir = Fs::joinPath(self::$varDir, "/lock");
		if (!is_dir(self::$lockDir))
			Fs::mkdir(self::$lockDir, 0777, TRUE);

		// Lock file
		self::$lockFile = Fs::joinPath(self::$lockDir, "/yabt.lock");

		// Status directory
		self::$statusDir = Fs::joinPath(self::$varDir, "/lib/yabt");
		if (!is_dir(self::$statusDir))
			Fs::mkdir(self::$statusDir, 0777, TRUE);

		// Read main configuration
		$mainConfFile = Fs::joinPath(self::$confDir, "main.conf");
		MainConf::load($mainConfFile);

		// Load all jobs into a queue
		self::$jobQueue = JobQueue::load(self::$jobsConfDir);
	}

	//--------------------------------------------------------------------------
	//! Run all the jobs
	//--------------------------------------------------------------------------
	protected static function runJobs()
	{
		Log::submit(LOG_DEBUG, "Running all jobs");
		self::$jobQueue->runAll();
	}

	//--------------------------------------------------------------------------
	//! Run a single job
	//--------------------------------------------------------------------------
	protected static function runJob($name)
	{
		$job = self::$jobQueue->getJobByName($name);
		if (!$job)
			throw new \Exception("Job not found: $name");

		Log::submit(LOG_DEBUG, "Running job: $name");
		$job->run();
	}

	//--------------------------------------------------------------------------
	//! Run a single job or all jobs
	//--------------------------------------------------------------------------
	protected static function wrappedExecute()
	{
		if (self::$jobName)
			self::runJob(self::$jobName);
		else
			self::runJobs();
	}

	//--------------------------------------------------------------------------
	//! Run the job, using a lock to guarantee unique execution
	//--------------------------------------------------------------------------
	protected static function execute()
	{
		$emailLogListener = NULL;
		$mainConf = MainConf::getGlobal();

		// Initialize console logging
		if (self::$verbose) {
			$listener = new ConsoleLogListener();
			$listener->setMinLogLevel(self::$consoleLogLevel);
			Log::attachListener($listener);
		}

		// Initialize email logging
		$emailLogListener = new EmailLogListener();
//		$emailLogListener->setMinLogLevel(6);	// DEBUG
		Log::attachListener($emailLogListener);

		// Initialize file logging
		$listener = new FileLogListener();
		if (!is_null($s = $mainConf->get("log", "file")))
			$listener->setLogFile($s);
		if (!is_null($s = $mainConf->get("log", "min_level")))
			$listener->setMinLogLevel($s);
		Log::attachListener($listener);

		// Use lock to guarantee unique execution
		Log::submit(LOG_DEBUG, "***** Start *****");
		$fp = Fs::fopen(self::$lockFile, "w");
		if (flock($fp, LOCK_EX | LOCK_NB)) {
			self::wrappedExecute();

			// Release and remove lock
			fclose($fp);
			@unlink(self::$lockFile);
		}
		else {
			Log::submit(LOG_DEBUG, "Yabt already running, skipping.");
			fclose($fp);
		}

		Log::submit(LOG_DEBUG, "***** End *****");

		// Send notifications by email
		if (Mailer::notificationsEnabled()) {
			if (self::$disableReport) {
				Log::submit(LOG_DEBUG, "Report email disabled.");
			}
			else {
				$messages = $emailLogListener->getMessages();
				if (!empty($messages)) {
					$messages = implode("\n", $messages);
					$fqdn = System::getFqdn();
					$body = "<!DOCTYPE html>
<html>
<style type=\"text/css\">
* { font-family: sans-serif; }
h2 { font-size: 130%; }
</style>
<body>
<p>Hello,</p>
<p>This is the report of the backup jobs configured on {$fqdn}:</p>
<pre>
{$messages}
</pre>
<p>Please take appropriate actions if necessary.</p>
<p>Regards</p>
<p>Yabt on {$fqdn}</p>
</body>
</html>";
					$subject = "Backup report";
					try {
						Mailer::sendNotification($subject, $body);
						Log::submit(LOG_DEBUG, "Report email sent.");
					}
					catch (Exception $e) {
						Log::submit(LOG_WARNING, "Failed to send report email.");
					}
				}
			}
		}
	}

	//--------------------------------------------------------------------------
	//! Display job status to console
	//--------------------------------------------------------------------------
	public function displayStatus(array $status, $s = '')
	{
		foreach ($status as $k => $v) {
			if (is_array($v)) {
				Console::displayMsg("{$s}{$k}:");
				self::displayStatus($v, "{$s}  ");
			}
			else
				Console::displayMsg("{$s}{$k}: {$v}");
		}
	}

	//--------------------------------------------------------------------------
	//! Display job status
	//--------------------------------------------------------------------------
	public static function status()
	{
		if (self::$jobName) {
			$job = self::$jobQueue->getJobByName(self::$jobName);
			if (!$job)
				throw new \Exception("Job not found: ".self::$jobName);

			$status = $job->getStatusArray();
		}
		else
			$status = self::$jobQueue->getStatusArray();

		self::displayStatus($status);
	}

	//--------------------------------------------------------------------------
	//! Main program
	//--------------------------------------------------------------------------
	public static function run()
	{
		try {
			self::init();
		}
		catch (\Exception $e) {
			Console::displayError($e->getMessage());
			exit(1);
		}

		try {
			switch (self::$cmd) {
				case 'execute':
					self::execute();
					break;

				case 'status':
					self::status();
					break;
			}
		}
		catch (\Exception $e) {
			Console::displayError($e->getMessage());
			Log::submit(LOG_ERR, $e->getMessage());
			exit(1);
		}
	}
};

