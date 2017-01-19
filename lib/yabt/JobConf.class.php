<?php
//------------------------------------------------------------------------------
/* 	Yabt

	JobConf class
    
	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Job configuration file
//------------------------------------------------------------------------------
class JobConf
	extends Conf
{
	//--------------------------------------------------------------------------
	//! Create a Job according to this configuration parameters
	//--------------------------------------------------------------------------
	public function makeJob()
	{
		$type = $this->get('job', 'type');
		if (!$type)
			$this->error("Missing parameter job/type.");

		$job = Job::instantiateJob($type, $this);
		return $job;
	}

	//--------------------------------------------------------------------------
	//! Get the value of a configuration parameter
	/*! \param $section [string] Name of the configuration file section
		\param $key [string] Name of the key within the configuration section
		\param $default [mixed] Default value to return if the section/key
			value is not set.
		\returns The key value, or the $default value.

		Explore the main configuration if the value is not found locally. */
	//--------------------------------------------------------------------------
	protected function _get($section, $key, $default = NULL)
	{
		$value = parent::_get($section, $key);
		if (!is_null($value))
			return $value;

		$mainConf = MainConf::getGlobal();
		return $mainConf->_get($section, $key, $default);
	}
};
