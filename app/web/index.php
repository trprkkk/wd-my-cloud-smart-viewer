<?php
/**
 * WD My Cloud SMART Viewer
 *
 * - OS5 アプリ(CenterType: 1)としてダッシュボードに埋め込むと、ページ断片として描画
 * - 直接アクセス(http://nas/apps/smartinfo/)した場合は完全な HTML ページとして描画
 *
 * SMART 情報の取得方法は 2 通り:
 *  1. root で動く bin/collector.sh が data/ に書き出した JSON / テキストを読む(通常)
 *  2. それが無い場合は smartctl を直接実行する(SSH 単体利用時など)
 *
 * PHP 5.3+ 互換(smartctl 7.x の JSON 出力を使用、非対応時はテキスト解析)
 */

// ---------- 設定 ----------
$DISKS     = array('/dev/sda', '/dev/sdb'); // 直接実行時のフォールバック
$CACHE_TTL = 30;                            // 直接実行時のキャッシュ秒数
$TITLE     = 'SMART Viewer';
// --------------------------

$DATA_DIR = dirname(__FILE__) . '/../data';

function si_find_smartctl($dataDir) {
    $recorded = @trim(@file_get_contents($dataDir . '/smartctl.path'));
    if ($recorded !== '' && @is_executable($recorded)) return $recorded;

    $candidates = array(
        '/usr/sbin/smartctl', '/usr/bin/smartctl', '/usr/local/sbin/smartctl',
        '/usr/local/bin/smartctl', '/sbin/smartctl', '/bin/smartctl',
        '/opt/sbin/smartctl', '/opt/bin/smartctl',
    );
    foreach ($candidates as $p) {
        if (@is_executable($p)) return $p;
    }
    if (function_exists('shell_exec')) {
        $which = @shell_exec('command -v smartctl 2>/dev/null');
        if ($which && trim($which) !== '') return trim($which);
    }
    return null;
}

/** root 以外でも動くよう sudo フォールバック付きで実行 */
function si_exec($cmd) {
    // OS5 の PHP では実行系関数が無効化されている場合がある
    if (!function_exists('shell_exec')) return null;

    $out = @shell_exec($cmd . ' 2>&1');
    if ($out === null || $out === false
        || stripos($out, 'Permission denied') !== false
        || stripos($out, 'are you root') !== false) {
        $sudo = @shell_exec('sudo -n ' . $cmd . ' 2>&1');
        if ($sudo !== null && $sudo !== false && trim($sudo) !== ''
            && stripos($sudo, 'Permission denied') === false
            && stripos($sudo, 'a password is required') === false) {
            return $sudo;
        }
    }
    return $out;
}

/** smartctl -a -j の JSON 出力を取得(非対応環境では null) */
function si_collect_json($smartctl, $disk) {
    $out = si_exec(escapeshellarg($smartctl) . ' -a -j ' . escapeshellarg($disk));
    if (!$out) return null;
    $json = json_decode($out, true);
    if (!is_array($json) || !isset($json['device'])) return null;
    return $json;
}

/** テキスト出力から最低限の情報を抽出(JSON 非対応の smartctl 用) */
function si_parse_text($text) {
    if (!$text) return null;

    $data = array(
        'smart_status' => array('passed' => null),
        'ata_smart_attributes' => array('table' => array()),
        'info' => array(),
        'raw' => $text,
    );
    if (preg_match('/overall-health.*?:\s*(PASSED|FAILED)/i', $text, $m)) {
        $data['smart_status']['passed'] = (strtoupper($m[1]) === 'PASSED');
    }
    foreach (preg_split('/\r?\n/', $text) as $line) {
        if (preg_match('/^\s*(\d+)\s+(\S+)\s+0x[0-9a-fA-F]+\s+(\d+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(.*?)\s*$/', $line, $m)) {
            $data['ata_smart_attributes']['table'][] = array(
                'id' => (int)$m[1],
                'name' => $m[2],
                'value' => (int)$m[3],
                'worst' => (int)$m[4],
                'thresh' => (int)$m[5],
                'when_failed' => ($m[8] === '-' ? '' : $m[8]),
                'raw' => array('string' => trim($m[9]), 'value' => trim($m[9])),
            );
        }
        $fields = array('Device Model' => 'model_name', 'Serial Number' => 'serial_number',
                        'Firmware Version' => 'firmware_version', 'Rotation Rate' => 'rotation_rate');
        foreach ($fields as $k => $field) {
            if (stripos($line, $k) !== false && preg_match('/:\s*(.+)/', $line, $m)) {
                $data['info'][$field] = trim($m[1]);
            }
        }
        if (preg_match('/User Capacity:.*\[(.+?)\]/', $line, $m)) {
            $data['info']['capacity'] = $m[1];
        }
        if (preg_match('/Power_On_Hours.*?(\d+)\s*$/', $line, $m)) {
            $data['info']['power_on_hours'] = (int)$m[1];
        }
        if (preg_match('/Temperature_Celsius.*?(\d+)\s*$/', $line, $m)) {
            $data['info']['temperature'] = (int)$m[1];
        }
    }
    return $data;
}

function si_collect_text($smartctl, $disk) {
    return si_parse_text(si_exec(escapeshellarg($smartctl) . ' -a ' . escapeshellarg($disk)));
}

/** ディスク情報を直接取得(キャッシュ付き) */
function si_get_disk($smartctl, $disk, $ttl, $force) {
    $cacheFile = sys_get_temp_dir() . '/smartinfo_' . md5($disk) . '.json';
    if (!$force && is_readable($cacheFile)) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if (is_array($cached) && isset($cached['ts']) && (time() - $cached['ts']) < $ttl) {
            return $cached['data'];
        }
    }
    $data = si_collect_json($smartctl, $disk);
    if ($data === null) {
        $data = si_collect_text($smartctl, $disk);
    }
    if ($data !== null) {
        @file_put_contents($cacheFile, json_encode(array('ts' => time(), 'data' => $data)));
    }
    return $data;
}

/** コレクタが書き出した data/*.json, data/*.txt を読む */
function si_load_collected($dir) {
    $out = array();
    if (!is_dir($dir)) return $out;
    $dh = @opendir($dir);
    if (!$dh) return $out;
    while (($f = readdir($dh)) !== false) {
        if (substr($f, 0, 1) === '.') continue;
        if (!preg_match('/^(sd[a-z]+|nvme\d+n\d+)\.(json|txt)$/', $f, $m)) continue;
        $dev = '/dev/' . $m[1];
        $path = $dir . '/' . $f;
        $out[$dev]['mtime'] = @filemtime($path);
        if ($m[2] === 'json') {
            $j = json_decode(@file_get_contents($path), true);
            if (is_array($j)) {
                $out[$dev]['data'] = $j;
                $out[$dev]['type'] = 'json';
            }
        } else {
            $t = @file_get_contents($path);
            if ($t !== false && $t !== '') {
                $out[$dev]['text'] = $t;
                $out[$dev]['type'] = 'text';
            }
        }
    }
    closedir($dh);
    return $out;
}

/** 画面表示用の値に整える */
function si_normalize($d) {
    if (!is_array($d)) return null;
    $status = null;
    if (isset($d['smart_status']['passed'])) {
        $status = $d['smart_status']['passed'];
    }

    $info = array();
    if (isset($d['model_name']))       $info['モデル'] = $d['model_name'];
    if (isset($d['model_family']))     $info['ファミリー'] = $d['model_family'];
    if (isset($d['serial_number']))    $info['シリアル番号'] = $d['serial_number'];
    if (isset($d['firmware_version'])) $info['ファームウェア'] = $d['firmware_version'];
    if (isset($d['user_capacity']['bytes'])) {
        $info['容量'] = number_format($d['user_capacity']['bytes'] / 1000000000, 1) . ' GB';
    }
    if (!empty($d['rotation_rate']))   $info['回転数'] = $d['rotation_rate'] . ' rpm';
    if (isset($d['temperature']['current'])) $info['温度'] = $d['temperature']['current'] . ' °C';
    if (isset($d['power_on_time']['hours']))  $info['通電時間'] = number_format($d['power_on_time']['hours']) . ' 時間';

    // テキストパース時の info
    if (isset($d['info']) && is_array($d['info'])) {
        $map = array('model_name' => 'モデル', 'serial_number' => 'シリアル番号',
                     'firmware_version' => 'ファームウェア', 'capacity' => '容量',
                     'rotation_rate' => '回転数', 'power_on_hours' => '通電時間',
                     'temperature' => '温度');
        foreach ($d['info'] as $k => $v) {
            $label = isset($map[$k]) ? $map[$k] : $k;
            if ($k === 'power_on_hours') $v = number_format((int)$v) . ' 時間';
            if ($k === 'temperature')    $v = $v . ' °C';
            if (!isset($info[$label])) $info[$label] = $v;
        }
    }

    $attrs = array();
    if (isset($d['ata_smart_attributes']['table'])) {
        foreach ($d['ata_smart_attributes']['table'] as $a) {
            $failed = isset($a['when_failed']) ? $a['when_failed'] : '';
            if (isset($a['raw']['string'])) {
                $raw = $a['raw']['string'];
            } elseif (isset($a['raw']['value'])) {
                $raw = (string)$a['raw']['value'];
            } else {
                $raw = '';
            }
            $attrs[] = array(
                'id'     => isset($a['id']) ? $a['id'] : '',
                'name'   => isset($a['name']) ? $a['name'] : '',
                'value'  => isset($a['value']) ? $a['value'] : '',
                'worst'  => isset($a['worst']) ? $a['worst'] : '',
                'thresh' => isset($a['thresh']) ? $a['thresh'] : '',
                'failed' => $failed,
                'raw'    => $raw,
            );
        }
    }

    $rawText = isset($d['raw']) ? $d['raw'] : json_encode($d);
    return array('status' => $status, 'info' => $info, 'attrs' => $attrs, 'raw' => $rawText);
}

/** 代表的な属性の日本語名 */
$SI_ATTR_NAMES = array(
    1   => '読み取りエラー率',
    3   => 'スピンアップ時間',
    4   => '起動/停止回数',
    5   => '代替済みセクタ数',
    7   => 'シークエラー率',
    9   => '通電時間',
    10  => 'スピン再試行回数',
    12  => '通電回数',
    190 => 'エアフロー温度',
    192 => '電源OFF時退避回数',
    193 => 'ロード/アンロード回数',
    194 => '温度',
    196 => '代替処理イベント数',
    197 => '保留セクタ数',
    198 => 'オフライン修正不可セクタ数',
    199 => 'UltraDMA CRC エラー',
    200 => 'マルチゾーンエラー率',
);

$smartctl = si_find_smartctl($DATA_DIR);
$embedded = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/apps/') !== false);
$force    = isset($_GET['refresh']);

if ($force && is_dir($DATA_DIR)) {
    @touch($DATA_DIR . '/.refresh');
}

$collected = si_load_collected($DATA_DIR);
$updated   = @filemtime($DATA_DIR . '/updated');
$results   = array();
$source    = 'live';

if (count($collected) > 0) {
    $source = 'collector';
    foreach ($collected as $dev => $c) {
        if (isset($c['data'])) {
            $results[$dev] = $c['data'];
        } elseif (isset($c['text'])) {
            $results[$dev] = si_parse_text($c['text']);
        }
    }
} else {
    foreach ($DISKS as $disk) {
        if (!file_exists($disk)) continue;
        $results[$disk] = $smartctl ? si_get_disk($smartctl, $disk, $CACHE_TTL, $force) : null;
    }
}

$css = '.si-app { font-family: "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif; color:#222; line-height:1.6; }'
     . '.si-app h1 { font-size:20px; margin:0 0 16px; display:flex; align-items:center; gap:8px; }'
     . '.si-app .si-card { background:#fff; border:1px solid #e2e6ea; border-radius:10px; padding:18px; margin-bottom:16px; }'
     . '.si-app .si-card h2 { font-size:15px; margin:0 0 12px; display:flex; align-items:center; gap:10px; }'
     . '.si-app .si-badge { display:inline-block; padding:3px 12px; border-radius:999px; font-size:12px; font-weight:bold; }'
     . '.si-app .si-ok { background:#d9f2e0; color:#157347; }'
     . '.si-app .si-warn { background:#fff3cd; color:#8a6d00; }'
     . '.si-app .si-bad { background:#f8d7da; color:#a12622; }'
     . '.si-app table { border-collapse:collapse; width:100%; font-size:12px; }'
     . '.si-app th, .si-app td { border:1px solid #e2e6ea; padding:5px 7px; text-align:left; white-space:nowrap; }'
     . '.si-app th { background:#f6f8fa; }'
     . '.si-app tr:hover td { background:#f0f6ff; }'
     . '.si-app .si-fail { color:#c0392b; font-weight:bold; }'
     . '.si-app .si-meta { font-size:13px; }'
     . '.si-app .si-meta div { display:flex; gap:8px; }'
     . '.si-app .si-meta b { min-width:110px; color:#555; font-weight:600; }'
     . '.si-app .si-err { color:#c0392b; }'
     . '.si-app .si-note { color:#555; font-size:12px; background:#f6f8fa; border-left:3px solid #2d6cdf; padding:8px 12px; margin:8px 0 14px; }'
     . '.si-app .si-muted { color:#888; font-size:12px; margin-top:12px; }'
     . '.si-app details { margin-top:12px; }'
     . '.si-app summary { cursor:pointer; font-size:12px; color:#2d6cdf; }'
     . '.si-app pre { background:#f6f8fa; padding:12px; border-radius:6px; font-size:11px; overflow-x:auto; }'
     . '.si-app .si-btn { display:inline-block; padding:5px 14px; background:#2d6cdf; color:#fff; border:0; border-radius:6px; font-size:12px; cursor:pointer; text-decoration:none; }';

if (!$embedded) {
    echo "<!DOCTYPE html>\n<html lang=\"ja\">\n<head>\n<meta charset=\"utf-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo "<title>" . htmlspecialchars($TITLE) . "</title>\n<style>\n";
    echo "body { background:#f2f4f7; margin:0; padding:24px; }\n.si-wrap { max-width:940px; margin:0 auto; }\n";
    echo "</style>\n</head>\n<body><div class=\"si-wrap\">\n";
}
echo "<style>\n" . $css . "\n</style>\n";
echo "<div class=\"si-app\">\n";
echo "<h1>&#128190; " . htmlspecialchars($TITLE) . "</h1>\n";
?>
<?php if ($smartctl === null && $source !== 'collector'): ?>
  <div class="si-card si-err">
    <b>smartctl が見つかりません。</b><br>
    SSH でログインし <code>apt-get update &amp;&amp; apt-get install -y smartmontools</code> を実行してください。<br>
    または <code>index.php</code> の <code>si_find_smartctl()</code> にパスを追加してください。
  </div>
<?php endif; ?>

<?php if (count($results) === 0): ?>
  <div class="si-card si-err">
    ディスクが見つかりません。<br>
    <span style="font-size:12px">アプリを有効化してから数十秒待つと、root コレクタが情報を収集します。それでも表示されない場合は SSH で
    <code>smartctl -a /dev/sda</code> が実行できるか確認してください。</span>
  </div>
<?php endif; ?>

<?php foreach ($results as $disk => $raw): $d = si_normalize($raw); ?>
  <div class="si-card">
    <h2>
      <?php echo htmlspecialchars($disk); ?>
      <?php if ($d && $d['status'] === true): ?>
        <span class="si-badge si-ok">PASSED</span>
      <?php elseif ($d && $d['status'] === false): ?>
        <span class="si-badge si-bad">FAILED</span>
      <?php else: ?>
        <span class="si-badge si-warn">UNKNOWN</span>
      <?php endif; ?>
      <a class="si-btn" style="margin-left:auto" href="?refresh=1">再取得</a>
    </h2>

    <?php if (!$d): ?>
      <p class="si-err">SMART 情報を取得できませんでした。smartctl の実行権限を確認してください。</p>
    <?php else: ?>
      <?php if ($source === 'collector'): ?>
        <div class="si-note">root コレクタが取得したデータを表示しています<?php if ($updated): ?>（<?php echo htmlspecialchars(date('Y-m-d H:i:s', $updated)); ?> 更新）<?php endif; ?>。「再取得」を押すと最大 5 秒後に更新されます。</div>
      <?php endif; ?>
      <div class="si-meta">
        <?php foreach ($d['info'] as $k => $v): ?>
          <div><b><?php echo htmlspecialchars($k); ?></b><span><?php echo htmlspecialchars((string)$v); ?></span></div>
        <?php endforeach; ?>
      </div>
      <br>
      <?php if (count($d['attrs']) > 0): ?>
        <div style="overflow-x:auto">
        <table>
          <tr><th>ID</th><th>属性名</th><th>現在値</th><th>最悪値</th><th>閾値</th><th>失敗</th><th>RAW値</th></tr>
          <?php foreach ($d['attrs'] as $a): ?>
            <tr>
              <td><?php echo htmlspecialchars((string)$a['id']); ?></td>
              <td>
                <?php echo htmlspecialchars($a['name']); ?>
                <?php if (isset($SI_ATTR_NAMES[$a['id']])): ?>
                  <br><span style="color:#888;font-size:10px"><?php echo htmlspecialchars($SI_ATTR_NAMES[$a['id']]); ?></span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars((string)$a['value']); ?></td>
              <td><?php echo htmlspecialchars((string)$a['worst']); ?></td>
              <td><?php echo htmlspecialchars((string)$a['thresh']); ?></td>
              <td class="<?php echo $a['failed'] ? 'si-fail' : ''; ?>"><?php echo $a['failed'] ? htmlspecialchars($a['failed']) : '-'; ?></td>
              <td><?php echo htmlspecialchars((string)$a['raw']); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
        </div>
      <?php endif; ?>

      <details>
        <summary>smartctl の生の出力を見る</summary>
        <pre><?php echo htmlspecialchars($d['raw']); ?></pre>
      </details>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<p class="si-muted">
  取得時刻: <?php echo htmlspecialchars(date('Y-m-d H:i:s', $updated ? $updated : time())); ?> &middot; WD My Cloud SMART Viewer
</p>
</div>

<?php if (!$embedded): ?>
</div></body></html>
<?php endif; ?>
