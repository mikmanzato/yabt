<?php
//------------------------------------------------------------------------------
/* 	Yabt

	JobQueue class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Generic configuration file
//------------------------------------------------------------------------------
class JobQueue
{
	private $jobs = array();

	//--------------------------------------------------------------------------
	// Add a new job to the queue
	//--------------------------------------------------------------------------
	private function add(Job $job)
	{
		$this->jobs[] = $job;
		$this->jobsByName[$job->getName()] = $job;
	}

	//--------------------------------------------------------------------------
	// Sort jobs to execute according to phase
	//--------------------------------------------------------------------------
	private function sort()
	{
		usort($this->jobs, function(Job $job1, Job $job2) {
				return $job1->getPhase() - $job2->getPhase();
			});
	}

	//--------------------------------------------------------------------------
	// Return a job by its name
	/*! \param $name [string] Name of job to return
		\returns [Job] The found job, or NULL if no such job exists. */
	//--------------------------------------------------------------------------
	public function getJobByName($name)
	{
		if (isset($this->jobsByName[$name]))
			return $this->jobsByName[$name];
		else
			return NULL;
	}

	//--------------------------------------------------------------------------
	// Run all jobs in the queue
	//--------------------------------------------------------------------------
	public function runAll()
	{
		if (empty($this->jobs)) {
			Log::submit(LOG_DEBUG, "Job queue is empty - No jobs configured");
			return;
		}

		foreach (array_keys($this->jobs) as $k) {
			$job = $this->jobs[$k];
			$job->run();
		}
	}

	//--------------------------------------------------------------------------
	//! Returns the status of all jobs in queue
	/*! \param $type [string] Type of job, or NULL for all jobs */
	//--------------------------------------------------------------------------
	public function getStatusArray($type = NULL)
	{
		$s = array(
				'Jobs' => array()
			);
		foreach (array_keys($this->jobs) as $k) {
			$job = $this->jobs[$k];
			if ($type && ($job->getType() != $type))
				continue;
			$s['Jobs'][] = $job->getStatusArray();
		}
		return $s;
	}

	//--------------------------------------------------------------------------
	// Run all jobs in the queue
	//--------------------------------------------------------------------------
	public static function load($jobsConfDir)
	{
		$confFnames = glob($jobsConfDir."/*.conf");
		$jobQueue = new self;

		foreach ($confFnames as $confFname) {
			$conf = new JobConf($confFname);
			$job = $conf->makeJob();
			$jobQueue->add($job);
		}

		$jobQueue->sort();
		return $jobQueue;
	}
};
