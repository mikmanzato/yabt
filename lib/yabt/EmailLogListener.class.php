<?php
//------------------------------------------------------------------------------
/* 	Yabt

	EmailLogListener class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Log listener to send notifications by email
//------------------------------------------------------------------------------
class EmailLogListener
	extends LogListener
{
	//! Stored messages
	private $messages = array();

	//------------------------------------------------------------------------------
	//! Constructor
	//------------------------------------------------------------------------------
	public function __construct()
	{
		$this->minLogLevel = LOG_WARNING;
	}

	//------------------------------------------------------------------------------
	//! Log a new message
	/*! \param $level [int] The log level. One among log levels defined for
			the syslog() PHP function.
		\param $ts [string] The message timestamp
		\param $session [string] The run session ID
		\param $msg [string] The message to log.
		\param $context [string] Context where the log message has been produced.

		The message is recorded in a message queue which is subsequently used
		to produce a notification. */
	//------------------------------------------------------------------------------
	public function submit($level, $ts, $session, $msg, $context = "")
	{
		if ($level > $this->minLogLevel)
			return;

		if ($context)
			$msg = "$context: $msg";
		$msg = sprintf("%s [%s] %04d: %s\n",
				$ts,
				Log::getLevelStr($level),
				$session,
				$msg);
		$this->messages[] = $msg;
	}

	//------------------------------------------------------------------------------
	//! Return the stored messages
	//------------------------------------------------------------------------------
	public function getMessages()
	{
		return $this->messages;
	}
};
