<?php
//------------------------------------------------------------------------------
/* 	Yabt
	BackupInfo class implementation
	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Backup info for the DirDump job
//------------------------------------------------------------------------------
class DirDumpInfo
	extends DumpInfo
{
    //--------------------------------------------------------------------------
    //! Return verbose information about the job status
    //--------------------------------------------------------------------------
	public function getStatusArray()
	{
		return parent::getStatusArray();
	}
}



