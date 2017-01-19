<?php
//------------------------------------------------------------------------------
/* 	Yabt

	StatusNotificationJob class implementation.

	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


//------------------------------------------------------------------------------
//! Job which notifies the overall backup status
//------------------------------------------------------------------------------
class StatusNotificationJob
	extends Job
{
	const SECTION = 'status-notification';

	//! The export path
	private $complete;

	//--------------------------------------------------------------------------
	//! Constructor
	//--------------------------------------------------------------------------
	public function __construct(Conf $conf)
	{
		parent::__construct($conf);

		$this->complete = (bool) $this->conf->get(self::SECTION, 'complete', FALSE);
	}

	//--------------------------------------------------------------------------
	//! Returns the job type
	//--------------------------------------------------------------------------
	public function getType()
	{
		return "status-notification";
	}

	//--------------------------------------------------------------------------
	//! Return context for log messages
	//--------------------------------------------------------------------------
	protected function logContext()
	{
		return "status-notification[{$this->name}]";
	}

	//--------------------------------------------------------------------------
	//! Convert job status to HTML format
	//--------------------------------------------------------------------------
	public static function statusToHtml(array $status)
	{
		$ss = '';
		foreach ($status as $js) {
			$name = $js['Job name'];
			unset($js['Job name']);

			if ($js['Enabled'] == 'NO')
				$attrs = ' style="color: #999; font-size: 130%"';
			elseif ($js['Last run result'] == 'SUCCESS')
				$attrs = ' style="color: #090; font-size: 130%"';
	        elseif ($js['Last run result'] == 'FAILURE')
				$attrs = ' style="color: #900; font-size: 130%"';
			else
				$attrs = '';

			$ss .= sprintf("<h2%s>%s</h2>\n",
						   $attrs,
						   htmlspecialchars($name));

			$ss .= "<dl>\n";
			foreach ($js as $k => $v)
				$ss .= sprintf("<dt>%s</dt><dd>%s</dd>\n",
	                           htmlspecialchars($k),
	                           htmlspecialchars($v));
			$ss .= "</dl>\n";
		}

		return $ss;
	}

	//------------------------------------------------------------------------------
	//! Run the job: send notification
	//------------------------------------------------------------------------------
	protected function doRun()
	{
		if (!Mailer::notificationsEnabled()) {
			$this->log(LOG_DEBUG, "Status notification email NOT sent: notifications are disabled.");
			return TRUE;
		}

		// Get status of backup jobs
		$status = Main::$jobQueue->getStatusArray('backup');
		$status = $status['Jobs'];

		// If a complete status isn't requested then only enabled and failed
		// jobs are notified
		if (!$this->complete) {
			foreach ($status as $k => $s) {
				if ($s['Enabled'] != 'YES')
					unset($status[$k]);
				elseif (isset($s['Last run result']) && ($s['Last run result'] == 'SUCCESS'))
					unset($status[$k]);
			}
		}

		if (empty($status)) {
			$this->log(LOG_DEBUG, "Status notification email NOT sent: nothing to notify.");
			return TRUE;
		}

		$ss = self::statusToHtml($status);
//		var_dump($ss);

		$fqdn = System::getFqdn();
		$body = "<!DOCTYPE html>
<html>
<style type=\"text/css\">
* { font-family: sans-serif; }
</style>
<body>
<p>Hello,</p>
<p>this is the status of the backup jobs configured on <b>{$fqdn}</b>:</p>
{$ss}
<p>Please take appropriate actions if necessary.</p>
<p>Regards</p>
<p>Yabt on {$fqdn}</p>
</body>
</html>";
		$subject = "Backup status notification";

//		file_put_contents("sn.html", $body);

		try {
			Mailer::sendNotification($subject, $body);
			$this->log(LOG_DEBUG, "Status notification email sent.");
			return TRUE;
		}
		catch (Exception $e) {
			$this->log(LOG_WARNING, "Failed to send status notification email.");
			return FALSE;
		}
	}
};
