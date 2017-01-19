<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Log class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Simple logging facility
//------------------------------------------------------------------------------
abstract class Log
{
	//! [int] Random session number
	private static $session = NULL;

	//! Configured log listeners
	private static $listeners = array();

	//! [array(int => string)] Description of log level
	private static $levelStr = array(
			LOG_EMERG 	=> 'EMG',
			LOG_ALERT 	=> 'ALE',
			LOG_CRIT 	=> 'CRI',
			LOG_ERR 	=> 'ERR',
			LOG_WARNING	=> 'WRN',
			LOG_NOTICE	=> 'NTC',
			LOG_INFO 	=> 'INF',
			LOG_DEBUG 	=> 'DEB',
		);

	//------------------------------------------------------------------------------
	//! Create a new session number, if one isn't already available
	//------------------------------------------------------------------------------
	private static function createSessionNumber()
	{
		if (is_null(self::$session))
			self::$session = rand(1, 9999);
	}

	//------------------------------------------------------------------------------
	//! Return a code corresponding to the log level
	//------------------------------------------------------------------------------
	public static function getLevelStr($level)
	{
		return self::$levelStr[$level];
	}

	//------------------------------------------------------------------------------
	//! Attach a listener to the queue of listeners
	//------------------------------------------------------------------------------
	public static function attachListener(LogListener $listener)
	{
		self::$listeners[] = $listener;
	}

	//------------------------------------------------------------------------------
	//! Log a new message
	/*! \param $level [int] The log level. One among log levels defined for
			the syslog() PHP function.
		\param $msg [string] The message to log.
		\param $context [string] Context where the log message has been produced. */
	//------------------------------------------------------------------------------
	public static function submit($level, $msg, $context = "")
	{
		self::createSessionNumber();
		$ts = strftime("%Y:%m:%dT%H:%M:%S");

		foreach (array_keys(self::$listeners ) as $k) {
			$listener = self::$listeners[$k];
			$listener->submit($level, $ts, self::$session, $msg, $context);
		}
	}
};
