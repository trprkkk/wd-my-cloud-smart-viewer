#!/bin/sh
# 常駐プロセスはないため、無効化時の処理は clean.sh のみ
[ -f /tmp/debug_apkg ] && echo "APKG_DEBUG: $0 $@" >> /tmp/debug_apkg
exit 0
