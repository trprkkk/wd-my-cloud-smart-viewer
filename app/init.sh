#!/bin/sh
# init.sh <アプリディレクトリ>
# 毎回の起動時に Web ページへのシンボリックリンクを張り直す
LOG=/tmp/debug_apkg
APKG_PATH=$(readlink -f "$1")
WEB_PATH=/var/www/apps/smartinfo

echo "APKG_DEBUG: $0 $@" >> $LOG

mkdir -p "$WEB_PATH"
ln -sf "$APKG_PATH"/web/* "$WEB_PATH"/ 2>>$LOG

echo "init: linked $APKG_PATH/web -> $WEB_PATH" >> $LOG
exit 0
