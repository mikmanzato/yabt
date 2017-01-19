<?php
//------------------------------------------------------------------------------
/* 	Yabt

	DuplicityJobStatus class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Represents the status of a Duplicity backup job
//------------------------------------------------------------------------------
class DuplicityJobStatus
	extends JobStatus
{
	//! [Sequence] Sequenced backup index
	public $sequence;
};
