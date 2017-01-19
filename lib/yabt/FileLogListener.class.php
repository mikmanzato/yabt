<?php
//------------------------------------------------------------------------------
/* 	Yabt

	FileLogListener class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Log listener which stores logs to file
//------------------------------------------------------------------------------
class FileLogListener
	extends LogListener
{
	//! [string] Name of the logfile
	private $logFileName = "/var/log/yabt/yabt.log";

	//! [resource] Logfile handle
	private $fp = NULL;

	//------------------------------------------------------------------------------
	//! Set the logfile to use
	//------------------------------------------------------------------------------
	public function setLogFile($fileName)
	{
		$this->logFileName = $fileName;
	}

	//------------------------------------------------------------------------------
	//! Open the logfile for appending
	//------------------------------------------------------------------------------
	private function openLogFile()
	{
		if (!is_resource($this->fp))
			$this->fp = @fopen($this->logFileName, "a");
	}

	//------------------------------------------------------------------------------
	//! Log a new message
	/*! \param $level [int] The log level. One among log levels defined for
			the syslog() PHP function.
		\param $ts [string] The message timestamp
		\param $session [string] The run session ID
		\param $msg [string] The message to log.
		\param $context [string] Context where the log message has been produced. */
	//------------------------------------------------------------------------------
	public function submit($level, $ts, $session, $msg, $context = "")
	{
		if ($level > $this->minLogLevel)
			return;

		$this->openLogFile();
		if ($context)
			$msg = "$context: $msg";
		if (is_resource($this->fp)) {
			fprintf($this->fp,
					"%s [%s] %04d: %s\n",
					$ts,
					Log::getLevelStr($level),
					$session,
					$msg);
			fflush($this->fp);
		}
	}
};
