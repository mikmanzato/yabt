<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Log class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Listener for log events
//------------------------------------------------------------------------------
abstract class LogListener
{
	//! [int] The minimum loglevel
	protected $minLogLevel = LOG_INFO;

	//------------------------------------------------------------------------------
	//! Set the minimum loglevel
	//------------------------------------------------------------------------------
	public function setMinLogLevel($l)
	{
		$this->minLogLevel = $l;
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
	abstract public function submit($level, $ts, $session, $msg, $context = "");
};
