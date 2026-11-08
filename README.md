# WD My Cloud SMART Viewer

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
![Platform](https://img.shields.io/badge/Platform-My%20Cloud%20OS3%20%2F%20OS5-blue.svg)
![PHP](https://img.shields.io/badge/PHP-5.3%2B-777bb4.svg)

WD My Cloud の内蔵 HDD の **S.M.A.R.T. 情報をブラウザで閲覧する**アプリです。
My Cloud のダッシュボードに **通常のアプリと同じ形でインストール**でき、アイコンから起動できます。

*View the S.M.A.R.T. health of your WD My Cloud's internal drive from the dashboard. Installs as a regular My Cloud app (.bin).*

<!-- スクリーンショットを追加する場合は docs/screenshot.png を置いて下記を有効化 -->
<!-- ![Screenshot](docs/screenshot.png) -->

## 特徴

- **健康状態をひと目で** — `smartctl -H` の PASSED / FAILED をバッジ表示
- **SMART 属性一覧** — 現在値・最悪値・閾値・RAW値に加え、主要属性の日本語注釈付き
  - 代替済みセクタ数、保留セクタ数、通電時間、温度 など
- **デバイス情報** — モデル、シリアル番号、ファームウェア、容量、回転数
- **生の smartctl 出力** — 折りたたみで確認可能
- **単一ファイル・依存なし** — 外部ライブラリやビルド不要
- **閲覧専用** — HDD への書き込みや設定変更は一切行いません

## インストール(アプリとして)

### 1. 自分の OS を確認する

ダッシュボードの **設定 → システム更新** でファームウェアを確認します。

| ファームウェア | 使用するファイル |
|---|---|
| 5.x(My Cloud OS 5) | `WDMyCloud_smartinfo_1.0.0_OS5.bin` |
| 4.x(My Cloud OS 3) | `WDMyCloud_smartinfo_1.0.0_OS3.bin` |

### 2. `.bin` をダウンロードする

[Releases](../../releases) から該当するファイルをダウンロードします。

### 3. ダッシュボードからインストールする

1. ダッシュボードの **アプリ** を開く
2. **アプリを追加**(手動インストール)を選択
3. ダウンロードした `.bin` を指定してインストール
4. 一覧に **SMART Viewer** が表示されるので **有効化** する
5. アイコンをクリックすると SMART 情報が表示されます

インストール先は `/mnt/HD/HD_a2/Nas_Prog/smartinfo`、Web ページは
`/var/www/apps/smartinfo/` にシンボリックリンクされます。

> **対応モデル**: `WDMyCloud`(1st Gen)。他のモデルで使う場合は
> [ビルド方法](#ビルド方法)を参照して `-m` のモデル名を変えてください。

## インストール(単体 PHP として / SSH)

アプリ化せず、URL で直接開くこともできます。

1. SSH を有効化(設定 → ネットワーク → SSH)
2. リポジトリを NAS の Public 共有にコピー
3. 実行:

```sh
cd /mnt/HD/HD_a2/Public/wd-my-cloud-smart-viewer
sh install.sh
```

`http://<NASのIP>/smartinfo/` で開けます。

## 動作環境

| 項目 | 内容 |
|---|---|
| 本体 | WD My Cloud 1st Gen(Armada 370 / ARMv7) |
| ファームウェア | My Cloud OS 3(v04.xx)または My Cloud OS 5(v5.xx) |
| Web サーバー | 付属 Apache + PHP(PHP 5.3+ で動作) |
| 必須コマンド | `smartctl`(smartmontools) |

## ビルド方法

`.bin` は WD のパッケージツール `mksapkg` で作成します(リポジトリには含めていません)。
Docker があれば 1 コマンドでビルドできます。

```sh
cd app

# OS5 向け
./build.sh OS5 WDMyCloud

# OS3 向け
./build.sh OS3 WDMyCloud
```

- 出力: `WDMyCloud_smartinfo_<version>_<OS>.bin`
- `mksapkg` は [WDCommunity/wdpksrc](https://github.com/WDCommunity/wdpksrc) から取得します
- 署名は OS3 = `bf-cbc/md5`、OS5 = `bf-cbc/sha256` と異なるため、それぞれ専用のツールでビルドします

## 構成

```
.
├── app/                     # My Cloud アプリのソース
│   ├── apkg.rc              # アプリ情報(名前・アイコン・埋め込み設定)
│   ├── build.sh             # .bin ビルドスクリプト
│   ├── install.sh           # インストール時に実行
│   ├── init.sh              # 起動時に /var/www/apps/ へリンク
│   ├── start.sh / stop.sh   # 常駐なし(no-op)
│   ├── clean.sh / remove.sh # 無効化・削除
│   └── web/
│       ├── index.php        # アプリ本体(単一ファイル)
│       └── smartinfo.png    # アイコン
├── install.sh               # 単体 PHP 用インストーラ
├── LICENSE
└── README.md
```

`app/apkg.rc` の `CenterType: 1` により、ダッシュボード埋め込み型アプリ
(`/var/www/apps/<pkg>/`)として表示されます。

## 注意事項

- **ファームウェア更新で消える可能性があります。** その場合は `.bin` を再インストールしてください。
- NAS がインターネットへ公開されている環境では第三者もアクセスできます。**同一 LAN 内での使用を推奨**します。
- 追加ディスクを監視したい場合は `app/web/index.php` 冒頭の `$DISKS` 配列に `/dev/sdb` などを追加してください。

## トラブルシューティング

| 症状 | 対処 |
|---|---|
| 「smartctl が見つかりません」と表示 | SSH で `smartctl -a /dev/sda` が動くか確認。必要なら `apt-get install -y smartmontools` |
| 属性テーブルが空 | Web サーバーから `smartctl` を実行できる権限か確認(`sudo -n smartctl -a /dev/sda`) |
| アプリ一覧に出ない | OS と `.bin` の種類(OS3/OS5)が一致しているか確認 |
| インストールに失敗する | モデル名が `WDMyCloud` か確認。他モデルは `./build.sh OS5 <モデル名>` でビルド |

## ライセンス

[MIT](LICENSE)
