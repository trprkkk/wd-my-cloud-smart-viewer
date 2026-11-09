#!/bin/sh
# init.sh <アプリディレクトリ>
# 毎回の起動時に Web ページへのシンボリックリンクを張り直す
LOG=/tmp/debug_apkg
APP=$(readlink -f "$1")
WEB_PATH=/var/www/apps/smartinfo

echo "APKG_DEBUG: $0 $@" >> $LOG

mkdir -p "$WEB_PATH"
ln -sf "$APP"/web/* "$WEB_PATH"/ 2>>$LOG

mkdir -p "$APP/data"
chmod 777 "$APP/data" 2>/dev/null

echo "init: linked $APP/web -> $WEB_PATH" >> $LOG
exit 0
