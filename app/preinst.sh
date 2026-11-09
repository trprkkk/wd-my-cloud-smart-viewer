#!/bin/sh
# preinst.sh <アプリディレクトリ>
# 再インストール前に古いキャッシュを掃除する
LOG=/tmp/debug_apkg
APP=$(readlink -f "$1")
echo "APKG_DEBUG: $0 $@" >> $LOG

[ -n "$APP" ] && rm -f "$APP"/data/.refresh 2>/dev/null
rm -f /tmp/smartinfo_*.json 2>/dev/null
exit 0
