<?php
//------------------------------------------------------------------------------
/* 	Yabt

	SvnDumpJob class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Dump job: dump svn repositories
//------------------------------------------------------------------------------
class SvnDumpJob
	extends DumpJob
{
	const SECTION = 'svn';
	const DEFAULT_SVNADMIN_EXE = "/usr/bin/svnadmin";
	const DEFAULT_SVNLOOK_EXE = "/usr/bin/svnlook";

	private $svnadminExe;
	private $svnlookExe;
	private $bzip2Exe;

	//! The parent path of the repositories to export
	private $parentPath;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		parent::__construct($conf, self::SECTION);

		$this->parentPath = $this->conf->getRequired(self::SECTION, 'parent_path');

		$this->svnadminExe = $this->conf->get(self::SECTION, 'svnadmin_exe', self::DEFAULT_SVNADMIN_EXE);
		if (!file_exists($this->svnadminExe) && !is_executable($this->svnadminExe))
			throw new \Exception("Not found or not executable: ".$this->svnadminExe);

		$this->svnlookExe = $this->conf->get(self::SECTION, 'svnlook_exe', self::DEFAULT_SVNLOOK_EXE);
		if (!file_exists($this->svnlookExe) && !is_executable($this->svnlookExe))
			throw new \Exception("Not found or not executable: ".$this->svnlookExe);

		$this->bzip2Exe = MainConf::getGlobal()->getBzip2Exe();
	}

	//--------------------------------------------------------------------------
	//! Return context for log messages
	//--------------------------------------------------------------------------
	protected function logContext()
	{
		return "svn[{$this->name}]";
	}

	//--------------------------------------------------------------------------
	//! Dump a single SVN repository
	//--------------------------------------------------------------------------
	protected function dumpSingleRepository($path)
	{
		if (!file_exists($path."/format"))
			throw new \BadFunctionCallException("Not an SVN repository: $path");

		$repoPath = $path;
		$repoName = basename($repoPath);
		$this->log(LOG_INFO, "Dumping repository: $repoName");

		// Download dump information
		$dumpInfoFName = "svndump-{$repoName}.info";
/*		if ($this->fs->getFileToTemp($dumpInfoFName, $dumpInfoPath))
			$prevDumpInfo = DumpInfo::load($dumpInfoPath);
		else
			$prevDumpInfo = FALSE;*/
		$prevDumpInfo = DumpInfo::load($this->fs, $dumpInfoFName);

		if ($this->incremental) {
			// Perform an incremental SVN backup
			$this->log(LOG_INFO, "Performing an incremental dump");

			$dumpInfo = NULL;
			if ($prevDumpInfo) {
				if (!$prevDumpInfo instanceof SvnDumpInfo) {
					$this->log(LOG_WARNING, "Bad dump info, doing a full dump");
				}
				elseif (!$prevDumpInfo->isChainOk($this->fs)) {
					$this->log(LOG_WARNING, "Dump file chain is corrupt, doing a full dump");
				}
				else {
					$this->log(LOG_DEBUG, "Dump file chain is OK");
					$dumpInfo = $prevDumpInfo->next($this->fullPeriod);
				}
			}
			else {
				$this->log(LOG_DEBUG, "Initializing new backup");
			}

			if (!$dumpInfo)
				$dumpInfo = new SvnDumpInfo();

			if ($dumpInfo->isFull())
				$suffix = 'full';
			else
				$suffix = 'inc';

			// Get latest committed revision in repository
			$cmd = sprintf("%s youngest %s",
			               escapeshellarg($this->svnlookExe),
			               escapeshellarg($repoPath));
			$result = Shell::exec($cmd);
			$dumpInfo->revision = (int) $result;
			$seq = $dumpInfo->sequence->get();

//			if (($seq == 1) || ($rev1 != $rev2)) {
			if ($dumpInfo->sequence->isFirst()
				|| ($dumpInfo->revision != $prevDumpInfo->revision)) {
				$target = "{$repoName}-{$dumpInfo->ts}.vol{$seq}.{$suffix}.dump.bz2";
				$tmpTarget = Fs::mkExactTempName($target);

				if ($dumpInfo->isFull())
					$cmd = sprintf("%s dump %s",
                                   escapeshellarg($this->svnadminExe),
					               escapeshellarg($repoPath));
				else
					$cmd = sprintf("%s dump %s --incremental --revision %d:%d",
                                   escapeshellarg($this->svnadminExe),
								   escapeshellarg($repoPath),
								   $prevDumpInfo->revision + 1,
								   $dumpInfo->revision);

				$cmd = $cmd.sprintf(" 2> /dev/null | %s > %s",
							   escapeshellarg($this->bzip2Exe),
							   escapeshellarg($tmpTarget));

				$this->log(LOG_DEBUG, "Running: $cmd");
				Shell::exec($cmd);

				$type = ($dumpInfo->isFull() ? "full" : "incremental");
				$this->log(LOG_INFO, "Created new $type dump: $target");
			}
			else {
				$this->log(LOG_DEBUG, "No commits since previous dump");
				$target = "{$repoName}-{$dumpInfo->ts}.vol{$seq}.empty";
				$tmpTarget = Fs::mkExactTempName($target);
				file_put_contents($tmpTarget, "");

				$this->log(LOG_INFO, "Created new empty dump: $target");
			}

			// Move to target fs
			$this->fs->putFile($tmpTarget, $target);
			Fs::unlink($tmpTarget);

			$dumpInfo->addFile($target);

			// Purge old dumps
			$pattern = "/^{$repoName}-.*\\.(bz2|empty)\$/";
			$this->purgeOldSeq($pattern);
		}
		else {
			// Perform a full SVN backup
			$this->log(LOG_INFO, "Performing a full dump");
			$dumpInfo = new SvnDumpInfo();

			$target = "{$repoName}-{$dumpInfo->ts}.dump.bz2";
			$tmpTarget = Fs::mkExactTempName($target);

			$cmd = sprintf("%s dump %s 2> /dev/null | %s > %s",
                           escapeshellarg($this->svnadminExe),
			               escapeshellarg($repoPath),
			               escapeshellarg($this->bzip2Exe),
			               escapeshellarg($tmpTarget));

			$this->log(LOG_DEBUG, "Running: $cmd");
			Shell::exec($cmd);

			// Move to target fs
			$this->fs->putFileWithMd5($tmpTarget, $target);
			Fs::unlink($tmpTarget);

			$this->log(LOG_INFO, "Created new dump: $target");

			// Purge old backups
			$pattern = "/^{$repoName}-.*\\.dump\\.bz2\$/";
			$this->purgeOld($pattern);
		}

		// Upload dump information
/*		$s = serialize($dumpInfo);
		file_put_contents($dumpInfoPath, $s);
		$this->fs->putFile($dumpInfoPath, $dumpInfoFName);
		Fs::unlink($dumpInfoPath); */
		$dumpInfo->store($this->fs, $dumpInfoFName);
	}

	//------------------------------------------------------------------------------
	//! Run the export job
	//------------------------------------------------------------------------------
	protected function doRun()
	{
		$this->checkAvailableSpace();

		$paths = glob($this->parentPath."/*");
		foreach ($paths as $path) {

			if (!is_dir($path))
				continue;

			// Basic check if the directory contains an SVN repository
			if (!file_exists($path."/format"))
				continue;

			$this->dumpSingleRepository($path);
		}

		return TRUE;
	}
};




