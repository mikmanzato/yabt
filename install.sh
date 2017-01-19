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
LOGDIR="/var/log/yabt"
STATUSDIR="/var/lib/yabt"

# Obsolete installation locations
OLD_LIBDIR="/usr/local/lib"
OLD_RUNDIR="/var/run/yabt"

# Copy files
cp $D/bin/* $BINDIR
mkdir -p $LIBDIR/yabt
cp -R $D/lib/* $LIBDIR/yabt

if [ ! -d "$ETCDIR/yabt" -o "$1" = "-c" ] ; then
	echo "Installing config files"
	mkdir -p $ETCDIR/yabt
	cp -R $D/etc/yabt/* $ETCDIR/yabt/
fi

cp $D/etc/cron.d/yabt /etc/cron.d/yabt
cp $D/etc/logrotate.d/yabt /etc/logrotate.d/yabt
mkdir -p "$LOGDIR"
mkdir -p "$STATUSDIR"

if [ -d "$OLD_RUNDIR" ] ; then
	mv $OLD_RUNDIR/status/* "$STATUSDIR"
	rm -rf "$OLD_RUNDIR"
fi

if [ -e "$OLD_LIBDIR/yabt" ] ; then
	rm -rf "$OLD_LIBDIR/yabt"
fi

echo "Succesfully installed."
