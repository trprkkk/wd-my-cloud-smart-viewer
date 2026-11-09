#!/bin/sh
# clean.sh
# アプリ無効化/削除時に Web へのリンクとコレクタを片付ける
LOG=/tmp/debug_apkg
PID_FILE=/var/run/smartinfo.pid

echo "APKG_DEBUG: $0 $@" >> $LOG

if [ -f "$PID_FILE" ]; then
    PID=$(cat "$PID_FILE" 2>/dev/null)
    [ -n "$PID" ] && kill "$PID" 2>/dev/null
    rm -f "$PID_FILE"
fi
pkill -f "Nas_Prog/smartinfo/bin/collector.sh" 2>/dev/null

rm -rf /var/www/apps/smartinfo
rm -f /tmp/smartinfo_*.json 2>/dev/null
exit 0
