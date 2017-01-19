<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Conf class
    
	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Generic configuration file
//------------------------------------------------------------------------------
class Conf
{
	//! [string] Name of the configuration file
	private $confFname = NULL;

	//! [array] The configuration, as read from file
	private $conf = array();

	//--------------------------------------------------------------------------
	//! Constructor
	/*! \param $confFname [string] Name of the configuration file to load */
	//--------------------------------------------------------------------------
	public function __construct($confFname)
	{
		$this->confFname = $confFname;

		if (!file_exists($confFname))
			throw new \Exception("Configuration file not found: $confFname");

		$conf = @parse_ini_file($confFname, TRUE);
		if ($conf === FALSE)
			$this->error("Syntax error");

		$this->conf = $conf;
	}

	//--------------------------------------------------------------------------
	//! Get the value of a configuration parameter
	/*! \param $section [string] Name of the configuration file section
		\param $key [string] Name of the key within the configuration section
		\param $default [mixed] Default value to return if the section/key
			value is not set.
		\returns The key value, or the $default value. */
	//--------------------------------------------------------------------------
	protected function _get($section, $key, $default=NULL)
	{
		if (isset($this->conf[$section][$key]))
			return $this->conf[$section][$key];
		else
			return $default;
	}

	//--------------------------------------------------------------------------
	//! Parse and resolve cross-references
	/*! \param $value [string] String potentially containing cross-references
		\returns The input string with references resolved.

		Following references are resolved at present:
		* ${section.key}: resolved as value of the corresponding key. */
	//--------------------------------------------------------------------------
	protected function resolveRefs($value)
	{
		// Resolve ${section.key} references
		$pattern = '/\$\{(\w+)\.(\w+)\}/';
		$value = preg_replace_callback($pattern, function($regs) {
				$section = $regs[1];
				$key = $regs[2];
				return $this->get($section, $key);
			}, $value);

		return $value;
	}

	//--------------------------------------------------------------------------
	//! Get the value of a configuration parameter, resolving references
	/*! \param $section [string] Name of the configuration file section
		\param $key [string] Name of the key within the configuration section
		\param $default [mixed] Default value to return if the section/key
			value is not set.
		\returns The key value, or the $default value. */
	//--------------------------------------------------------------------------
	public final function get($section, $key, $default=NULL)
	{
		$value = $this->_get($section, $key, $default);
		if (is_null($value))
			return NULL;

		return $this->resolveRefs($value);
	}

	//--------------------------------------------------------------------------
	//! Get the value of a required configuration parameter
	/*! \param $section [string] Name of the configuration file section
		\param $key [string] Name of the key within the configuration section
		\param $default [mixed] Default value to return.
		\returns The key value
		\throws Exception if the key does not exist */
	//--------------------------------------------------------------------------
	public function getRequired($section, $key, $default=NULL)
	{
		$value = $this->get($section, $key, $default);
		if (is_null($value) || empty($value))
			$this->error("Missing value for '{$section}/{$key}'");
		else
			return $value;
	}

	//--------------------------------------------------------------------------
	//! Get the value of a configuration parameter
	/*! \param $section [string] Name of the configuration file section
		\param $key [string] Name of the key within the configuration section
		\returns The key value, or NULL if no value is found. */
	//--------------------------------------------------------------------------
	public function error($msg)
	{
		throw new \Exception("{$msg} - In file {$this->confFname}");
	}
};
