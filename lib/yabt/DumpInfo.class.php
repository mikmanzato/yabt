<?php
//------------------------------------------------------------------------------
/* 	Yabt

	DumpInfo class implementation

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Generic dump info
//------------------------------------------------------------------------------
class DumpInfo
{
	//! [string] Timestamp of chain
	public $ts;

	//! [Sequence] Sequenced index for the next dump step
	public $sequence = NULL;

	//! [array(string)] List of names of files in chain
	protected $fnames = array();

    //--------------------------------------------------------------------------
    //! Factory method: load existing dump info from target filesystem
    //--------------------------------------------------------------------------
	public static function load(TargetFs $fs, $fname)
	{
		$path = '';
		if ($fs->getFileToTemp($fname, $path)) {
			if (!file_exists($path))
				return NULL;

			$s = file_get_contents($path);
			$dumpInfo = unserialize($s);
			if ($dumpInfo === FALSE)
				throw new \Exception("Can't read dump info from file: $path");
			if (!$dumpInfo instanceof self)
				throw new \Exception("Invalid DumpInfo: $path");
			Fs::unlink($path);
			return $dumpInfo;
		}
		else
			return NULL;
	}

    //--------------------------------------------------------------------------
    //! Store the dump info file on the target filesystem
    //--------------------------------------------------------------------------
	public function store(TargetFs $fs, $fname)
	{
		$s = serialize($this);
		$path = Fs::mkTempName($fname);
		file_put_contents($path, $s);
		$fs->putFile($path, $fname);
		Fs::unlink($path);
	}

    //--------------------------------------------------------------------------
	//! Constructor
    //--------------------------------------------------------------------------
	public function __construct()
	{
		$this->sequence = new Sequence();
		$this->ts = strftime("%Y%m%dT%H%M%S");
	}

    //--------------------------------------------------------------------------
	//! Returns TRUE for full dumps
    //--------------------------------------------------------------------------
	public function isFull()
	{
		return $this->sequence->isFirst();
	}

    //--------------------------------------------------------------------------
	//! Get next dump info item
    //--------------------------------------------------------------------------
	public function next($fullPeriod)
	{
		$class = get_class($this);
		$info = new $class();
		$info->sequence = $this->sequence->next($fullPeriod);

		if (!$info->sequence->isFirst()) {
			$info->ts = $this->ts;
			$info->fnames = $this->fnames;
		}

		return $info;
	}

    //--------------------------------------------------------------------------
	//! Add a new file to che chain
    //--------------------------------------------------------------------------
	public function addFile($fname)
	{
		$this->fnames[] = $fname;
	}

    //--------------------------------------------------------------------------
	//! Returns TRUE if the latest chain of files is complete
    //--------------------------------------------------------------------------
	public function isChainOk(TargetFs $fs)
	{
		foreach ($this->fnames as $fname)
			if (!$fs->exists($fname))
				return FALSE;

		return TRUE;
	}

    //--------------------------------------------------------------------------
    //! Return verbose information about the job status
    //--------------------------------------------------------------------------
	public function getStatusArray()
	{
		$s = array();
		if ($this->ts)
			$s['Timestamp'] = $this->ts;
		if ($this->sequence)
			$s['Sequence'] = $this->sequence;
		return $s;
	}
}
