#!/bin/sh
# インストール前フック(旧バージョンのキャッシュを掃除)
LOG=/tmp/debug_apkg
echo "APKG_DEBUG: $0 $@" >> $LOG
rm -f /tmp/smartinfo_*.json 2>/dev/null
exit 0
