#!/bin/sh
# start.sh <アプリディレクトリ>
# root 権限で SMART 情報を収集するコレクタを起動する
LOG=/tmp/debug_apkg
APP=$(readlink -f "$1")
PID_FILE=/var/run/smartinfo.pid

echo "APKG_DEBUG: $0 $@" >> $LOG

mkdir -p "$APP/data"
chmod 777 "$APP/data" 2>/dev/null

if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE" 2>/dev/null)
    if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
        echo "start: already running (pid $PID)" >> $LOG
        exit 0
    fi
    rm -f "$PID_FILE"
fi

nohup sh "$APP/bin/collector.sh" "$APP" >> "$APP/data/collector.out" 2>&1 &
echo $! > "$PID_FILE"
echo "start: collector pid $(cat $PID_FILE)" >> $LOG
exit 0
