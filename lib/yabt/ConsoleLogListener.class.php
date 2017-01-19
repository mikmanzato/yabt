<?php
//------------------------------------------------------------------------------
/* 	Yabt

	ConsoleLogListener class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Listener which outputs log messages on the console
//------------------------------------------------------------------------------
class ConsoleLogListener
	extends LogListener
{
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

		if ($context)
			$msg = "$context: $msg";
		$msg = sprintf("[%s] %s", Log::getLevelStr($level), $msg);
		Console::displayMsg($msg);
	}
};
