# WD My Cloud SMART Viewer

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
![PHP](https://img.shields.io/badge/PHP-no%20dependencies-777bb4.svg)
![Platform](https://img.shields.io/badge/Platform-WD%20My%20Cloud%201st%20Gen-blue.svg)

WD My Cloud 1st Gen(My Cloud OS 3 系)の内蔵 HDD の **S.M.A.R.T. 情報をブラウザで閲覧する**、依存ライブラリ不要の単一 PHP アプリです。

*View the S.M.A.R.T. health of your WD My Cloud 1st Gen's internal drive from a web browser. A single-file, dependency-free PHP app.*

<!-- スクリーンショットを追加する場合は docs/screenshot.png を置いて下記を有効化 -->
<!-- ![Screenshot](docs/screenshot.png) -->

## 特徴

- **健康状態をひと目で** — `smartctl -H` の PASSED / FAILED をバッジ表示
- **SMART 属性一覧** — 現在値・最悪値・閾値・RAW値に加え、主要属性の日本語注釈付き
  - 代替済みセクタ数、保留セクタ数、通電時間、温度 など
- **デバイス情報** — モデル、シリアル番号、ファームウェア、容量、回転数
- **生の smartctl 出力** — 折りたたみで確認可能
- **単一ファイル・依存なし** — 外部ライブラリやビルド不要。`index.php` を置くだけ
- **閲覧専用** — HDD への書き込みや設定変更は一切行いません

## セットアップ

### 1. SSH を有効にする(まだの場合)

1. ブラウザで `http://<NASのIP>/`(wdcloud ダッシュボード)を開く
2. **設定 → ネットワーク → SSH** を開く
3. **SSH アクセスを ON** にし、パスワードを設定
   - 既定ユーザー: `root`(ファームウェアによっては `admin`)
4. 接続確認:
   ```sh
   ssh root@<NASのIP>
   ```

> ファームウェア v04 の一部にはダッシュボードに SSH 項目がありません。
> その場合は My Cloud OS 3 へ更新するか、別途有効化手順が必要です。

### 2. ファイルを転送する

`smartinfo/` の中身(`index.php` と `install.sh`)を NAS の **Public 共有** にコピーします。
エクスプローラーで `\\<NASのIP>\Public` を開いてドラッグ&ドロップするだけで OK です。

### 3. インストールする

SSH でログインして:

```sh
cd /mnt/HD/HD_a2/Public
sh install.sh
```

スクリプトが自動で行うこと:

- `smartmontools` が無ければインストール
- `/var/www/smartinfo/index.php` にアプリを配置
- Web サーバー(www-data)が `smartctl` を sudo 実行できるよう sudoers に追加

### 4. アクセスする

```
http://<NASのIP>/smartinfo/
```

## 構成

| ファイル | 説明 |
|---|---|
| `index.php` | アプリ本体(単一ファイル、外部ライブラリ不要) |
| `install.sh` | NAS 上でのインストールスクリプト |
| `README.md` | このドキュメント |
| `LICENSE` | MIT ライセンス |

## 動作環境

| 項目 | 内容 |
|---|---|
| 本体 | WD My Cloud 1st Gen(Armada 370 / ARMv7) |
| ファームウェア | My Cloud OS 3(v04.xx) |
| Web サーバー | 付属 Apache + PHP |
| 必須コマンド | `smartctl`(smartmontools 7.x で動作確認) |

## 注意事項

- **ファームウェア更新で消える可能性があります。** その場合は `install.sh` を再実行してください(Public 共有に置いたままにしておけば数秒で復旧)。
- NAS がインターネットへ公開されている環境では第三者もアクセスできます。**同一 LAN 内での使用を推奨**します。
- 追加ディスクを監視したい場合は `index.php` 冒頭の `$DISKS` 配列に `/dev/sdb` などを追加してください。

## トラブルシューティング

| 症状 | 対処 |
|---|---|
| 「smartctl が見つかりません」と表示 | `apt-get install -y smartmontools` を実行、または `index.php` の `$SMARTCTL` パスを修正 |
| 属性テーブルが空 | SSH で `smartctl -a /dev/sda` が動くか確認。`install.sh` を再実行して sudoers 設定を反映 |
| ページが 404 | `ls /var/www/smartinfo/` で配置を確認。Apache のドキュメントルートが異なる場合は実体に合わせて配置先を変更 |

## ライセンス

[MIT](LICENSE)
