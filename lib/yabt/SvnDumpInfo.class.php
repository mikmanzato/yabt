<?php
//------------------------------------------------------------------------------
/* 	Yabt
	MysqlDbDumpInfo class implementation
	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Backup info for the SvnDump job
//------------------------------------------------------------------------------
class SvnDumpInfo
	extends DumpInfo
{
	//! [int] The last dumped revision
	public $revision = 0;

    //--------------------------------------------------------------------------
    //! Return verbose information about the job status
    //--------------------------------------------------------------------------
	public function getStatusArray()
	{
		$s = parent::getStatusArray();
		$s['Revision'] = $this->revision;
		return $s;
	}
}



