<?php
//------------------------------------------------------------------------------
/* 	Yabt
	Sequence class implementation
	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Sequencer, for incremental backups/dumps
//------------------------------------------------------------------------------
class Sequence
{
	//! [int] Sequence index
	protected $seq = 0;

	//! Constructor
	public function __construct($seq = 1)
	{
		$this->seq = $seq;
	}

	//! Return string representation
	public function __toString()
	{
		return (string) $this->seq;
	}

	//! Get current sequence number
	public function get()
	{
		return $this->seq;
	}

	//! Tell if this is the first of sequence
	public function isFirst()
	{
		return $this->seq == 1;
	}

	//! Get next sequence
	public function next($fullPeriod)
	{
		if ($this->seq < $fullPeriod)
			return new Sequence($this->seq + 1);
		else
			return new Sequence();
	}
};
