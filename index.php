<?php
/**
 * My Cloud 1st Gen SMART Viewer (standalone app)
 * 配置先: /var/www/smartinfo/
 * アクセス: http://<NASのIP>/smartinfo/
 * 要件: smartmontools (smartctl) がインストールされていること
 */

// ---------- 設定 ----------
$DISKS = array('/dev/sda');            // 1st Gen は内蔵HDD 1基。必要なら追加
$SMARTCTL = '/usr/sbin/smartctl';      // 見つからない場合は自動探索
$TITLE = 'My Cloud SMART Viewer';
// --------------------------

// smartctl のパスを自動探索
if (!file_exists($SMARTCTL)) {
    foreach (array('/usr/sbin/smartctl', '/usr/bin/smartctl', '/sbin/smartctl', '/opt/sbin/smartctl', '/opt/bin/smartctl') as $p) {
        if (file_exists($p)) { $SMARTCTL = $p; break; }
    }
}

function sh($cmd) {
    $out = @shell_exec($cmd . ' 2>&1');
    // Web サーバーは www-data 権限のため sudo 経由で再試行
    $needsSudo = ($out === null || $out === false ||
                  stripos($out, 'Permission denied') !== false ||
                  stripos($out, 'are you root') !== false);
    if ($needsSudo) {
        $sudoOut = @shell_exec('sudo ' . $cmd . ' 2>&1');
        if ($sudoOut !== null && $sudoOut !== false && strlen(trim($sudoOut)) > 0
            && stripos($sudoOut, 'Permission denied') === false) {
            return $sudoOut;
        }
    }
    return $out;
}

/** smartctl -H の結果から健康状態を判定 */
function parseHealth($text) {
    if (preg_match('/overall-health.*?:\s*(.+)/i', $text, $m)) {
        return trim($m[1]);
    }
    return 'UNKNOWN';
}

/** smartctl -A をパースして属性配列へ */
function parseAttributes($text) {
    $attrs = array();
    foreach (preg_split('/\r?\n/', $text) as $line) {
        // 例: 5 Reallocated_Sector_Ct   0x0033   200   200   140    Pre-fail  Always       -       0
        if (preg_match('/^\s*(\d+)\s+(\S+)\s+(0x[0-9a-fA-F]+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.*?)\s*$/i', $line, $m)) {
            $attrs[] = array(
                'id'      => $m[1],
                'name'    => $m[2],
                'flag'    => $m[3],
                'value'   => $m[4],
                'worst'   => $m[5],
                'thresh'  => $m[6],
                'type'    => $m[7],
                'updated' => $m[8],
                'when_failed' => $m[9],
                'raw'     => trim($m[10]),
            );
        }
    }
    return $attrs;
}

/** モデル名・シリアル等のデバイス情報を抽出 */
function parseInfo($text) {
    $info = array();
    $keys = array(
        'Device Model' => 'モデル',
        'Model Family' => 'ファミリー',
        'Serial Number' => 'シリアル番号',
        'Firmware Version' => 'ファームウェア',
        'User Capacity' => '容量',
        'Rotation Rate' => '回転数',
        'Power_On_Hours' => '',
    );
    foreach (preg_split('/\r?\n/', $text) as $line) {
        foreach ($keys as $k => $label) {
            if ($label !== '' && stripos($line, $k) !== false && preg_match('/:\s*(.+)/', $line, $m)) {
                $info[$label] = trim($m[1]);
            }
        }
        // 稼働時間は属性側にもあるので here は info テキストのみ
        if (preg_match('/POWER ON HOURS/i', $line) === false && preg_match('/^  9\s+Power_On_Hours/i', $line, $m)) {
            // no-op (属性テーブルで扱う)
        }
    }
    return $info;
}

function healthClass($h) {
    if (stripos($h, 'PASSED') !== false) return 'ok';
    if (stripos($h, 'FAILED') !== false) return 'bad';
    return 'warn';
}

/** 代表的な属性の日本語名 */
$ATTR_NAMES = array(
    '1'  => '読み取りエラー率',
    '5'  => '代替済みセクタ数',
    '9'  => '通電時間',
    '12' => '通電回数',
    '190'=> 'エアフロー温度',
    '194'=> '温度',
    '196'=> '再割当試行数',
    '197'=> '保留セクタ数',
    '198'=> '修正不可能セクタ数',
    '199'=> 'UltraDMA CRCエラー',
);

$page = isset($_GET['d']) ? $_GET['d'] : (isset($DISKS[0]) ? $DISKS[0] : '');
$diskData = array();
foreach ($DISKS as $d) {
    if (!file_exists($d)) continue;
    $healthText = sh(escapeshellarg($SMARTCTL) . ' -H ' . escapeshellarg($d));
    $attrText   = sh(escapeshellarg($SMARTCTL) . ' -A ' . escapeshellarg($d));
    $infoText   = sh(escapeshellarg($SMARTCTL) . ' -i ' . escapeshellarg($d));
    $diskData[$d] = array(
        'health'    => parseHealth($healthText),
        'attrs'     => parseAttributes($attrText),
        'info'      => parseInfo($infoText),
        'raw_attr'  => $attrText,
        'exists'    => true,
    );
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo htmlspecialchars($TITLE); ?></title>
<style>
  :root { color-scheme: light; }
  * { box-sizing: border-box; }
  body { font-family: "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif; background:#f2f4f7; color:#222; margin:0; padding:24px; }
  h1 { font-size:20px; display:flex; align-items:center; gap:8px; }
  .card { background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:20px; margin-bottom:20px; max-width:900px; }
  .badge { display:inline-block; padding:4px 12px; border-radius:999px; font-size:13px; font-weight:bold; }
  .ok   { background:#d9f2e0; color:#157347; }
  .warn { background:#fff3cd; color:#8a6d00; }
  .bad  { background:#f8d7da; color:#a12622; }
  table { border-collapse:collapse; width:100%; font-size:13px; }
  th, td { border:1px solid #e2e6ea; padding:6px 8px; text-align:left; white-space:nowrap; }
  th { background:#f6f8fa; }
  tr:hover td { background:#f0f6ff; }
  .fail { color:#c0392b; font-weight:bold; }
  .disktabs { margin-bottom:16px; }
  .disktabs a { display:inline-block; padding:6px 14px; margin-right:6px; background:#fff; border:1px solid #d5dae0; border-radius:6px; text-decoration:none; color:#333; font-size:13px; }
  .disktabs a.active { background:#2d6cdf; color:#fff; border-color:#2d6cdf; }
  .meta { font-size:13px; color:#555; line-height:1.8; }
  .meta b { color:#222; display:inline-block; min-width:110px; }
  .updated { color:#888; font-size:12px; max-width:900px; }
  details summary { cursor:pointer; font-size:13px; color:#2d6cdf; }
  pre { background:#f6f8fa; padding:12px; border-radius:6px; font-size:12px; overflow-x:auto; }
  .err { color:#c0392b; }
</style>
</head>
<body>
<h1>&#128190; <?php echo htmlspecialchars($TITLE); ?></h1>

<?php if (!file_exists($SMARTCTL)): ?>
  <div class="card err">
    <b>smartctl が見つかりません。</b><br>
    SSH でログインして <code>apt-get update && apt-get install -y smartmontools</code> を実行してください。<br>
    または本ファイル冒頭の <code>$SMARTCTL</code> パスを修正してください。
  </div>
<?php endif; ?>

<?php if (count($diskData) === 0): ?>
  <div class="card err">検出できたディスクがありません。設定の <code>$DISKS</code> を確認してください。</div>
<?php endif; ?>

<?php foreach ($diskData as $d => $dd): ?>
  <div class="card">
    <h2 style="font-size:16px; margin-top:0;">
      <?php echo htmlspecialchars($d); ?>
      <span class="badge <?php echo healthClass($dd['health']); ?>"><?php echo htmlspecialchars($dd['health']); ?></span>
    </h2>
    <div class="meta">
      <?php foreach ($dd['info'] as $k => $v): ?>
        <div><b><?php echo htmlspecialchars($k); ?>:</b> <?php echo htmlspecialchars($v); ?></div>
      <?php endforeach; ?>
    </div>
    <br>
    <?php if (count($dd['attrs']) > 0): ?>
      <table>
        <tr>
          <th>ID</th><th>属性名</th><th>現在値</th><th>最悪値</th><th>閾値</th><th>種別</th><th>失敗</th><th>RAW値</th>
        </tr>
        <?php foreach ($dd['attrs'] as $a): ?>
          <tr>
            <td><?php echo $a['id']; ?></td>
            <td><?php echo htmlspecialchars($a['name']); ?><?php
              if (isset($ATTR_NAMES[$a['id']])) echo '<br><span style="color:#888;font-size:11px">' . htmlspecialchars($ATTR_NAMES[$a['id']]) . '</span>';
            ?></td>
            <td><?php echo $a['value']; ?></td>
            <td><?php echo $a['worst']; ?></td>
            <td><?php echo $a['thresh']; ?></td>
            <td><?php echo htmlspecialchars($a['type']); ?></td>
            <td class="<?php echo ($a['when_failed'] !== '-') ? 'fail' : ''; ?>"><?php echo htmlspecialchars($a['when_failed']); ?></td>
            <td><?php echo htmlspecialchars($a['raw']); ?></td>
          </tr>
        <?php endforeach; ?>
      </table>
    <?php else: ?>
      <p class="err">SMART 属性を取得できませんでした。smartctl の実行権限(Web サーバーが root で動作しているか)を確認してください。</p>
    <?php endif; ?>
    <br>
    <details>
      <summary>smartctl の生の出力を見る</summary>
      <pre><?php echo htmlspecialchars($dd['raw_attr']); ?></pre>
    </details>
  </div>
<?php endforeach; ?>

<p class="updated">取得時刻: <?php echo htmlspecialchars(date('Y-m-d H:i:s')); ?> / My Cloud 1st Gen &middot; SMART Viewer</p>
</body>
</html>
