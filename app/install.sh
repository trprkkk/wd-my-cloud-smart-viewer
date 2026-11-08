#!/bin/sh
# install.sh <展開先ディレクトリ> <Nas_Prog ディレクトリ>
# パッケージ内容を /mnt/HD/HD_a2/Nas_Prog/smartinfo へ配置する
LOG=/tmp/debug_apkg
echo "APKG_DEBUG: $0 $@" >> $LOG

INSTALL_DIR=$(readlink -f "$1")
NAS_PROG=$(readlink -f "$2")
APKG_PATH="${NAS_PROG}/smartinfo"

echo "install: ${INSTALL_DIR} -> ${APKG_PATH}" >> $LOG

mkdir -p "$NAS_PROG"
rm -rf "$APKG_PATH"
mv "$INSTALL_DIR" "$APKG_PATH" 2>>$LOG || cp -a "$INSTALL_DIR" "$APKG_PATH"

# キャッシュ用ディレクトリ(Web サーバーから書き込めるようにする)
mkdir -p "$APKG_PATH/data"
chmod 777 "$APKG_PATH/data" 2>/dev/null

# smartctl の場所を記録しておく(Web 側で参照)
SMARTCTL=$(command -v smartctl 2>/dev/null)
if [ -z "$SMARTCTL" ]; then
    for p in /usr/sbin/smartctl /usr/local/bin/smartctl /usr/bin/smartctl /opt/bin/smartctl; do
        [ -x "$p" ] && SMARTCTL="$p" && break
    done
fi
if [ -n "$SMARTCTL" ]; then
    echo "$SMARTCTL" > "$APKG_PATH/data/smartctl.path"
    chmod 644 "$APKG_PATH/data/smartctl.path"
    echo "smartctl: ${SMARTCTL}" >> $LOG
else
    echo "smartctl not found. Install smartmontools." >> $LOG
fi

exit 0
