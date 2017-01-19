<?php
//------------------------------------------------------------------------------
/* 	Yabt

	DumpJob class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Generic configuration file
//------------------------------------------------------------------------------
abstract class DumpJob
	extends BackupJob
{
	const DEFAULT_INCREMENTAL = TRUE;
	const DEFAULT_RETENTION_PERIOD = 14;
	const DEFAULT_FULL_PERIOD = 7;

	protected $fs;

	protected $incremental;
	protected $retentionPeriod;
	protected $fullPeriod;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf, $section)
	{
		parent::__construct($conf);

		$dumpLocation = $this->conf->get('dump', 'location');
		if (!$dumpLocation)
			throw new \Exception("Missing value for dump.location");

		$dumpLocation = $dumpLocation;
		$this->fs = TargetFs::build($dumpLocation, $this->getSubdir());

		// Other common parameters
		$this->incremental = (bool) $this->conf->get($section, 'incremental', self::DEFAULT_INCREMENTAL);
		$this->retentionPeriod = (int) $this->conf->get($section, 'retention_period', self::DEFAULT_RETENTION_PERIOD);
		$this->fullPeriod = (int) $this->conf->get($section, 'full_period', self::DEFAULT_FULL_PERIOD);
	}

	//--------------------------------------------------------------------------
	//! Check the available partition space
	//--------------------------------------------------------------------------
	protected function checkAvailableSpace()
	{
		static $lowLimit = 1024.0;	// Lower limit, in MiB

		$space = $this->fs->getAvailableSpace("/");
		if ($space !== FALSE) {
			$spaceMiB = $space / 1048576.0;
			$this->log(LOG_INFO, sprintf("Available space: %.1f MiB", $spaceMiB));
			if ($spaceMiB < $lowLimit)
				$this->log(LOG_WARNING, sprintf("Space in partition '%s' is low (< %.1f MiB)", $path, $lowLimit));
		}
	}

	//------------------------------------------------------------------------------
	//! Purge old dump files
	/*! \param $dir [string] Directory to clean
		\param $pattern [string] The pattern for dump file names, basename only

		The $target backup file is replaced with a link to the previous dump
		file if no changes are detected.

		Old dump files and links, beyond the retention number, are deleted.
		However, old full dumps are kept beyond the retention period as long as
		they are referenced by at least one recent link.
		*/
	//------------------------------------------------------------------------------
	protected function purgeOld($pattern)
	{
//		echo __method__."('$pattern')\n";
		$this->log(LOG_DEBUG, "Purging expired dumps (retention={$this->retentionPeriod}).");

		// All dump files, including the most recent
		$fnames = $this->fs->listFiles(".", $pattern);
//		var_dump($fnames);

		// Sort $fnames according to descending timestamp
		usort($fnames, function($fname1, $fname2) {
				static $tsPattern = '/([0-9]{8}T[0-9]{6})/';
				if (preg_match($tsPattern, $fname1, $regs))
					$ts1 = $regs[0];
				if (preg_match($tsPattern, $fname2, $regs))
					$ts2 = $regs[0];
				return -strcmp($ts1, $ts2);
			});

		// First entry in $fnames is the latest (current) dump
		$target = array_shift($fnames);

		// Next entry in $fnames is the previous dump
		if (isset($fnames[0]))
			$targetPrevious = $fnames[0];
		else
			$targetPrevious = FALSE;

		$sources = array();
		if ($targetPrevious && $this->fs->supportsLinks()) {
			$targetMd5 = $this->fs->getFileMd5($target);
			$targetPreviousMd5 = $this->fs->getFileMd5($targetPrevious);

			$this->log(LOG_DEBUG, "MD5 target: $targetMd5");
			$this->log(LOG_DEBUG, "MD5 target latest: $targetPreviousMd5");

			if (($this->fs->filesize($target) == $this->fs->filesize($targetPrevious))
				&& ($targetMd5 == $targetPreviousMd5)) {
				// No change: delete target file and replace it with a link to the
				// previous dump file.
				$this->log(LOG_DEBUG, "Replacing $target with link to the previous dump file.");
				$this->fs->unlink($target);
				if ($this->fs->isLink($targetPrevious))
					$linkTarget = $this->fs->readlink($targetPrevious);
				else
					$linkTarget = basename($targetPrevious);

				$this->fs->symlink($target, $linkTarget);
				$sources[$linkTarget] = TRUE;
			}
		}

		// Purge expired link dump
		$n = 0;
		foreach ($fnames as $fname) {
			$n++;
			if (is_link($fname)) {
				if ($n < $this->retentionPeriod) {
					$src = readlink($fname);
					$sources[$src] = TRUE;
				}
				else {
					$this->log(LOG_INFO, "Removing old link dump: $fname");
					unlink($fname);
				}
			}
			elseif ($n < $this->retentionPeriod) {
				$src = basename($fname);
				$sources[$src] = TRUE;
			}
		}

		// Purge expired full dumps, unless they are referenced by a recent link dump
		foreach ($fnames as $fname) {
			$n++;
			if ($this->fs->exists($fname) && !$this->fs->isLink($fname)) {
				$b = basename($fname);
				if (!isset($sources[$b])) {
					$this->log(LOG_INFO, "Removing old full dump file: $fname");
					$this->fs->unlink($fname);
				}
			}
		}
	}

	//------------------------------------------------------------------------------
	//! Purge old sequenced dump files
	/*! \param $dir [string] Directory to clean
		\param $pattern [string] The pattern for dump file names, basename only */
	//------------------------------------------------------------------------------
	protected function purgeOldSeq($pattern)
	{
		$this->log(LOG_DEBUG, "Purging expired dumps (retention=$this->retentionPeriod).");

		// All dump files, including the most recent
		$fnames = $this->fs->listFiles(".", $pattern);

		// Sort $fnames according to descending timestamp
		usort($fnames, function($fname1, $fname2) {
//				var_dump($fname1);
//				var_dump($fname2);
				static $tsPattern = '/([0-9]{8}T[0-9]{6})\\.(vol)?([0-9]+)/';
				$ts1 = $ts2 = 0;
				if (preg_match($tsPattern, $fname1, $regs)) {
					$ts1 = $regs[1];
					$seq1 = (int) $regs[3];
				}
				if (preg_match($tsPattern, $fname2, $regs)) {
	-				$ts2 = $regs[1];
					$seq2 = (int) $regs[3];
				}
				if ($ts1 != $ts2)
					return -strcmp($ts1, $ts2);
				else
					return $seq2 - $seq1;
			});

		// Delete dumps beyond the retention period. Be sure not to delete full or
		// partial dumps which are required by a non-expired dump file.
		$seq = 0;
		$delete = FALSE;
		foreach ($fnames as $fname) {
			$seq = $seq + 1;
			if ($seq < $this->retentionPeriod)
				continue;
			if (!$delete) {
				static $tsPattern = '/([0-9]{8}T[0-9]{6})\\.(vol)?([0-9]+)/';
				if (!preg_match($tsPattern, $fname, $regs))
					continue;
				if ($regs[3] == '1')
					$delete = TRUE;
				continue;
			}
			else {
				$this->log(LOG_INFO, "Removing old dump file: $fname");
				$this->fs->unlink($fname);
			}
		}
	}

	//--------------------------------------------------------------------------
	//! Automated backup verification
	/*! Ensure that backups are healthy */
	//--------------------------------------------------------------------------
	protected function verify()
	{
	}
};
