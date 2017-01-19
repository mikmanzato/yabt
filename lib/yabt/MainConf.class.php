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
	private static $mainConf;

	//--------------------------------------------------------------------------
	//! Return the global MainConf
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
};
