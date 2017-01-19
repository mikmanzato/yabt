<?php
//------------------------------------------------------------------------------
/* 	Yabt

	SimpleDumpJob class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! A simple dump job
//------------------------------------------------------------------------------
abstract class SimpleDumpJob
	extends DumpJob
{
	//------------------------------------------------------------------------------
	//! Run an incremental dump
	//------------------------------------------------------------------------------
	abstract protected function doRunIncremental(DumpInfo $dumpInfo = NULL);

	//------------------------------------------------------------------------------
	//! Run a full dump
	//------------------------------------------------------------------------------
	abstract protected function doRunFull(DumpInfo $dumpInfo = NULL);

	//------------------------------------------------------------------------------
	//! Run the dump job (simple use cases)
	//------------------------------------------------------------------------------
	protected function doRun()
	{
		$this->checkAvailableSpace();

		// Get dump information
		$dumpInfoFName = "dump.info";
		$dumpInfo = DumpInfo::load($this->fs, $dumpInfoFName);

		// Run dump
		if ($this->incremental)
			$dumpInfo = $this->doRunIncremental($dumpInfo);
		else
			$dumpInfo = $this->doRunFull($dumpInfo);

		// Store dump information
		$dumpInfo->store($this->fs, $dumpInfoFName);
	}
};
