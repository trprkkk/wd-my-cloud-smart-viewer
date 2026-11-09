#!/bin/sh
# install.sh <アップロード先ディレクトリ> <Nas_Prog>
# 呼び出し: $UPLOAD_PATH/install.sh $UPLOAD_PATH $INSTALL_PATH
LOG=/tmp/debug_apkg
echo "APKG_DEBUG: $0 $@" >> $LOG

UPLOAD=$(readlink -f "$1")
APPS=$(readlink -f "$2")
APP="${APPS}/smartinfo"

if [ -z "$UPLOAD" ] || [ -z "$APPS" ]; then
    echo "install: invalid arguments ($1 / $2)" >> $LOG
    exit 1
fi

echo "install: ${UPLOAD} -> ${APP}" >> $LOG

mkdir -p "$APPS"
rm -rf "$APP"
mv "$UPLOAD" "$APP" 2>>$LOG || cp -a "$UPLOAD" "$APP" 2>>$LOG

# データ用ディレクトリ(Web サーバーからも書き込めるようにする)
mkdir -p "$APP/data"
chmod 777 "$APP/data" 2>/dev/null

# smartctl の場所を記録(root コレクタが参照する)
SMARTCTL=""
for p in /usr/sbin/smartctl /usr/local/sbin/smartctl /usr/bin/smartctl /sbin/smartctl /opt/bin/smartctl; do
    if [ -x "$p" ]; then SMARTCTL="$p"; break; fi
done
if [ -z "$SMARTCTL" ]; then
    SMARTCTL=$(command -v smartctl 2>/dev/null)
fi
if [ -n "$SMARTCTL" ]; then
    echo "$SMARTCTL" > "$APP/data/smartctl.path"
    chmod 644 "$APP/data/smartctl.path"
    echo "install: smartctl=${SMARTCTL}" >> $LOG
else
    echo "install: smartctl not found" >> $LOG
fi

exit 0
