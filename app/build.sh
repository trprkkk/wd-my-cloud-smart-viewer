#!/bin/sh
# OS3 / OS5 アプリ(.bin)をビルドする
#
# 使用方法:
#   ./build.sh [OS5|OS3] [モデル名]
#   例: ./build.sh OS5 WDMyCloud
#       ./build.sh OS5 WDCloud
#       ./build.sh OS3 WDMyCloud
#
# 必要なもの: Docker
# mksapkg は WDCommunity/wdpksrc から取得します(リポジトリには含めません)。
set -e

cd "$(dirname "$0")"

OS="${1:-OS5}"
MODEL="${2:-WDMyCloud}"
VERSION=$(awk '/^Version:/{print $2}' apkg.rc)
MKSAPKG_REV="e5a759b98f9e71c4c519b8a762a384e1117ad075"

case "$OS" in
    OS5) TOOL="mksapkg-OS5" ;;
    OS3) TOOL="mksapkg-OS3" ;;
    *) echo "usage: $0 [OS5|OS3] [model]" >&2; exit 1 ;;
esac
MKSAPKG_URL="https://raw.githubusercontent.com/WDCommunity/wdpksrc/${MKSAPKG_REV}/${TOOL}"

# パッケージのトップレベルが smartinfo/ になるように組み立てる
rm -rf .build
mkdir -p .build/smartinfo
cp -a apkg.rc before_apkg.sh preinst.sh install.sh init.sh start.sh stop.sh clean.sh remove.sh bin web .build/smartinfo/
chmod +x .build/smartinfo/*.sh .build/smartinfo/bin/*.sh 2>/dev/null || true

echo "Building ${MODEL}_smartinfo_${VERSION}_${OS}.bin"

docker run --rm \
    -v "$(pwd)/.build:/work" \
    -w /work/smartinfo \
    ubuntu:20.04 \
    sh -c "apt-get update -qq && apt-get install -y -qq libxml2 openssl curl ca-certificates >/dev/null && \
           curl -fsSL '${MKSAPKG_URL}' -o /tmp/mksapkg && chmod +x /tmp/mksapkg && \
           /tmp/mksapkg -E -s -m ${MODEL}"

# mksapkg は "モデル_名前_版.bin(日付)" を親ディレクトリに出力する
BUILT=$(ls .build/${MODEL}_smartinfo_${VERSION}.bin* 2>/dev/null | head -n1)
if [ -z "$BUILT" ]; then
    echo "ビルドに失敗しました" >&2
    exit 1
fi
mv "$BUILT" "../${MODEL}_smartinfo_${VERSION}_${OS}.bin"
echo "Done: ../${MODEL}_smartinfo_${VERSION}_${OS}.bin"
