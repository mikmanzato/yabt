YABT - Yet Another Backup Tool
==============================

This is my take at building a backup tool for home or small office use.

I reviewed several existing open-source backup solutions but I found out
they were either too basic, too complicated, poorly extensible, or they just
missed the features that I needed. So I wrote yet another backup tool, or Yabt.

Yabt is a standalone backup solution. It does not need a central backup server,
client-server components or such. You install Yabt on the PC or server
where there are thigs to back up and configure as many dump/backup jobs as
required.

Yabt is also a front-end to a number of 3rd party backup utilities such as
rsync, rdiff-backup and duplicity which

It is good practice to store backups on a different machine. Set up a storage
space on your network and make it accessible over FTP/FTPS. You can then tell
Yabt to store dumps or backups to this location.


Features
--------

Yabt can dump local resources such as:

  * SVN repositories
  * MySQL databases (with optional filesystem-based storage)
  * local directories

to local directories or ftp/ftps/sftp servers.

Yabt can execute backups of local directories using:

  * rsync
  * rdiff-backup
  * duplicity

Yabt is developed in PHP. It was originally written and tested on a
Debian-based system and is known to run on Debian-based distributions.

Requirements
------------

Yabt requires a working command-line php installation. On GNU/Linux systems,
installing the `php-cli` package or equivalent should do. Yabt works on PHP 5.6
as well as PHP 7.

Yabt uses `tar` and `bzip2` which are generally available on any GNU/Linux
installation.

Depending on the configured jobs a number of external utilities are needed. For
example, if you configure a MySQL dump job the *mysqldump* program is required
and must be installed separately. Dependencies are pointed out in the
documentation for job configuration below.

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
  * default configuration files, if you use the `-c` option. Note that these
    configuration files will overwrite the existing ones you may have edited.

I will soon release a puppet moudule which automates installation and
configuration of Yabt.


Uninstallation
--------------

To uninstall Yabt, run:

    ./uninstall.sh

from the extracted package directory.


Quickstart
----------

(This section should contain a quick primer and basic configuration files.)


Configuration
-------------

Yabt is configured via configuration files placed in */usr/local/etc/yabt/*.
There is:

  * one main configuration file
  * several job configuration files in the *jobs.d* subdirectory

### Main configuration

The main configuration file is */usr/local/etc/yabt/main.conf*. It contains a
number of global settings

Format:

    [notifications]
    enabled=<0|1>
    from=<email>
    recipients=<email address>[,<email addresses>]
    smtp_hostname=<hostname>

    [log]
    file=<path>
    min_level=<0-7>

    [programs]
    tar_exe=<path>
    bzip2_exe=<path>

Parameters in the `[notifications]` section configure the email notifications.
Actual notifications are sent by notification jobs.

  * `enabled`: Whether to send email notifications.
  * `from`: Email address of the sender of the notification email.
  * `recipients`: Email address(es) of the recipients of the notification email.
  * `smtp_hostname`: The SMTP host to use to send email.

Parameters in the `[log]` section configure what messages and operations yabt
logs to file:

  * `file`: The log file. Normally Yabt logs to */var/log/yabt/yabt.log* which is
    rotated by logrotate. There should be no need to modify this setting.
  * `min_level`: The minimum level messages should have in order to be logged
    to file. Log levels are the following:
      * 0: Emergency (LOG_EMERG)
      * 1: Alert (LOG_ALERT)
      * 2: Critical (LOG_CRIT)
      * 3: Error (LOG_ERR)
      * 4: Warning (LOG_WARNING)
      * 5: Notice (LOG_NOTICE)
      * 6: Informative (LOG_INFO) <- default
      * 7: Debug (LOG_DEBUG)

Parameters in the `[programs]` section configure non-default paths to some
system utilities which are used across jobs:

  * `tar_exe`: Executable of the *tar* command. Defaults to */bin/tar*.
  * `bzip2_exe`: Executable of the *bzip2* command. Defaults to */bin/bzip2*.

The main configuration file can also contain common configuration which applies
to all relevant jobs. It can be useful to "reuse" configuration parameters
such as the location of generated files for dump jobs.

Example configuration file:

    ;/usr/local/etc/yabt/main.conf
    [notifications]
    enabled=1
    from=yabt@example.com
    recipients=me@example.com,you@example.com

    [log]
    min_level=5

    [job]
    recurrence=daily
    at=04:00

    [dump]
    location=ftps://myuser:mypass@ftphost/backups

    [rdiff-backup]
    destination=/var/backups


### Job configuration

#### General job configuration

All jobs share common configuration parameters in the `[job]` section of the
configuration file:

    [job]
    name=<name_of_job>
    type=<full name of the class implementing the job>
    enabled=<0|1>
    phase=<0..10>
    recurrence=<hourly|daily|weekly|monthly>
    at=<date/time of execution>

Parameters in the `[job]` section:

  * `name`: Descriptive mnemonic name of the job. It will be used to name the
    backup files or directories produced. It must be unique among all jobs.
  * `type`: The type of job to run. It is the full name of the PHP class which
    implemenents the job.
  * `enabled`: When 0 the job isn't executed
  * `phase`: Running phase. Jobs in a lower phase run earlier than jobs in a
    higher phase. Used to run notification jobs at later phases than dump and
    backup jobs.
  * `recurrence`: Defines how often the job is run. Possible choices are:
    `hourly`, `daily`, `weekly`, `monthly`
  * `at`: Defines when the job should be run. Format differs depending on the
    `recurrence` value:

      * for `hourly` recurrence it is in the format `:MM` where MM are the
        minutes in the hour
      * for `daily` recurrence it is in the format `HH:MM` where HH:MM is the
        hour and minutes in the day
      * for `weekly` recurrence it is in the format `dayname, HH:MM` where day
        is the name of the day either abbreviated or full (sun, mon, tue... or
        sunday, monday, tuesday, ...) and HH:MM is the hour and minutes in the
        day
      * for `monthly` recurrence it is in the format `D, HH:MM` where day is
        the day number in the month (1, 2, ...31) and HH:MM is the hour and
        minutes in the day

    Examples (which are also the defaults applied when the `at` parameter is
    omitted):

        at=05, 03:30    ; monthly recurrence
        at=Sun, 03:30   ; weekly recurrence
        at=03:30        ; daily recurrence
        at=:30          ; hourly recurrence

    Note that the job is not guaranteed to run at the time indicated in the `at`
    parameter. If there is another job running at the same time the job
    execution will be deferred.

  * `pre_cmd`: (available since version 1.2.0) A command to be executed before
    running the actual job. The command MUST return 0 (success) otherwise the
    job execution is stopped. The command may do some preparatory work (e.g.
    stopping some services which could interfere with a clean backup) or check
    some pre-conditions. Example:

        pre_cmd=/usr/sbin/service apache2 stop

  * `post_cmd`: (available since version 1.2.0) A command to be executed after
    the actual job has been run. The command may do some cleanup work (e.g.
    restart some services that were stopped by `pre_cmd`).

        post_cmd=/usr/sbin/service apache2 start


#### Dump jobs

Dump jobs produce "dump file"s out of the source content. The typical case are
MySQL dump jobs, which dump the contents of MySQL databases to file.

In dump jobs Yabt controls and takes care directly of the backup files
produced, as well as retention and incrementality. That is, Yabt knows which
files are produced, where they are located, manages incremental chains and
takes care of deleting old (expired) files or chains. In contrast, in other
jobs - such as the "rdiff-backup" job - incrementality and retention are
delegated to an external backup tool. Note that dump files are always produced
locally to the system where Yabt runs and then they are transferred to

A few configuration parameters are common to all dump jobs and are placed in
a separated section of the config file named after the dump job:

  * `retention_period`: Number of previous dumps to retain. Works together with the
    `recurrence` parameter in the `[job]` section: for daily recurrence the
    retention period is a number of days while for weekly recurrence the
    retention is a number of weeks
  * `full_period`: Number of runs after which a full dump is always executed.
  * `incremental`: 0 or 1. 1 activates incremental dumps. Yabt will store only
    differences from the previous dump, saving time and space. Not all dump
    jobs support incrementality.
  * `location`: Where dump files are stored. It is expressed in a URI-like
    format. It is a "root directory", each dump is stored in subdirectories of
    this directory. A number of possibilities are currently supported,
    described below.

    Local directory:

        location=file:///var/backups

    A space on a FTP server:

        location=ftp://username:password@hostname/path

    A space on a FTPS server (where possible this is to be preferred over FTP):

        location=ftps://username:password@hostname/path

    A space on a SFTP (ssh2) server:

        location=ssh2://username:password@hostname/path

#### Directory dump job

Stores all files from a local directory into a *.tar.bz2* file.

Directories are dumped using the `tar` command which is generally available on
any GNU/Linux installation. `bzip2` is also required for compression of the
resulting dumps.

Configuration file:

    [job]
    type=yabt\DirDumpJob
    phase=2
    ; common job parameters, see above
    name=<name_of_job>
    phase=<running phase, 0-10>
    recurrence=<hourly|daily|weekly|monthly>
    at=<date/time of execution>

    [dir]
    path=<path to directory to dump>
    ; common dump job parameters, see above
    retention_period=<retention>
    full_period=<full>
    incremental=<0|1>
    location=<dump location, local or remote>

Parameters in the `[job]` section:

  * `type`: must be `yabt\DirDumpJob`
  * `phase`: typically 2

Parameters in the `[dir]` section:

  * `path`: The local directory path to back up

Directory dump job can be effectively used with directories with a relatively
small number of small files, such as the */etc* directory. Larger directories,
or directories containing big files, are best backed-up with other jobs such as
the rsync job, the rdiff-backup job  or the duplicity job.

#### Subversion repositories dump job

Dumps [Subversion](https://subversion.apache.org/) repositories.

Subversion repositories are dumped using the `svnadmin dump` command. Hence,
this job requires the `svnadmin` command. `svnlook` is also required for
incremental backups. Both are generally available in the `subversion` package
of your GNU/Linux distribution. Also the job must run on the host which serves
SVN repositories, that is, where the svn repository directory is a local
directory. `bzip2` is also required for compression of the resulting dumps.

Incremental dumps are supported.

Configuration file:

    [job]
    type=yabt\SvnDumpJob
    phase=2
    ; common job parameters, see above
    name=<name_of_job>
    phase=<running phase, 0-10>
    recurrence=<hourly|daily|weekly|monthly>
    at=<date/time of execution>

    [svn]
    parent_path=<path>
    svnadmin_exe=<path to executable>
    svnlook_exe=<path to executable>
    ; common dump job parameters, see above
    retention_period=<retention>
    full_period=<full>
    incremental=<0|1>
    location=<dump location, local or remote>

Parameters in the `[job]` section:

  * `type`: must be `yabt\MysqlDbDumpJob`
  * `phase`: typically 2

Parameters in the `[svn]` section:

  * `parent_path`: Path of the directory which contains subversion repositories.
  * `svnadmin_exe`: Full path to the *svnadmin* executable. Defaults to
    */usr/bin/svnadmin*.
  * `svnlook_exe`: Full path to the *svnlook* executable. Defaults to
    */usr/bin/svnlook*.

> **LIMITATIONS:** The SVN server is not currently stopped during the dump job.

Example configuration file:

    ;/usr/local/etc/yabt/jobs.d/svnrepositories.conf
    [job]
    name=svnrepositories
    type=yabt\SvnDumpJob
    phase=2
    recurrence=daily
    at=00:30

    [svn]
    retention_period=15
    parent_path=/var/svnroot/repositories
    location=ftps://myftpuser:myftppass@ftphost/yabt/svnrepos


#### MySQL dump job

Dumps a [MySQL](https://www.mysql.com/) database. Optionally dumps also
content stored on the filesystem but referenced by the database.

Dumps are performed using the `mysqldump` utility which is generally available
in the `mysql-client` package of your GNU/Linux distribution. It can be run by
a remote server which has access to the database server and schema. `bzip2` is
required for compression of the resulting dumps. `tar` is also required to
archive the filesystem storage, when used.

Incremental dumps are not supported.

Configuration file:

    [job]
    type=yabt\MysqlDbDumpJob
    phase=2
    ; common job parameters, see above
    name=<name_of_job>
    phase=<running phase, 0-10>
    recurrence=<hourly|daily|weekly|monthly>
    at=<date/time of execution>

    [mysqldb]
    user=<db username>
    password=<db password>
    hostname=<db_host>
    dbname=<db_schema_name>
    storage_dir=<path to storage directory>
    mysqldump_exe=<path to executable>
    ; common dump job parameters, see above
    retention_period=<retention>
    full_period=<full>
    incremental=<0|1>
    location=<dump location, local or remote>

Parameters in the `[job]` section:

  * `type`: must be `yabt\MysqlDbDumpJob`
  * `phase`: typically 2

Parameters in the `[mysql]` section:

  * `user`: Username to connect to the database
  * `password`: Passowrd associated to the username
  * `hostname`: DB server host
  * `port`: The DB server TCP/IP port (optional)
  * `dbname`: The DB schema to back-up
  * `storage_dir`: Path of the filesystem directory where files are stored (optional)
  * `mysqldump_exe`: Full path to the *mysqldump* executable. Defaults
    to */usr/bin/mysqldump*.

> **LIMITATIONS:** The MySQL server is not currently stopped or put into
> any kind of "exclusive access" mode. Hence it could be possible that
> inconsistent data is backed up.

Example configuration file:

    ;/usr/local/etc/yabt/jobs.d/mysqldb_myapp.conf
    [job]
    name=mysqldb_myapp
    type=yabt\MysqlDbDumpJob
    phase=2

    [mysqldb]
    user=mydbuser
    password=mydbpass
    hostname=localhost
    dbname=myapp
    storage_dir=/var/lib/myapp/storage
    location=ftps://myftpuser:myftppass@ftphost/yabt


#### Duplicity job

Back up a local directory using [duplicity](http://duplicity.nongnu.org/), an
*encrypted bandwidth-efficient backup using the rsync algorithm*. Duplicity
backs up directories by producing encrypted tar-format volumes and uploading
them to a remote or local file server.

Duplicity jobs require the duplicity utility to be installed. It is generally
available in the `duplicity` package of your GNU/Linux distribution.

Configuration file:

    [job]
    type=yabt\DuplicityJob
    phase=7
    ; common job parameters, see above
    name=<name_of_job>
    phase=<running phase, 0-10>
    recurrence=<hourly|daily|weekly|monthly>
    at=<date/time of execution>

    [duplicity]
    source_dir=/path/to/directory
    retention_period=<days>
    full_period=<days>
    passphrase=<the_passphrase>
    duplicity_exe=<path_to_executable>

Parameters in the `[job]` section:

  * `type`: must be `yabt\DuplicityJob`
  * `phase`: typically 7

Parameters in the `[duplicity]` section:

  * `source_dir`: path to the local directory to back up
  * `target_url`: URL of the target location to backup to.
  * `retention_period`: Number of days to retain in the backup. Cleanup of old
    backup sets is performed with the `remove-all-but-n-full` duplicity command.
  * `full_period`: Number of days after which to run a full backup
  * `passphrase`: The passphrase which is used by duplicity to encrypt the
    remote backup files.
  * `duplicity_exe`: Full path to the duplicity executable. Defaults
    to */usr/bin/duplicity*.

Example configuration file for a daily backup of the directory */home/user*,
with a retention of 12 days and a full backup every 4 days:

    ;/usr/local/etc/yabt/jobs.d/duplicity.conf
    [job]
    name=duplicity
    type=yabt\DuplicityJob
    phase=7

    [duplicity]
    source_dir=/home/user
    target_url=ftp://ftpuser:ftppass@ftphost/backups/
    retention_period=12
    full_period=4
    passphrase=change_this_with_a_secret_passphrase


#### Rdiff-backup job

Back up a local directory using [rdiff-backup](http://www.nongnu.org/rdiff-backup/).

The target directory ends up a copy (mirror) of the source directory. Extra
reverse diffs are stored in a special subdirectory of that target directory, so
one can still recover files lost some time ago. rdiff-backup preserves
symlinks, special files, hardlinks, permissions, uid/gid ownership, and
modification times.

Rdiff-backup produces backup directories which are intelligible so files can be
easily found and restored on the destination. It is particularly suitable if one
is under full control of the backup location.

This job uses the `rdiff-backup` command to execute the actual backup. It is
generally available in the `rdiff-backup` package of your GNU/Linux
distribution. Note that, in order to access remote files, rdiff-backup opens up
a pipe to a copy of rdiff-backup running on the remote machine. Thus
rdiff-backup must be installed on both ends.

Configuration:

    [job]
    type=yabt\RdiffBackupJob
    phase=7
    ; common job parameters, see above
    name=<name_of_job>
    phase=<running phase, 0-10>
    recurrence=<hourly|daily|weekly|monthly>
    at=<date/time of execution>

    [rdiff-backup]
    source=/path/to/directory
    destination=<rdiff-backup destination>
    retention_period=<2Y>
    rdiffbackup_exe=<path to executable>

Parameters in the `[job]` section:

  * `type`: must be `yabt\RdiffBackupJob`
  * `phase`: typically 7

Parameters in the `[rdiff-backup]` section:

  * `source`: Path to local directory to back up
  * `retention_period`: Remove the incremental backup  information  in  the
    destination directory that has been around longer than the given time.
    time_spec can be either an absolute time, like "2002-01-04",  or a  time
    interval. The time interval is an integer followed by the character s, m,
    h, D, W, M, or Y, indicating  seconds,  minutes,  hours,  days,  weeks,
    months, or years respectively, or a number of these concatenated.  For
    example, 32m  means  32  minutes,  and 3W2D10h7s means 3 weeks, 2 days,
    10 hours, and 7 seconds. See also rdiff-backup's `--remove-older-than`
    option.
  * `destination`: The location where to store the backup copy. It can be a
    local destination:

        /path/to/local/directory

    or a destination on a remote machine:

        user@hostname::/path/to/remote/directory

    In this case you should pre-initialize access to the remote machine by
    copying the public SSH key [for example as it is explained here](https://www.howtoforge.com/linux_rdiff_backup).
  * `rdiffbackup_exe`: Full path to the rdiffbackup executable. Defaults to
    */usr/bin/rdiff-backup*.

Example configuration file:

    ;/usr/local/etc/yabt/jobs.d/svnrepositories.conf
    [job]
    name=rdiff-backup
    type=yabt\RdiffBackupJob
    phase=7
    enabled=0

    [rdiff-backup]
    source=/home/user
    retention_period=2Y
    destination=/var/backups/svn


#### Rsync job

Back up a local directory using [rsync](https://rsync.samba.org/).

Rsync is an open source utility that provides fast incremental file transfer.
It is famous for its delta-transfer algorithm, which reduces the amount of data
sent over the network by sending only the differences between the source files
and the existing files in the destination. Rsync is widely used for backups and
mirroring and as an improved copy command for everyday use.

This job uses the `rsync` command to perform the actual backup. It is generally
available in the `rsync` package of your GNU/Linux distribution, which most of
the times is installed by default. If you use this kind of job you should
consider configuring a rsync daemon on the remote server since this makes rsync
much faster.

Rsync backup jobs are natively incremental. However, retention is not supported.

Configuration:

    [job]
    type=yabt\RsyncJob
    phase=7
    ; common job parameters, see above
    name=<name_of_job>
    phase=<running phase, 0-10>
    recurrence=<hourly|daily|weekly|monthly>
    at=<date/time of execution>

    [rsync]
    source=/path/to/directory
    destination=<rsync destination>
    rsync_exe=<path to executable>

Parameters in the `[job]` section:

  * `type`: must be `yabt\RsyncJob`
  * `phase`: typically 7

Parameters in the `[rsync]` section:

  * `source`: Path to local directory to back up
  * `destination`: Where to back-up files to. Examples are:

       * `user:pass@rsynchost:/path`: back up to a remote rsync server (recommended)
       * `/var/backups/`: back up to a local directory

    Please check the rsync documentation or tutorials for additional
    information on how to configure rsync.

  * `rsync_exe`: Full path to the rsync executable. Defaults to
    */usr/bin/rsync*.

Example configuration file to back-up the home directory of user *myuser*:

    ;/usr/local/etc/yabt/jobs.d/rsync-home-myuser.conf
    [job]
    name=rsync-home-myuser
    type=yabt\RsyncJob
    phase=5

    [rsync]
    source=/home/myuser/
    destination=user:pass@backuphost/var/backups


#### Status notification job

Send a notification about the status of the configured jobs. The notification
may be always sent or sent only if problems are detected.

Configuration file:

    [job]
    type=yabt\StatusNotificationJob
    phase=9
    ; common job parameters, see above
    name=<name_of_job>
    phase=<running phase, 0-10>
    recurrence=<hourly|daily|weekly|monthly>
    at=<date/time of execution>

    [status-notification]
    complete=<0|1>

Parameters in the `[job]` section:

  * `type`: must be `yabt\StatusNotificationJob`
  * `phase`: Status notifications are typically sent after all dump and backup
    jobs have been completed. Hence they are typically allocated to phase 9.

Parameters in the `[status-notification]` section:

  * `complete`: When 1 a complete report is produced, including also successful
    jobs. When 0 only failed backups are notified.

It is common practice to configure two notification jobs:
  * a daily job with `complete=0` to notify just failures
  * a weekly or monthly jobs with `complete=1` to provide a complete report and to
    ensure that notifications are sent correctly.

Example configuration file for a monthly complete report:

    ;/usr/local/etc/yabt/jobs.d/sn-monthly.conf
    [job]
    name=sn-monthly
    type=yabt\StatusNotificationJob
    phase=9
    recurrence=monthly
    at=2, 03:30

    [status-notification]
    complete=1

Example configuration file for a daily report which is sent only if problems
are detected:

    ;/usr/local/etc/yabt/jobs.d/sn-daily.conf
    [job]
    name=sn-daily
    type=yabt\StatusNotificationJob
    phase=9
    recurrence=daily
    at=03:30

    [status-notification]
    complete=0


Other issues
------------

### Prevention of concurrent executions

When Yabt is started a lock file is created in */var/lock/yabt.lock*. This
prevents multiple instance of Yabt from running concurrently.

External applications can check the lock file for existence to see if
Yabt is in execution.

### Logging

Operation log files are stored in */var/log/yabt/yabt.log*. Log file rotation
is delegated to the *logrotate* daemon (configuration is in
*/etc/logrotate.d/yabt*)

The log file can be changed by editing the `log_file` configuration property in
the `[log]` section of the main configuration file. Note that if you change the
default log file you must also take care of adjusting log rotation accordingly.

### Scheduling

After installation Yabt automatically runs every minute as a cron job.
Individual Jobs are run according to the scheduling configured in the
job files.

If you want to schedule a different cron execution policy you can edit file
*/etc/cron.d/yabt*.


Running Yabt from the command line
----------------------------------

Yabt can be run manually from the command line or programmatically from a
script. The syntax is:

    /usr/local/bin/yabt [options...] [command]

The following options are accepted:

  * `-c|--confdir <dir>`: Use directory *<dir>* as the configuration directory
    instead of the default directory */usr/lcoal/etc/yabt*
  * `-d|--disable-report`: Do not send notification reports by email
  * `-f|--force`: Force job execution. Can be used together with `-j` option to
    force execution of a specific job
  * `-j|--job <job_name>`: Run only the job whose name is <job_name>.
  * `-l|--loglevel <num>`: Apply this level for console log notifications. Log
    levels are 1 (emergency, highest) to 7 (debug, lowest).

    > **Note:** Log levels are the same as PHP's `LOG_XXX` constants

  * `-v|--verbose`: Provide verbose info
  * `--version`: Display Yabt version and exit

The following commands are understood:

  * `execute`: the default command, run jobs according to scheduling
  * `status`: display the status of all jobs on the command line
  * `notify-status`: send a notification with the full job status


Licensing
---------

Copyright (c) Michele Manzato.

Yabt is open source software licensed under the MIT License. Basically, you are
free to use Yabt in any commercial or non-commercial project, you can modify
the code as you wish as long as you don't change licensing and retain the
original copyright statement.

Yabt includes a copy of the following PHP libraries:

  * [PHPMailer](http://...) - Version 5.1

    Copyright (c) 2004-2009 Andy Provost. All Rights Reserved.
    Copyright (c) 2001-2003 Brent R. Matzelle.

    PHPMailer is licensed under the Lesser General Public License (LGPL).


Disclaimer
----------

Yabt is released as-is. I cannot guarantee fitness of Yabt for any particular
use and I cannot assume any direct or indirect liability should your system or
your data be damaged or lost due to proper or improper use of Yabt, or due to
bugs in Yabt itself or third-party programs run by Yabt.
