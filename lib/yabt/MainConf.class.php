<?php
//------------------------------------------------------------------------------
/* 	Yabt

	MainConf class

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Main configuration file
//------------------------------------------------------------------------------
class MainConf
	extends Conf
{
	const PROGRAMS_SECTION = 'programs';
	const DEFAULT_TAR_EXE = '/bin/tar';
	const DEFAULT_BZIP2_EXE = '/bin/bzip2';

	private static $mainConf;

	//--------------------------------------------------------------------------
	//! Initialize the MainConf object
	//--------------------------------------------------------------------------
	public static function load($confFname)
	{
		if (!self::$mainConf)
			self::$mainConf = new self($confFname);
	}

	//--------------------------------------------------------------------------
	//! Return the global MainConf
	//--------------------------------------------------------------------------
	public static function getGlobal()
	{
		if (!self::$mainConf)
			throw new \Exception("Configuration not loaded yet");

		return self::$mainConf;
	}

	//--------------------------------------------------------------------------
	//! Return the path to the "tar" executable
	//--------------------------------------------------------------------------
	public function getTarExe()
	{
		$exe = $this->get(self::PROGRAMS_SECTION, 'tar_exe', self::DEFAULT_TAR_EXE);
		if (!file_exists($exe) && !is_executable($exe))
			throw new \Exception("Not found or not an executable: ".$exe);
		return $exe;
	}

	//--------------------------------------------------------------------------
	//! Return the path to the "bzip2" executable
	//--------------------------------------------------------------------------
	public function getBzip2Exe()
	{
		$exe = $this->get(self::PROGRAMS_SECTION, 'bzip2_exe', self::DEFAULT_BZIP2_EXE);
		if (!file_exists($exe) && !is_executable($exe))
			throw new \Exception("Not found or not an executable: ".$exe);
		return $exe;
	}
};
