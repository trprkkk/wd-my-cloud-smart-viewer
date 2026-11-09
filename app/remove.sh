#!/bin/sh
# remove.sh <アプリディレクトリ>
# アプリ本体と Web リンクを完全に削除する
LOG=/tmp/debug_apkg
APP=$(readlink -f "$1")
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
[ -n "$APP" ] && [ "$APP" != "/" ] && rm -rf "$APP"
exit 0
