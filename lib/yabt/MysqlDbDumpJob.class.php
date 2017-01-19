<?php
//------------------------------------------------------------------------------
/* 	Yabt

	MysqlDbDumpJob class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Dump job: Dump mysql database
//------------------------------------------------------------------------------
class MysqlDbDumpJob
	extends SimpleDumpJob
{
	const SECTION = 'mysqldb';

	private $user;
	private $password;
	private $dbName;
	private $hostname;
	private $port;
	private $storageDir;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		parent::__construct($conf, self::SECTION);

		$this->user = $this->conf->getRequired(self::SECTION, 'user');
		$this->password = $this->conf->get(self::SECTION, 'password', "");
		$this->hostname = $this->conf->get(self::SECTION, 'hostname', "localhost");
		$this->dbname = $this->conf->getRequired(self::SECTION, 'dbname');
		$this->port = $this->conf->get(self::SECTION, 'port');
		$this->storageDir = $this->conf->get(self::SECTION, 'storage_dir', FALSE);
	}

	//--------------------------------------------------------------------------
	//! Return context for log messages
	//--------------------------------------------------------------------------
	protected function logContext()
	{
		return "mysqldb[{$this->name}]";
	}

	//------------------------------------------------------------------------------
	//! Run an incremental dump
	//------------------------------------------------------------------------------
	protected function doRunIncremental(DumpInfo $dumpInfo = NULL)
	{
		return $this->doRunFull($dumpInfo);
	}

	//------------------------------------------------------------------------------
	//! Run a full dump
	//------------------------------------------------------------------------------
	protected function doRunFull(DumpInfo $dumpInfo = NULL)
	{
		$this->log(LOG_INFO, "Performing a full dump");

		$this->checkAvailableSpace();

		$dumpInfo = new MysqlDbDumpInfo();
		$cmd = sprintf("/usr/bin/mysqldump --skip-dump-date -u%s -p%s -h%s %s %s",
				escapeshellarg($this->user),
				escapeshellarg($this->password),
				escapeshellarg($this->hostname),
				$this->port ? "-P{$this->port}" : "",
				escapeshellarg($this->dbname));

		$cmd = $cmd." 2> /dev/null | /bin/bzip2";

		$fTarget1 = $fTarget2 = NULL;
		if ($this->storageDir) {
			$time = mktime(0, 0, 0, 1, 1, 2015, 0);
			$fnames = array();

			// Backup the database
			$tmpdir = Fs::mkTempDir();
			$target1 = "{$this->dbname}.sql.bz2";
			$fTarget1 = Fs::joinPath($tmpdir, $target1);
			$cmd = $cmd." > ".escapeshellarg($fTarget1);
			$this->log(LOG_DEBUG, "Running: $cmd");
			Shell::exec($cmd);

			touch($fTarget1, $time);
			$fnames[] = $target1;

			// Backup the storage directory
			$entries = array();
			if ($handle = opendir($this->storageDir)) {
				while (false !== ($entry = readdir($handle))) {
					if ($entry != "." && $entry != "..")
						$entries[] = $entry;
				}
				closedir($handle);
			}

			if ($entries) {
				$e = array();
				foreach ($entries as $entry)
					$e[] = escapeshellarg($entry);

				$target2 = "storage.tar.bz2";
				$fTarget2 = Fs::joinPath($tmpdir, $target2);
				$cmd = sprintf("/bin/tar jcf %s -C %s %s",
						escapeshellarg($fTarget2),
						escapeshellarg($this->storageDir),
						implode(" ", $e));

				$this->log(LOG_DEBUG, "Running: $cmd");
				Shell::exec($cmd);

				touch($fTarget2, $time);
				$fnames[] = $target2;
			}
			else
				$target2 = NULL;

			// Create a tarball with the two files together
			$e = array();
			foreach ($fnames as $fname)
				$e[] = escapeshellarg($fname);

			$fnames = implode(" ", $fnames);
			$target = "{$this->dbname}-{$dumpInfo->ts}.tar";
			$tmpTarget = Fs::mkExactTempName($target);
			$pattern = "/^{$this->dbname}-.*\\.tar\$/";
			$cmd = sprintf("/bin/tar cf %s -C %s %s",
					escapeshellarg($tmpTarget),
					escapeshellarg($tmpdir),
					implode(" ", $e));

			try {
				$this->log(LOG_DEBUG, "Running: $cmd");
				Shell::exec($cmd);

				$this->log(LOG_INFO, "Created new dump: $tmpTarget");
			}
			finally {
				// Remove temporary files
				if (file_exists($fTarget1))
					unlink($fTarget1);
				if (file_exists($fTarget2))
					unlink($fTarget2);
				if (is_dir($tmpdir))
					rmdir($tmpdir);
			}
		}
		else {
			// Backup the database
			$target = "{$this->dbname}-{$this->ts}.sql.bz2";
			$pattern = "/^{$this->dbname}-.*\\.sql\\.bz2\$/";
			$tmpTarget = Fs::mkExactTempName($target);
			$cmd = $cmd." > ".escapeshellarg($tmpTarget);
			Shell::exec($cmd);

			$this->log(LOG_INFO, "Created new dump: $target");
		}

		// Move to target fs
		$this->fs->putFileWithMd5($tmpTarget, $target);
		Fs::unlink($tmpTarget);

		// Purge old dumps
		$this->purgeOld($pattern);

		// Update dump info
		$dumpInfo->addFile($target);

		$this->log(LOG_INFO, "Done");
		return $dumpInfo;
	}
};

