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

- **健康状態をひと目で** — `smartctl` の PASSED / FAILED をバッジ表示
- **SMART 属性一覧** — 現在値・最悪値・閾値・RAW値に加え、主要属性の日本語注釈付き
  - 代替済みセクタ数、保留セクタ数、通電時間、温度 など
- **デバイス情報** — モデル、シリアル番号、ファームウェア、容量、回転数
- **生の smartctl 出力** — 折りたたみで確認可能
- **root コレクタ方式** — OS5 の Web サーバー(www-data)は `/dev/sda` を直接読めないため、
  root で動く小さなコレクタが SMART 情報を収集し、PHP はその結果を表示します
- **閲覧専用** — HDD への書き込みや設定変更は一切行いません

## 対応モデルとファイルの選び方

`.bin` は **モデル名** と **OS(OS3 / OS5)** の組み合わせで異なります。
まず SSH で次を実行して、自分の NAS のモデル名を確認してください
(SSH はダッシュボードの **設定 → ネットワーク → SSH** で有効化できます)。

```sh
# モデル名
grep modelName /etc/system.conf
cat /usr/local/modules/files/model

# ファームウェアの系統
cat /etc/version
```

| モデル名 | OS5 (5.x) | OS3 (4.x) |
|---|---|---|
| `WDMyCloud` | `WDMyCloud_smartinfo_1.0.1_OS5.bin` | `WDMyCloud_smartinfo_1.0.1_OS3.bin` |
| `WDCloud` | `WDCloud_smartinfo_1.0.1_OS5.bin` | — |
| その他 | [ビルド方法](#ビルド方法)参照 | 同左 |

> `WDMyCloud` と `WDCloud` は同じ 1 ベイ機(コードネーム Glacier)ですが、
> パッケージ内のモデル ID が異なります。**必ず自分のモデル名と一致するファイル**を使ってください。
> 一致しないファイルを入れると「アプリをインストールできませんでした。」になります。

## インストール(ダッシュボードから)

1. [Releases](https://github.com/trprkkk/wd-my-cloud-smart-viewer/releases) から
   自分のモデル・OS に合った `.bin` をダウンロードします。
2. ダッシュボードの **アプリ** を開きます。
3. **アプリを追加**(手動インストール)を選択し、`.bin` を指定してインストールします。
4. 一覧に **SMART Viewer** が表示されるので **有効化** します。
5. アイコンをクリックすると SMART 情報が表示されます。
   初回はコレクタの収集に数秒かかります。表示されない場合は **再取得** を押してください。

インストール先は `/mnt/HD/HD_a2/Nas_Prog/smartinfo`、Web ページは
`/var/www/apps/smartinfo/` にシンボリックリンクされます。

## インストール(SSH / CLI から)

ダッシュボードからのインストールに失敗する場合は、SSH から直接インストールできます。

```sh
cd /usr/local/apps_upload
# .bin をこのディレクトリに置いてから実行
upload_apkg -r 'WDMyCloud_smartinfo_1.0.1_OS5.bin' -f 1 -g smartinfo -m

# 結果確認(1 = 成功)
cat /tmp/upload_apkg_status
tail -50 /tmp/debug_apkg
```

インストール後、ダッシュボードの **アプリ** で **SMART Viewer** を有効化してください。

## インストール(単体 PHP として)

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
| 必須コマンド | `smartctl`(smartmontools 7.x 推奨) |

## ビルド方法

`.bin` は WD のパッケージツール `mksapkg` で作成します(リポジトリには含めていません)。
Docker があれば 1 コマンドでビルドできます。

```sh
cd app

# OS5 向け
./build.sh OS5 WDMyCloud
./build.sh OS5 WDCloud

# OS3 向け
./build.sh OS3 WDMyCloud
```

- 出力: `<モデル名>_smartinfo_<version>_<OS>.bin`
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
│   ├── start.sh / stop.sh   # root コレクタの起動・停止
│   ├── clean.sh / remove.sh # 無効化・削除
│   ├── bin/
│   │   └── collector.sh     # root で smartctl を実行し data/ に保存
│   └── web/
│       ├── index.php        # アプリ本体(単一ファイル)
│       ├── desc.xml         # 説明文(日英)
│       └── smartinfo.png    # アイコン
├── install.sh               # 単体 PHP 用インストーラ
├── LICENSE
└── README.md
```

`app/apkg.rc` の `CenterType: 1` により、ダッシュボード埋め込み型アプリ
(`/var/www/apps/<pkg>/`)として表示されます。

### SMART 情報の流れ

```
start.sh (root)
  └─ bin/collector.sh (root, 常駐)
       ├─ smartctl -a -j /dev/sda → data/sda.json
       └─ 10 分ごとに自動更新 / data/.refresh で即時更新
web/index.php (www-data)
  └─ data/*.json (または *.txt) を読んで表示
```

`data/.refresh` は画面の **再取得** ボタンで作成され、コレクタが数秒以内に拾って再収集します。
コレクタが動いていない環境では、PHP が `smartctl` を直接実行するフォールバックも動作します。

## 注意事項

- **ファームウェア更新で消える可能性があります。** その場合は `.bin` を再インストールしてください。
- NAS がインターネットへ公開されている環境では第三者もアクセスできます。**同一 LAN 内での使用を推奨**します。
- 追加ディスクを監視したい場合は `app/bin/collector.sh` の `for dev in ...` に
  `/dev/sdb` などを追加してください(単体 PHP の場合は `app/web/index.php` 冒頭の `$DISKS`)。

## トラブルシューティング

| 症状 | 対処 |
|---|---|
| 「アプリをインストールできませんでした。」 | ① モデル名と `.bin` が一致しているか確認(`grep modelName /etc/system.conf` / `WDMyCloud` か `WDCloud`)。② OS が一致しているか確認(`cat /etc/version` が 5.x なら `_OS5.bin`)。③ `tail -50 /tmp/debug_apkg` を確認。 |
| アプリ一覧に出ない | `ls -l /var/www/apps/smartinfo` と `ls -l /mnt/HD/HD_a2/Nas_Prog/smartinfo` を確認。有効化されているか確認。 |
| 「smartctl が見つかりません」 | SSH で `smartctl -a /dev/sda` が動くか確認。必要なら `apt-get install -y smartmontools`。 |
| 属性テーブルが空 / UNKNOWN のまま | コレクタが動いているか確認: `cat /var/run/smartinfo.pid`、`ls -l /mnt/HD/HD_a2/Nas_Prog/smartinfo/data`、`tail -20 /mnt/HD/HD_a2/Nas_Prog/smartinfo/data/collector.log`。 |
| 再取得しても変わらない | コレクタ停止が原因の可能性。アプリを無効化 → 有効化するか、SSH で `sh /mnt/HD/HD_a2/Nas_Prog/smartinfo/start.sh /mnt/HD/HD_a2/Nas_Prog/smartinfo`。 |

インストール失敗時の診断情報:

```sh
cat /etc/version
grep modelName /etc/system.conf
uname -a
cat /tmp/upload_apkg_status
tail -80 /tmp/debug_apkg
ls -l /mnt/HD/HD_a2/Nas_Prog
ls -l /var/www/apps
```

## ライセンス

[MIT](LICENSE)
