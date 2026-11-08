#!/bin/sh
# My Cloud 1st Gen 用 SMART Viewer インストールスクリプト
# 使い方: このスクリプトと index.php を NAS の Public 共有に置いて SSH で実行
#   sh /mnt/HD/HD_a2/Public/install.sh

set -e

echo "=== My Cloud SMART Viewer インストール ==="

# smartmontools の確認
if ! command -v smartctl >/dev/null 2>&1; then
    echo "smartctl が見つかりません。smartmontools をインストールします..."
    apt-get update || true
    apt-get install -y smartmontools || {
        echo "!! apt-get に失敗しました。手動で smartmontools を導入してください。" >&2
        exit 1
    }
fi
echo "smartctl: $(command -v smartctl)"

# 配置
SRC="$(dirname "$0")/app/web/index.php"
DEST_DIR="/var/www/smartinfo"
if [ ! -f "$SRC" ]; then
    echo "!! $SRC が見つかりません。リポジトリごと Public 共有に置いてください。" >&2
    exit 1
fi
mkdir -p "$DEST_DIR"
cp "$SRC" "$DEST_DIR/index.php"
chmod 755 "$DEST_DIR"
chmod 644 "$DEST_DIR/index.php"

# smartctl に sudo 経由でアクセスできるようにする
# (Web サーバーが www-data 権限で動作する場合のフォールバック。index.php 側で sudo -n を試行します)
SMARTCTL_PATH="$(command -v smartctl 2>/dev/null)"
if [ -n "$SMARTCTL_PATH" ] && command -v sudo >/dev/null 2>&1; then
    if ! grep -q "smartctl" /etc/sudoers 2>/dev/null; then
        echo "www-data ALL=(ALL) NOPASSWD: $SMARTCTL_PATH" >> /etc/sudoers
        echo "sudoers に smartctl の許可を追加しました。"
    fi
fi

echo ""
echo "=== 完了! ==="
echo "ブラウザで http://<NASのIP>/smartinfo/ にアクセスしてください。"
echo ""
echo "注意: ファームウェアを更新すると /var/www が初期化されて消える可能性があります。"
echo "その場合はこのスクリプトをもう一度実行してください。"
