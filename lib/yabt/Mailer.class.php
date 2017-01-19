<?php
//------------------------------------------------------------------------------
/* 	Yabt

	Mailer class implementation
    
	$Id$ */
//------------------------------------------------------------------------------

namespace yabt;


class MailerException extends \Exception {}


//------------------------------------------------------------------------------
//! Mailer class
//------------------------------------------------------------------------------
class Mailer
{
	public static $smtpHostname = 'localhost';

	//--------------------------------------------------------------------------
	//! Returns TRUE if notifications are enabled, FALSE otherwise
	//--------------------------------------------------------------------------
	public static function notificationsEnabled()
	{
		$mainConf = MainConf::getGlobal();
		if (!$mainConf->get('notifications', 'enabled', FALSE))
			return FALSE;

		$recipients = $mainConf->get('notifications', 'recipients', FALSE);
		if ($recipients === FALSE)
			return FALSE;

		return TRUE;
	}

	//--------------------------------------------------------------------------
	//! Send a notification email to the configured recipients
	/*! \param $subject [string] The email subject
		\param $subject [string] The email body (HTML) */
	//--------------------------------------------------------------------------
	public static function sendNotification($subject, $body)
	{
		require_once 'phpmailer/class.phpmailer.php';

		if (!self::notificationsEnabled())
			return;

		$mainConf = MainConf::getGlobal();

		$recipients = $mainConf->get('notifications', 'recipients', FALSE);
		if ($recipients === FALSE)
			return;

		$recipients = explode(",", $recipients);
		$from = $mainConf->get('notifications', 'from', 'yabt@'.System::getFqdn());
		$smtpHostname = $mainConf->get('notifications', 'smtp_hostname', self::$smtpHostname);

		$mail = new \PHPMailer;
		$mail->isSMTP();
		$mail->Host = $smtpHostname;
		$mail->setFrom($from);

		foreach ($recipients as $recipient)
			$mail->addAddress($recipient);

		$mail->Subject = $subject;
		$mail->Body = $body;
		$mail->IsHTML(TRUE);

		if (!$mail->send())
			throw new MailerException("Can't send email message");
	}
}



