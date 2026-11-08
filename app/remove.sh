#!/bin/sh
# remove.sh <アプリディレクトリ>
# アプリ本体と Web リンクを完全に削除する
LOG=/tmp/debug_apkg
APKG_PATH=$(readlink -f "$1")
echo "APKG_DEBUG: $0 $@" >> $LOG

rm -rf "$APKG_PATH"
rm -rf /var/www/apps/smartinfo
rm -f /tmp/smartinfo_*.json 2>/dev/null
exit 0
