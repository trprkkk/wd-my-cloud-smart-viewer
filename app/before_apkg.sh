#!/bin/sh
# パッケージ作成前フック(ログのみ)
[ -f /tmp/debug_apkg ] && echo "APKG_DEBUG: $0 $@" >> /tmp/debug_apkg
exit 0
