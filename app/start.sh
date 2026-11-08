#!/bin/sh
# 常駐プロセスはないため、有効化時の処理は init.sh のみ
[ -f /tmp/debug_apkg ] && echo "APKG_DEBUG: $0 $@" >> /tmp/debug_apkg
exit 0
