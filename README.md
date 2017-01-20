YABT - Yet Another Backup Tool
==============================

> TODO: Documentation in progress

This is my take at building a backup tool for home or small office use.

I've reviewed several existing open-source backup solutions but I found out
they were either too basic, too complicated, poorly extensible, or they just
missed the features that I needed.

Yabt can dump local resources such as:
* SVN repositories
* MySQL databases (with optional filesystem-based storage)
* directories

Yabt can store dumps using:
* rsync
* rdiffbackup
* duplicity

Targets:
* local directories
* ftp/ftps servers
* sftp server

Requirements
------------

Yabt requires a working command-line php installation. On Linux systems,
installing the `php-cli` package or equivalent should do. Yabt works on PHP 5.6
as well as PHP 7.

Installation
------------

Unpack the tar distribution archive, cd into the extracted yabt-x.y.z directory
and type:

    sudo ./install.sh [-c]

Yabt is installed in */usr/local/*.

Also installed are:

  * a global cron job which runs yabt once every 10 minutes. Yabt  itself takes
    care of backup job scheduling.
  * logrotate configuration
  * default configuration files (if you use the "-c" option)

I will soon release a puppet moudule which automates installation and
configuration of Yabt.

Uninstallation
--------------

To uninstall, run:

    ./uninstall.sh

from the extracted package directory.

Configuration
-------------

Yabt is configured via configuration files placed in */usr/local/etc/yabt/*.
There is:

* one main configuration file, *main.cnf*
* several job configuration files in the *jobs.d* subdirectory

Notifications
-------------

Yabt can send email notifications on what happens. There are two types of
notifications:

  * *report notifications*, sent at the end of the backup job executions,
    generally when something goes wrong
  * *status notifications*, sent periodically just to remember that everything
    is working fine

Logging
-------

Operation log files are stored in */var/log/yabt*.

