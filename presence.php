<?php
// presence.php (v9) - daftar user online + status "sudah dibaca" untuk Obrolan v9
// Taruh di folder yang SAMA dengan api-semua.php, api-lantai1.php, dst. (mis. /obrolan/presence.php)
// Klien memanggil:  POST presence.php?room=<nama-file-api-tanpa-.php>
//   body JSON: {"cid":"...","name":"...","ttl":30,"read":1760000000}   -> heartbeat
//              {"cid":"...","leave":true}                              -> keluar dari room
//   balasan : {"users":[{"cid","name"},...], "reads":{"<cid>":<waktu-pesan-terakhir-yang-dibaca>}}
// Data disimpan di ./presence/<room>.json (otomatis dibuat; folder harus bisa ditulis web server).
// File data lama (v8) otomatis diabaikan dan dibuat ulang.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$room = preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['room'] ?? '');
if ($room === '') $room = 'default';

$dir = __DIR__ . '/presence';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$file = "$dir/$room.json";

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = [];
$cid   = substr(preg_replace('/[^A-Za-z0-9]/', '', (string)($in['cid'] ?? '')), 0, 32);
$name  = mb_substr(trim(strip_tags((string)($in['name'] ?? ''))), 0, 40);
$ttl   = max(10, min(300, (int)($in['ttl'] ?? 30)));
$read  = max(0, (int)($in['read'] ?? 0));
$leave = !empty($in['leave']);
$now   = time();

$fp = fopen($file, 'c+');
if (!$fp) { http_response_code(500); echo json_encode(['error' => 'tidak bisa menulis ' . $file]); exit; }
flock($fp, LOCK_EX);
$data = json_decode(stream_get_contents($fp), true);
if (!is_array($data) || !isset($data['users'])) $data = ['users' => [], 'reads' => []];
if (!is_array($data['users'])) $data['users'] = [];
if (!is_array($data['reads'])) $data['reads'] = [];

foreach ($data['users'] as $k => $v) {              // buang user yang sudah kedaluwarsa
    if (!isset($v['exp']) || $v['exp'] < $now) unset($data['users'][$k]);
}
foreach ($data['reads'] as $k => $v) {              // status baca disimpan 90 hari sejak update terakhir
    if (!isset($v['u']) || $v['u'] < $now - 90 * 86400) unset($data['reads'][$k]);
}

if ($cid !== '') {
    if ($leave) {
        unset($data['users'][$cid]);                // status baca TIDAK dihapus -> centang tetap 2
    } else {
        $data['users'][$cid] = ['name' => ($name !== '' ? $name : 'python'), 'exp' => $now + $ttl];
        if ($read > 0) {
            $old = (int)($data['reads'][$cid]['t'] ?? 0);
            $data['reads'][$cid] = ['t' => max($old, $read), 'u' => $now];
        }
    }
}

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($data, JSON_UNESCAPED_UNICODE));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

$users = [];
foreach ($data['users'] as $id => $v) $users[] = ['cid' => (string)$id, 'name' => $v['name']];
usort($users, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
$reads = new stdClass();
foreach ($data['reads'] as $id => $v) $reads->{(string)$id} = (int)$v['t'];
echo json_encode(['users' => $users, 'reads' => $reads], JSON_UNESCAPED_UNICODE);
