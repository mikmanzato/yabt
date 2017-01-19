#!/bin/sh

D=$(dirname $0)

# Check that we are root
UID=$(id -u)
if [ $UID -ne 0 ] ; then
	echo "Must run as root" 1>&2
	exit 1
fi

# Installation locations
BINDIR="/usr/local/bin"
ETCDIR="/usr/local/etc"
LIBDIR="/usr/local/share"
OLD_LIBDIR="/usr/local/lib"
LOGDIR="/var/log/yabt"
RUNDIR="/var/run"

# Remove all
sudo rm -rf $BINDIR/yabt $ETCDIR/yabt/ $LIBDIR/yabt/ $RUNDIR/yabt/
sudo rm -rf /etc/logrotate.d/yabt /etc/cron.d/yabt
sudo rm -rf "$LOGDIR"

if [ -e "$OLD_LIBDIR/yabt" ] ; then
	rm -rf "$OLD_LIBDIR/yabt"
fi

echo "Succesfully uninstalled."
