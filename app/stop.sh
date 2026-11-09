#!/bin/sh
# stop.sh
# コレクタを停止する
LOG=/tmp/debug_apkg
PID_FILE=/var/run/smartinfo.pid

echo "APKG_DEBUG: $0 $@" >> $LOG

if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE" 2>/dev/null)
    if [ -n "$PID" ] && kill -0 "$PID" 2>/dev/null; then
        kill "$PID" 2>/dev/null
        i=0
        while kill -0 "$PID" 2>/dev/null && [ "$i" -lt 5 ]; do
            sleep 1
            i=$((i + 1))
        done
        kill -9 "$PID" 2>/dev/null
    fi
    rm -f "$PID_FILE"
fi

# 取り残し対策
pkill -f "Nas_Prog/smartinfo/bin/collector.sh" 2>/dev/null

echo "stop: stopped" >> $LOG
exit 0
