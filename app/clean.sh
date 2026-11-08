#!/bin/sh
# clean.sh <アプリディレクトリ>
# アプリ無効化時に Web へのリンクを削除する
LOG=/tmp/debug_apkg
echo "APKG_DEBUG: $0 $@" >> $LOG
rm -rf /var/www/apps/smartinfo
rm -f /tmp/smartinfo_*.json 2>/dev/null
exit 0
