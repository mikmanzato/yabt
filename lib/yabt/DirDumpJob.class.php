<?php
//------------------------------------------------------------------------------
/* 	Yabt

	DirDumpJob class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Dump job: Dump directory contents
//------------------------------------------------------------------------------
class DirDumpJob
	extends SimpleDumpJob
{
	const SECTION = 'dir';

	private $tarExe;

	//! The export path
	private $path;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		parent::__construct($conf, self::SECTION);

		$this->path = $this->conf->getRequired(self::SECTION, 'path');
		$this->tarExe = MainConf::getGlobal()->getTarExe();
	}

	//--------------------------------------------------------------------------
	//! Return context for log messages
	//--------------------------------------------------------------------------
	protected function logContext()
	{
		return "dir[{$this->name}]";
	}

	//------------------------------------------------------------------------------
	//! Run an incremental dump
	//------------------------------------------------------------------------------
	protected function doRunIncremental(DumpInfo $dumpInfo = NULL)
	{
		// Perform an incremental directory backup
		$this->log(LOG_INFO, "Doing an incremental dump");

		$snapshotFile = Fs::mkExactTempName("archive.snar");
		if (!$dumpInfo instanceof DirDumpInfo) {
			$this->log(LOG_WARNING, "Bad backup info, doing a full dump");
			$dumpInfo = NULL;
		}
		elseif ($this->fs->getFile("archive.snar", $snapshotFile)) {
			$this->log(LOG_WARNING, "Missing snapshot file, doing a full dump");
			$dumpInfo = NULL;
		}
		elseif (!$dumpInfo->isChainOk($this->fs)) {
			$this->log(LOG_WARNING, "Backup chain is corrupt, doing a full dump");
			$dumpInfo = NULL;
		}
		else {
			$this->log(LOG_DEBUG, "Backup chain is OK");
			$dumpInfo = $dumpInfo->next($this->fullPeriod);
		}

		if (!$dumpInfo) {
			$this->log(LOG_DEBUG, "Initializing new dump");
			$dumpInfo = new DirDumpInfo();
		}

		if ($dumpInfo->isFull())
			@unlink($snapshotFile);

		$seq = $dumpInfo->sequence->get();
		$target = "archive-{$dumpInfo->ts}.vol{$seq}.tar.bz2";
		$tmpTarget = Fs::mkExactTempName($target);
		$cmd = sprintf("%s jcf %s --listed-incremental=%s -C %s . 2> /dev/null",
					   escapeshellarg($this->tarExe),
					   escapeshellarg($tmpTarget),
					   escapeshellarg($snapshotFile),
					   escapeshellarg($this->path));

		$this->log(LOG_DEBUG, "Running: $cmd");
		Shell::exec($cmd);

		// Move to target fs
		$this->fs->putFileWithMd5($tmpTarget, $target);
		Fs::unlink($tmpTarget);

		$type = ($dumpInfo->isFull() ? "full" : "incremental");
		$this->log(LOG_INFO, "Created new $type dump: $target");
		$dumpInfo->addFile($target);

		$this->fs->putFile($snapshotFile, "archive.snar");

		// Purge old backups
		$pattern = '/^archive-.*\.tar\.bz2$/';
		$this->purgeOldSeq($pattern);

		// Update dump info
		$dumpInfo->addFile($target);

		return $dumpInfo;
	}

	//------------------------------------------------------------------------------
	//! Run a full dump
	//------------------------------------------------------------------------------
	protected function doRunFull(DumpInfo $dumpInfo = NULL)
	{
		$this->log(LOG_INFO, "Doing a full dump");

		// Perform a full directory backup
		$dumpInfo = new DirDumpInfo();
		$target = "archive-{$dumpInfo->ts}.tar.bz2";
		$tmpTarget = Fs::mkExactTempName($target);
		$cmd = sprintf("%s jcf %s -C %s . 2> /dev/null",
					   escapeshellarg($this->tarExe),
					   escapeshellarg($tmpTarget),
					   escapeshellarg($this->path));

		$this->log(LOG_DEBUG, "Running: $cmd");
		Shell::exec($cmd);

		// Move to target fs
		$this->fs->putFileWithMd5($tmpTarget, $target);
		Fs::unlink($tmpTarget);

		$this->log(LOG_INFO, "Created new dump: $target");

		// Add the file to the dump information
		$dumpInfo->addFile($target);

		// Purge old backups
		$pattern = '/^archive-.*\.tar\.bz2$/';
		$this->purgeOld($pattern);

		return $dumpInfo;
	}
};
