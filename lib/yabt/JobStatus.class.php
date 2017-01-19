<?php
//------------------------------------------------------------------------------
/* 	Yabt

	JobStatus class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Represents the status of a job
/*! It is serialized into the "job.status" file which is saved into each
	job's directory. */
//------------------------------------------------------------------------------
class JobStatus
{
	//! [string] Name of the file where the Job status is saved
	protected $fname = NULL;

	//! [\DateTime] Date and time of previous successful run
	public $lastSuccessfulRunDt = NULL;

	//! [\DateTime] Date and time of previous attempted run
	public $lastAttemptedRunDt = NULL;

	//! [bool] TRUE if last run was executed correctly, FALSE otherwise
	public $lastRunOk = NULL;

	//! [string] Error message from last run
	public $lastRunErrorMsg = NULL;

    //--------------------------------------------------------------------------
    //! Factory method: load existing object from file
    //--------------------------------------------------------------------------
	public static function load($fname)
	{
		if (!file_exists($fname))
			return NULL;

		$s = file_get_contents($fname);
		$jobStatus = unserialize($s);
		if ($jobStatus === FALSE)
			throw new \Exception("Can't read job status from file: $fname");
		$jobStatus->fname = $fname;
		return $jobStatus;
	}

    //--------------------------------------------------------------------------
    //! Constructor
    //--------------------------------------------------------------------------
	public function __construct($fname)
	{
		$this->fname = $fname;
	}

    //--------------------------------------------------------------------------
    //! Magic method, called before serialization
    //--------------------------------------------------------------------------
    public function __sleep()
    {
        return array_diff(array_keys(get_object_vars($this)), array('fname'));
    }

	//--------------------------------------------------------------------------
	//! Save this object to file
	//--------------------------------------------------------------------------
	public function save()
	{
		file_put_contents($this->fname, serialize($this));
	}

    //--------------------------------------------------------------------------
    //! Return verbose information about the job status
    //--------------------------------------------------------------------------
	public function getStatusArray()
	{
		$s = array();
		if ($this->lastSuccessfulRunDt)
			$s['Last successful run'] = $this->lastSuccessfulRunDt->format('Y-m-d H:i:s');
		if ($this->lastAttemptedRunDt)
			$s['Last attempted run'] = $this->lastAttemptedRunDt->format('Y-m-d H:i:s');
		if ($this->lastRunOk === TRUE)
			$s['Last run result'] = "SUCCESS";
		elseif ($this->lastRunOk === FALSE)
			$s['Last run result'] = "FAILED";
		if ($this->lastRunErrorMsg)
			$s['Last run errmsg'] = $this->lastRunErrorMsg;
		return $s;
	}
};
