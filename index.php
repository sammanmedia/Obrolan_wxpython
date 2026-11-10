<?php
/*
 * index.php - OBROLAN versi BROWSER
 *
 * File ini TIDAK perlu diedit. Semua pengaturan (daftar chatroom, file API, background, dll.)
 * ada di  config.php  (satu folder dengan file ini).
 *
 * Taruh index.php + config.php di folder yang sama dengan file api-*.php dan presence.php,
 * lalu buka  http://alamat-server/folder-obrolan/
 * Kalau ada yang tidak nyambung, buka  index.php?cek=1  (halaman pemeriksaan).
 *
 * Kompatibel dengan aplikasi desktop (wxPython): reply, centang baca, dan daftar online saling terbaca.
 */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$WEB_VERSION = '9.5';
$base = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/')), '/') . '/';

$cfgFile = __DIR__ . '/config.php';
if (!is_file($cfgFile)) {
    http_response_code(500);
    echo '<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;padding:24px">'
       . '<h3>config.php tidak ditemukan</h3><p>Taruh file <b>config.php</b> di folder yang sama dengan index.php:<br><code>'
       . htmlspecialchars(__DIR__) . '</code></p></body>';
    exit;
}
$CFG = require $cfgFile;
if (!is_array($CFG)) $CFG = [];

/** Nama file / path / URL penuh dari config -> alamat yang dipakai browser (path absolut, satu origin). */
function web_path($u, $base) {
    $u = trim((string)$u);
    if ($u === '') return '';
    if (preg_match('~^https?://~i', $u)) {            // URL penuh -> pakai path-nya saja
        $p = parse_url($u, PHP_URL_PATH);
        return $p ? $p : $u;
    }
    if ($u[0] === '/') return $u;                      // sudah path absolut
    return $base . str_replace('\\', '/', $u);         // nama file biasa: di folder index.php
}

$title = isset($CFG['judul']) && $CFG['judul'] !== '' ? (string)$CFG['judul'] : 'Obrolan';
$poll  = max(2000, min(60000, (int)(isset($CFG['poll_ms']) ? $CFG['poll_ms'] : 10000)));
$rooms = [];
$rawRooms = isset($CFG['rooms']) && is_array($CFG['rooms']) ? $CFG['rooms'] : [];
foreach ($rawRooms as $r) {
    if (!is_array($r) || empty($r['chatroom']) || empty($r['api'])) continue;
    $api  = web_path($r['api'], $base);
    $path = parse_url((string)$r['api'], PHP_URL_PATH);
    $key  = !empty($r['key']) ? (string)$r['key'] : pathinfo($path ? $path : (string)$r['api'], PATHINFO_FILENAME);
    $key  = preg_replace('/[^A-Za-z0-9_\-]/', '', $key);
    if ($key === '') $key = 'default';
    if (!empty($r['presence'])) {
        $pres = web_path($r['presence'], $base);
    } else {
        $d = rtrim(str_replace('\\', '/', dirname($api)), '/');
        $pres = ($d === '' || $d === '.') ? $base . 'presence.php' : $d . '/presence.php';
    }
    $bg = str_replace('\\', '/', trim((string)(isset($r['background']) ? $r['background'] : '')));
    if (preg_match('~^[A-Za-z]:/~', $bg)) $bg = '';    // path Windows (C:/...) tidak bisa dipakai browser
    if ($bg !== '') $bg = web_path($bg, $base);
    $rooms[] = [
        'name'     => (string)$r['chatroom'],
        'api'      => $api,
        'presence' => $pres,
        'key'      => $key,
        'bg'       => $bg,
        'bg_mode'  => (string)(isset($r['bg_mode']) ? $r['bg_mode'] : 'cover'),
        'bg_color' => (string)(isset($r['bg_warna']) ? $r['bg_warna'] : ''),
    ];
}

// ---------- halaman pemeriksaan:  index.php?cek=1 ----------
if (isset($_GET['cek']) && (!isset($CFG['halaman_cek']) || $CFG['halaman_cek'])) {
    $h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); };
    $onDisk = function ($webPath) use ($base) {       // path web -> file di server (kalau satu folder/di bawahnya)
        if (strpos($webPath, $base) !== 0) return null;
        $rel = substr($webPath, strlen($base));
        if ($rel === '' || strpos($rel, '..') !== false) return null;
        return __DIR__ . '/' . $rel;
    };
    $mark = function ($ok) { return $ok === null ? '<td>?</td>' : ($ok ? '<td style="color:#2a8a3c">ADA</td>' : '<td style="color:#c33;font-weight:bold">TIDAK ADA</td>'); };
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><title>Cek Obrolan</title><body style="font-family:system-ui,sans-serif;padding:20px;max-width:900px">';
    echo '<h2>Pemeriksaan Obrolan (web)</h2><ul>';
    echo '<li>PHP versi <b>' . $h(PHP_VERSION) . '</b></li>';
    echo '<li>Ekstensi mbstring (dipakai api.php &amp; presence.php): ' . (function_exists('mb_substr') ? '<b style="color:#2a8a3c">aktif</b>' : '<b style="color:#c33">TIDAK AKTIF - api.php akan error</b>') . '</li>';
    echo '<li>Folder aplikasi: <code>' . $h(__DIR__) . '</code> (alamat web: <code>' . $h($base) . '</code>)</li>';
    $pw = is_dir(__DIR__ . '/presence') ? is_writable(__DIR__ . '/presence') : is_writable(__DIR__);
    echo '<li>Folder untuk data online/centang (presence/) bisa ditulis: ' . ($pw ? '<b style="color:#2a8a3c">ya</b>' : '<b style="color:#c33">TIDAK</b>') . '</li>';
    echo '<li>Poll: ' . (int)$poll . ' ms</li></ul>';
    echo '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px"><tr style="background:#eee"><th>Chatroom</th><th>File API</th><th>Ada?</th><th>presence.php</th><th>Ada?</th><th>Background</th><th>Ada?</th></tr>';
    foreach ($rooms as $r) {
        $fa = $onDisk($r['api']); $fp = $onDisk($r['presence']); $fb = $r['bg'] !== '' ? $onDisk($r['bg']) : null;
        echo '<tr><td>' . $h($r['name']) . '</td><td><a href="' . $h($r['api']) . '" target="_blank">' . $h($r['api']) . '</a></td>'
           . $mark($fa === null ? null : is_file($fa))
           . '<td>' . $h($r['presence']) . '</td>' . $mark($fp === null ? null : is_file($fp))
           . '<td>' . ($r['bg'] === '' ? '-' : $h($r['bg'])) . '</td>' . ($r['bg'] === '' ? '<td>-</td>' : $mark($fb === null ? null : is_file($fb))) . '</tr>';
    }
    echo '</table>';
    if (!$rooms) echo '<p style="color:#c33"><b>Tidak ada chatroom di config.php</b> (bagian <code>rooms</code>).</p>';
    echo '<p>Klik nama file API di tabel: harus muncul teks JSON berawalan <code>[</code>. Kalau muncul 404 berarti nama file di <code>config.php</code> belum sama dengan file di server.</p>';
    echo '<p><a href="' . $h($base) . '">&larr; kembali ke Obrolan</a></p></body>';
    exit;
}

$conf = ['rooms' => $rooms, 'poll' => $poll, 'version' => $WEB_VERSION, 'title' => $title];
$confJson = json_encode($conf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1877f2">
<title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<style>
:root{
  --blue:#1877f2; --bg:#eceff3; --border:#dde1e6; --text:#1e2126; --muted:#6e7682;
  --other:#fff; --green:#2f9e44; --red:#d64545;
}
*{box-sizing:border-box}
html,body{height:100%;margin:0}
body{background:#cfd5dd;font:15px/1.35 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:var(--text);
  font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,"Segoe UI Emoji","Apple Color Emoji","Noto Color Emoji",sans-serif}
#app{height:100vh;height:100dvh;max-width:720px;margin:0 auto;background:var(--bg);display:flex;flex-direction:column;position:relative;
  padding-top:env(safe-area-inset-top);box-shadow:0 0 30px rgba(0,0,0,.15)}
#top{padding:8px 8px 6px;background:#fff;border-bottom:1px solid var(--border);display:flex;flex-direction:column;gap:6px}
#btnNotif{width:100%;border:0;border-radius:6px;padding:9px;color:#fff;font-weight:600;font-size:14px;cursor:pointer;background:var(--green)}
#btnNotif.off{background:var(--red)}
.row2{display:flex;gap:8px;align-items:center}
select,input.txt{width:100%;font:inherit;padding:8px 10px;border:1px solid var(--border);border-radius:6px;background:#fff;color:var(--text)}
select{color:var(--blue);font-weight:600}
.row2 label{font-size:13px;color:var(--muted);white-space:nowrap}
#chatwrap{flex:1;min-height:0;position:relative;background-color:var(--bg);background-size:cover;background-position:center;background-repeat:no-repeat}
#chat{position:absolute;inset:0;overflow-y:auto;overflow-x:hidden;padding:12px 12px 8px;-webkit-overflow-scrolling:touch;user-select:text}
.day{width:max-content;margin:10px auto 8px;padding:3px 12px;border-radius:12px;background:#d6dbe2;color:#505660;font-size:12px}
.msg{display:flex;align-items:center;gap:6px;margin-top:10px}
.msg.cont{margin-top:3px}
.msg.mine{justify-content:flex-end}
.bubble{max-width:78%;padding:7px 12px 5px;border-radius:14px;background:var(--other);border:1px solid var(--border);word-wrap:break-word;overflow-wrap:anywhere}
.mine .bubble{background:var(--blue);border-color:var(--blue);color:#fff}
.name{font-weight:700;font-size:13px;margin-bottom:2px}
.body{white-space:pre-wrap}
.meta{display:flex;justify-content:flex-end;align-items:center;gap:3px;font-size:11px;color:#8c929b;margin-top:2px}
.mine .meta{color:#cde2ff}
.tick{display:inline-flex;width:16px;height:10px}
.tick svg{width:16px;height:10px;fill:none;stroke:#cde2ff;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.tick .c1{display:none}
.tick.read svg{stroke:#78f0a5}
.tick.read .c1{display:inline}
.quote{border-radius:6px;padding:4px 8px;margin:2px 0 5px;border-left:4px solid var(--blue);background:rgba(24,119,242,.10);cursor:pointer;overflow:hidden}
.mine .quote{border-left-color:#fff;background:rgba(255,255,255,.22)}
.quote b{display:block;font-size:12px}
.quote span{display:block;font-size:13px;color:#5a6069;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mine .quote span{color:#e1ecff}
.rbtn{flex:none;width:28px;height:28px;border-radius:50%;border:1px solid var(--border);background:rgba(255,255,255,.92);cursor:pointer;
  display:flex;align-items:center;justify-content:center;padding:0;opacity:0;transition:opacity .12s}
.rbtn svg{width:16px;height:16px;fill:none;stroke:#5a6573;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.msg:hover .rbtn,.rbtn:focus{opacity:1}
@media (hover:none){.rbtn{opacity:.55}}
.msg.flash .bubble{outline:3px solid #ffaa00;outline-offset:1px}
#replybar{display:none;margin:0 8px;background:#e8f0fe;border-radius:8px 8px 0 0;border-left:4px solid var(--blue);padding:6px 8px;align-items:center;gap:8px}
#replybar.show{display:flex}
#replybar .t{flex:1;min-width:0}
#replybar b{display:block;color:var(--blue);font-size:13px}
#replybar span{display:block;color:#5a6069;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#replybar button{border:0;background:none;font-size:18px;cursor:pointer;color:#5a6069;padding:2px 6px}
#composer{position:relative;padding:8px;display:flex;gap:8px;align-items:flex-end}
.pill{flex:1;display:flex;align-items:flex-end;background:#fff;border:1px solid var(--border);border-radius:22px;padding:4px 6px 4px 14px;min-height:42px}
.pill:focus-within{border-color:var(--blue)}
.pill textarea{flex:1;border:0;outline:0;resize:none;font:inherit;background:transparent;max-height:120px;height:32px;padding:6px 0;color:var(--text)}
.ibtn{flex:none;width:34px;height:34px;border:0;background:none;border-radius:50%;cursor:pointer;font-size:22px;line-height:1;padding:0;display:flex;align-items:center;justify-content:center}
.ibtn:hover{background:#eef1f5}
#btnSend{flex:none;width:42px;height:42px;border:0;border-radius:50%;background:var(--blue);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}
#btnSend:hover{background:#0f63d0}
#btnSend svg{width:22px;height:22px;fill:#fff;margin-left:2px}
#emoji{display:none;position:absolute;left:8px;bottom:100%;margin-bottom:4px;width:min(340px,calc(100% - 16px));height:300px;background:#fff;border:1px solid var(--border);
  border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.18);flex-direction:column;overflow:hidden;z-index:20}
#emoji.show{display:flex}
#etabs{display:flex;border-bottom:1px solid var(--border)}
#etabs button{flex:1;border:0;background:none;font-size:19px;padding:7px 0;cursor:pointer;border-bottom:3px solid transparent}
#etabs button.on{border-bottom-color:var(--blue)}
#egrid{flex:1;overflow-y:auto;padding:6px;display:grid;grid-template-columns:repeat(8,1fr);align-content:start;gap:2px}
#egrid button{border:0;background:none;font-size:23px;line-height:1;padding:5px 0;border-radius:8px;cursor:pointer}
#egrid button:hover{background:#eef1f5}
#egrid .empty{grid-column:1/-1;color:var(--muted);text-align:center;padding:24px 8px;font-size:13px}
#foot{position:relative;padding:2px 12px calc(8px + env(safe-area-inset-bottom));font-size:12.5px;color:var(--muted)}
#status{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-height:18px}
#status.err{color:var(--red)}
#footrow{display:flex;justify-content:space-between;align-items:center}
#btnOnline{border:0;background:none;color:var(--blue);text-decoration:underline;cursor:pointer;font:inherit;padding:2px 0}
#online{display:none;position:absolute;right:8px;bottom:100%;margin-bottom:4px;width:260px;max-height:300px;overflow-y:auto;background:#fff;border:1px solid var(--border);
  border-radius:10px;box-shadow:0 6px 24px rgba(0,0,0,.18);z-index:20}
#online.show{display:block}
#online h4{margin:0;padding:8px 12px;color:var(--blue);font-size:13px;border-bottom:1px solid var(--border)}
#online .u{display:flex;gap:10px;padding:8px 12px;cursor:pointer;color:var(--text);font-size:14px}
#online .u:hover{background:#eef4ff}
#online .u i{font-style:normal;width:26px;color:var(--muted)}
#online .u.me{cursor:default;color:var(--muted)}
#online .u.me:hover{background:none}
#online .note{padding:12px;color:var(--muted);font-size:13px}
</style>
</head>
<body>
<div id="app">
  <div id="top">
    <button id="btnNotif" type="button">Notifikasi pesan masuk: ON</button>
    <select id="room" aria-label="Chatroom"></select>
    <div class="row2"><label for="nama">Nama:</label><input id="nama" class="txt" maxlength="30" placeholder="Nama kamu" autocomplete="off"></div>
  </div>

  <div id="chatwrap"><div id="chat" aria-live="polite"></div></div>

  <div id="replybar"><div class="t"><b id="rbName"></b><span id="rbText"></span></div><button id="rbClose" type="button" title="Batal (Esc)">&#x2715;</button></div>

  <div id="composer">
    <div id="emoji"><div id="etabs"></div><div id="egrid"></div></div>
    <div class="pill">
      <textarea id="msg" rows="1" placeholder="Ketik pesan..." autocomplete="off"></textarea>
      <button id="btnEmoji" class="ibtn" type="button" title="Emoji">&#x1F642;</button>
    </div>
    <button id="btnSend" type="button" title="Kirim" aria-label="Kirim">
      <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
    </button>
  </div>

  <div id="foot">
    <div id="online"></div>
    <div id="status">Memuat...</div>
    <div id="footrow"><button id="btnOnline" type="button">Chatroom Online : ...</button><span id="ver"></span></div>
  </div>
</div>

<script>
(() => {
'use strict';
const CONF = <?= $confJson ?>;
const EMOJI = [{"n":"Smiley & Orang","i":"😀","e":["😀","😃","😄","😁","😆","😅","🤣","😂","🙂","🙃","😉","😊","😇","🥰","😍","🤩","😘","😗","☺️","😚","😙","😋","😛","😜","🤪","😝","🤑","🤗","🤭","🤫","🤔","🤐","🤨","😐","😑","😶","😏","😒","🙄","😬","🤥","😌","😔","😪","🤤","😴","😷","🤒","🤕","🤢","🤮","🤧","🥵","🥶","🥴","😵","🤯","🤠","🥳","😎","🤓","🧐","😕","😟","🙁","☹️","😮","😯","😲","😳","🥺","😦","😧","😨","😰","😥","😢","😭","😱","😖","😣","😞","😓","😩","😫","😤","😡","😠","🤬","😈","👿","💀","☠️","💩","🤡","👹","👺","👻","👽","👾","🤖","😺","😸","😹","😻","😼","😽","🙀","😿","😾","🙈","🙉","🙊","💋","💌","💘","💝","💖","💗","💓","💞","💕","💟","❣️","💔","❤️","🧡","💛","💚","💙","💜","🖤","💯","💢","💥","💫","💦","💨","💤","👋","🤚","🖐️","✋","🖖","👌","✌️","🤞","🤟","🤘","🤙","👈","👉","👆","🖕","👇","☝️","👍","👎","✊","👊","🤛","🤜","👏","🙌","👐","🤲","🤝","🙏","✍️","💅","🤳","💪","🦵","🦶","👂","👃","🧠","🦷","🦴","👀","👁️","👅","👄","👶","🧒","👦","👧","🧑","👨","👩","🧓","👴","👵","🙍","🙎","🙅","🙆","💁","🙋","🙇","🤦","🤷","💆","💇","🚶","🏃","💃","🕺","👯","🧖","🗣️","👤","👥","👪","💑","💏","🧥","👔","👕","👖","👗","👘","👙","👚","👛","👜","👝","🎒","👞","👟","👠","👡","👢","👑","👒","🎩","🎓","🧢","💄","💍","💼"]},{"n":"Hewan & Alam","i":"🐻","e":["🐶","🐱","🐭","🐹","🐰","🦊","🐻","🐼","🐨","🐯","🦁","🐮","🐷","🐽","🐸","🐵","🐔","🐧","🐦","🐤","🐣","🐥","🦆","🦅","🦉","🦇","🐺","🐗","🐴","🦄","🐝","🐛","🦋","🐌","🐞","🐜","🦟","🦗","🕷️","🕸️","🦂","🐢","🐍","🦎","🦖","🦕","🐙","🦑","🦐","🦞","🦀","🐡","🐠","🐟","🐬","🐳","🐋","🦈","🐊","🐅","🐆","🦓","🦍","🐘","🦏","🦛","🐪","🐫","🦒","🦘","🐃","🐂","🐄","🐎","🐖","🐏","🐑","🦙","🐐","🦌","🐕","🐩","🐈","🐓","🦃","🦚","🦜","🦢","🕊️","🐇","🦝","🦡","🐁","🐀","🐿️","🦔","🐾","🐉","🐲","🌵","🎄","🌲","🌳","🌴","🌱","🌿","☘️","🍀","🎍","🎋","🍃","🍂","🍁","🍄","🌾","💐","🌷","🌹","🥀","🌺","🌸","🌼","🌻","🌞","🌝","🌛","🌜","🌚","🌕","🌖","🌗","🌘","🌑","🌒","🌓","🌔","🌙","🌎","🌍","🌏","⭐","🌟","✨","⚡","☄️","🔥","🌪️","🌈","☀️","🌤️","⛅","🌥️","☁️","🌦️","🌧️","⛈️","🌩️","🌨️","❄️","☃️","⛄","🌬️","💧","☔","☂️","🌊","🌫️"]},{"n":"Makanan & Minuman","i":"🍔","e":["🍏","🍎","🍐","🍊","🍋","🍌","🍉","🍇","🍓","🍈","🍒","🍑","🥭","🍍","🥥","🥝","🍅","🍆","🥑","🥦","🥬","🥒","🌶️","🌽","🥕","🥔","🍠","🥐","🥯","🍞","🥖","🥨","🧀","🥚","🍳","🥞","🥓","🥩","🍗","🍖","🌭","🍔","🍟","🍕","🥪","🥙","🌮","🌯","🥗","🥘","🥫","🍝","🍜","🍲","🍛","🍣","🍱","🥟","🍤","🍙","🍚","🍘","🍥","🥠","🥮","🍢","🍡","🍧","🍨","🍦","🥧","🧁","🍰","🎂","🍮","🍭","🍬","🍫","🍿","🍩","🍪","🌰","🥜","🍯","🥛","🍼","☕","🍵","🥤","🍶","🍺","🍻","🥂","🍷","🥃","🍸","🍹","🍾","🥄","🍴","🍽️","🥣","🥡","🥢","🧂"]},{"n":"Aktivitas","i":"⚽","e":["⚽","🏀","🏈","⚾","🥎","🎾","🏐","🏉","🥏","🎱","🏓","🏸","🏒","🏑","🥍","🏏","🥅","⛳","🏹","🎣","🥊","🥋","🎽","🛹","🛷","⛸️","🥌","🎿","⛷️","🏂","🏋️","🤼","🤸","⛹️","🤺","🤾","🏌️","🏇","🧘","🏄","🏊","🤽","🚣","🧗","🚵","🚴","🏆","🥇","🥈","🥉","🏅","🎖️","🏵️","🎗️","🎫","🎟️","🎪","🤹","🎭","🎨","🎬","🎤","🎧","🎼","🎹","🥁","🎷","🎺","🎸","🎻","🎲","🧩","♟","🎯","🎳","🎮","🎰","🎁","🎈","🎀","🎊","🎉","🎎","🎏","🎐","🎃","🎄","🎆","🎇","✨"]},{"n":"Perjalanan & Tempat","i":"🚗","e":["🚗","🚕","🚙","🚌","🚎","🏎️","🚓","🚑","🚒","🚐","🚚","🚛","🚜","🛴","🚲","🛵","🏍️","🚨","🚔","🚍","🚘","🚖","🚡","🚠","🚟","🚃","🚋","🚞","🚝","🚄","🚅","🚈","🚂","🚆","🚇","🚊","🚉","✈️","🛫","🛬","🛩️","💺","🛰️","🚀","🛸","🚁","🛶","⛵","🚤","🛥️","🛳️","⛴️","🚢","⚓","⛽","🚧","🚦","🚥","🚏","🗺️","🗿","🗽","🗼","🏰","🏯","🏟️","🎡","🎢","🎠","⛲","⛱️","🏖️","🏝️","🏜️","🌋","⛰️","🏔️","🗻","🏕️","⛺","🏠","🏡","🏘️","🏚️","🏗️","🏭","🏢","🏬","🏣","🏤","🏥","🏦","🏨","🏪","🏫","🏩","💒","🏛️","⛪","🕌","🕍","🕋","⛩️","🛤️","🛣️","🗾","🎑","🏞️","🌅","🌄","🌠","🎇","🎆","🌇","🌆","🏙️","🌃","🌌","🌉","🌁"]},{"n":"Objek","i":"💡","e":["⌚","📱","📲","💻","⌨️","🖥️","🖨️","🖱️","🖲️","🕹️","🗜️","💽","💾","💿","📀","📼","📷","📸","📹","🎥","📽️","🎞️","📞","☎️","📟","📠","📺","📻","🎙️","🎚️","🎛️","⏱️","⏲️","⏰","🕰️","⌛","⏳","📡","🔋","🔌","💡","🔦","🕯️","🗑️","🛢️","💸","💵","💴","💶","💷","💰","💳","💎","⚖️","🔧","🔨","⚒️","🛠️","⛏️","🔩","⚙️","⛓️","🔫","💣","🔪","🗡️","⚔️","🛡️","🚬","⚰️","⚱️","🏺","🔮","📿","💈","⚗️","🔭","🔬","🕳️","💊","💉","🌡️","🚽","🚰","🚿","🛁","🛀","🛎️","🔑","🗝️","🚪","🛋️","🛏️","🛌","🖼️","🛍️","🛒","🎁","✉️","📩","📨","📧","📥","📤","📦","🏷️","📪","📫","📬","📭","📮","📯","📜","📃","📄","📑","📊","📈","📉","🗒️","🗓️","📆","📅","📇","🗃️","🗳️","🗄️","📋","📁","📂","🗂️","🗞️","📰","📓","📔","📒","📕","📗","📘","📙","📚","📖","🔖","🔗","📎","🖇️","📐","📏","📌","📍","✂️","🖊️","🖋️","✒️","🖌️","🖍️","📝","✏️","🔍","🔎","🔏","🔐","🔒","🔓"]},{"n":"Simbol","i":"❤️","e":["❤️","🧡","💛","💚","💙","💜","🖤","💔","❣️","💕","💞","💓","💗","💖","💘","💝","💟","☮️","✝️","☪️","🕉️","☸️","✡️","🔯","🕎","☯️","☦️","🛐","⛎","♈","♉","♊","♋","♌","♍","♎","♏","♐","♑","♒","♓","🆔","⚛️","🉑","☢️","☣️","📴","📳","🈶","🈚","🈸","🈺","🈷️","✴️","🆚","💮","🉐","㊙️","㊗️","🈴","🈵","🈹","🈲","🅰️","🅱️","🆎","🆑","🅾️","🆘","❌","⭕","🛑","⛔","📛","🚫","💯","💢","♨️","🚷","🚯","🚳","🚱","🔞","📵","🚭","❗","❕","❓","❔","‼️","⁉️","🔅","🔆","〽️","⚠️","🚸","🔱","⚜️","🔰","♻️","✅","🈯","💹","❇️","✳️","❎","🌐","💠","Ⓜ️","🌀","💤","🏧","🚾","♿","🅿️","🈳","🈂️","🛂","🛃","🛄","🛅","🚹","🚺","🚼","🚻","🚮","🎦","📶","🈁","🔣","ℹ️","🔤","🔡","🔠","🆖","🆗","🆙","🆒","🆕","🆓","🔟","🔢","⏏️","▶️","⏸️","⏯️","⏹️","⏺️","⏭️","⏮️","⏩","⏪","⏫","⏬","◀️","🔼","🔽","➡️","⬅️","⬆️","⬇️","↗️","↘️","↙️","↖️","↕️","↔️","↪️","↩️","⤴️","⤵️","🔀","🔁","🔂","🔄","🔃","🎵","🎶","➕","➖","➗","✖️","♾️","💲","💱","™️","©️","®️","〰️","➰","➿","🔚","🔙","🔛","🔝","🔜","✔️","☑️","🔘","⚪","⚫","🔴","🔵","🔺","🔻","🔸","🔹","🔶","🔷","🔳","🔲","▪️","▫️","◾","◽","◼️","◻️","⬛","⬜","🔈","🔇","🔉","🔊","🔔","🔕","📣","📢","💬","💭","🗯️","♠️","♣️","♥️","♦️","🃏","🎴","🀄","0️⃣","1️⃣","2️⃣","3️⃣","4️⃣","5️⃣","6️⃣","7️⃣","8️⃣","9️⃣","#️⃣","*️⃣"]}];
const POLL = CONF.poll;
const NAME_COLORS = ['#c83c3c','#28965a','#b46e14','#8246b4','#148ca0','#be4682'];
const REPLY_RE = /^\[\[R:([A-Za-z0-9_\-=]+)\]\]\s*/;
const TICK_SVG = '<svg viewBox="0 0 16 10"><path class="c1" d="M1 5.2 3.6 7.8 9 1.2"/><path class="c2" d="M5.6 5.2 8.2 7.8 13.6 1.2"/></svg>';
const REPLY_SVG = '<svg viewBox="0 0 24 24"><path d="M9 14 4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 6 6v3"/></svg>';

const $ = (s) => document.querySelector(s);
const LS = {
  get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } },
  set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
};
const el = (tag, cls, text) => { const e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };

// ---------- identitas (disimpan di browser ini) ----------
function newId() {
  const a = new Uint8Array(12);
  if (window.crypto && crypto.getRandomValues) crypto.getRandomValues(a); else for (let i = 0; i < 12; i++) a[i] = Math.random() * 256;
  return Array.from(a, (b) => b.toString(16).padStart(2, '0')).join('');
}
let CID = LS.get('obrolan_cid', '');
if (!CID) { CID = newId(); LS.set('obrolan_cid', CID); }

// ---------- state ----------
const S = {
  room: null, msgs: [], sig: '', lastMax: -1, lastCount: -1,
  reply: null, online: null, onlineErr: '', myRead: 0, othersRead: 0,
  forceBottom: false, unread: 0, notif: LS.get('obrolan_notif', 'on') !== 'off',
};
const chat = $('#chat'), ta = $('#msg'), nama = $('#nama'), sel = $('#room');

// ---------- format reply (sama dengan aplikasi desktop) ----------
function b64uEncode(str) {
  const bytes = new TextEncoder().encode(str); let bin = '';
  bytes.forEach((b) => { bin += String.fromCharCode(b); });
  return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_');
}
function b64uDecode(s) {
  s = s.replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '=';
  const bin = atob(s); return new TextDecoder().decode(Uint8Array.from(bin, (c) => c.charCodeAt(0)));
}
function encodeReply(rep, body) {
  const cut = (x, n) => Array.from(String(x)).slice(0, n).join('');
  const data = { c: rep.c, n: cut(rep.n, 24), t: rep.t, s: cut(rep.s, 50) };
  return '[[R:' + b64uEncode(JSON.stringify(data)) + ']]\n' + body;
}
function parseMessage(text) {
  const m = REPLY_RE.exec(text);
  if (!m) return { rep: null, body: text };
  try {
    const d = JSON.parse(b64uDecode(m[1]));
    return { rep: { c: String(d.c), n: String(d.n || '?'), t: Number(d.t) || 0, s: String(d.s || '') }, body: text.slice(m[0].length) };
  } catch (e) { return { rep: null, body: text }; }
}

// ---------- util ----------
const nameColor = (n) => NAME_COLORS[Array.from(n).reduce((a, c) => a + c.codePointAt(0), 0) % NAME_COLORS.length];
const pad = (n) => String(n).padStart(2, '0');
const timeLabel = (d) => pad(d.getHours()) + ':' + pad(d.getMinutes());
function dayLabel(d) {
  const t = new Date(), y = new Date(Date.now() - 86400000);
  const same = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  if (same(d, t)) return 'Hari ini';
  if (same(d, y)) return 'Kemarin';
  return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear();
}
const getName = () => nama.value.trim() || 'Tamu';
const atBottom = () => chat.scrollHeight - chat.scrollTop - chat.clientHeight < 40;
function setStatus(text, err) { const s = $('#status'); s.textContent = text; s.className = err ? 'err' : ''; }
const cur = () => S.room;
const baseTitle = () => (CONF.title || 'Obrolan') + ' - ' + (S.room ? S.room.name : '');

// ---------- suara & notifikasi ----------
let actx = null;
function unlockAudio() {
  try { actx = actx || new (window.AudioContext || window.webkitAudioContext)(); if (actx.state === 'suspended') actx.resume(); } catch (e) {}
}
['click', 'keydown', 'touchstart'].forEach((ev) => addEventListener(ev, unlockAudio, { passive: true }));
function beep() {
  if (!actx) return;
  const t = actx.currentTime;
  [[880, 0], [1175, 0.13]].forEach(([f, d]) => {
    const o = actx.createOscillator(), g = actx.createGain();
    o.type = 'sine'; o.frequency.value = f;
    g.gain.setValueAtTime(0.0001, t + d);
    g.gain.exponentialRampToValueAtTime(0.25, t + d + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, t + d + 0.2);
    o.connect(g); g.connect(actx.destination); o.start(t + d); o.stop(t + d + 0.22);
  });
}
function notify(n) {
  if (!S.notif) return;
  const away = document.hidden || !document.hasFocus();
  if (!away) return;                       // sedang melihat chat -> tidak perlu
  S.unread += n; document.title = '(' + S.unread + ') ' + baseTitle();
  beep();
  try {
    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification(baseTitle(), { body: n + ' pesan baru', tag: 'obrolan' });
    }
  } catch (e) {}
}
function clearBadge() { S.unread = 0; document.title = baseTitle(); }
function paintNotifBtn() {
  const b = $('#btnNotif'); b.classList.toggle('off', !S.notif);
  b.textContent = 'Notifikasi pesan masuk: ' + (S.notif ? 'ON' : 'OFF');
}
$('#btnNotif').addEventListener('click', () => {
  S.notif = !S.notif; LS.set('obrolan_notif', S.notif ? 'on' : 'off'); paintNotifBtn();
  if (S.notif) { unlockAudio(); try { if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission(); } catch (e) {} }
});

// ---------- tampilan pesan ----------
function render(forceBottom) {
  const wasBottom = forceBottom || atBottom(), prevTop = chat.scrollTop;
  const frag = document.createDocumentFragment();
  let prevDay = null, prevSender = null;
  S.msgs.forEach((m) => {
    const t = Number(m.time) || 0, d = new Date(t * 1000);
    const dayKey = d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();
    const mcid = String(m.cid || ''), name = String(m.name || '?');
    const mine = mcid !== '' && mcid === CID;
    const { rep, body } = parseMessage(String(m.message || ''));
    const sender = mcid || name;
    if (dayKey !== prevDay) { frag.appendChild(el('div', 'day', dayLabel(d))); prevDay = dayKey; prevSender = null; }
    const showName = !mine && sender !== prevSender;
    const row = el('div', 'msg ' + (mine ? 'mine' : 'other') + (sender === prevSender ? ' cont' : ''));
    row.dataset.c = mcid; row.dataset.t = t;
    const bub = el('div', 'bubble');
    if (showName) { const n = el('div', 'name', name); n.style.color = nameColor(name); bub.appendChild(n); }
    if (rep) {
      const q = el('div', 'quote'); q.dataset.c = rep.c; q.dataset.t = rep.t;
      q.appendChild(el('b', null, rep.n)); q.appendChild(el('span', null, rep.s.replace(/\s+/g, ' ')));
      bub.appendChild(q);
    }
    bub.appendChild(el('div', 'body', body));
    const meta = el('div', 'meta'); meta.appendChild(el('span', 'time', timeLabel(d)));
    if (mine) { const tk = el('span', 'tick'); tk.dataset.t = t; tk.innerHTML = TICK_SVG; meta.appendChild(tk); }
    bub.appendChild(meta); row.appendChild(bub);
    if (!mine) {
      const rb = el('button', 'rbtn'); rb.type = 'button'; rb.title = 'Balas'; rb.innerHTML = REPLY_SVG;
      rb.addEventListener('click', () => startReply({ c: mcid, n: name, t: t, s: body }));
      row.appendChild(rb);
    }
    frag.appendChild(row); prevSender = sender;
  });
  chat.textContent = ''; chat.appendChild(frag);
  updateTicks();
  chat.scrollTop = wasBottom ? chat.scrollHeight : prevTop;
}
function updateTicks() {
  chat.querySelectorAll('.tick').forEach((tk) => tk.classList.toggle('read', S.othersRead > 0 && Number(tk.dataset.t) <= S.othersRead));
}
chat.addEventListener('click', (e) => {
  const q = e.target.closest('.quote');
  if (q) jumpTo(q.dataset.c, Number(q.dataset.t));
});
function jumpTo(c, t) {
  for (const row of chat.querySelectorAll('.msg')) {
    if (row.dataset.c === c && Number(row.dataset.t) === t) {
      row.scrollIntoView({ block: 'center', behavior: 'smooth' });
      row.classList.add('flash'); setTimeout(() => row.classList.remove('flash'), 1400);
      return;
    }
  }
}

// ---------- baca pesan ----------
async function getJson(url) {
  const r = await fetch(url, { cache: 'no-store' });
  if (!r.ok) throw new Error('HTTP ' + r.status);
  const text = (await r.text()).trim();
  try { return JSON.parse(text); } catch (e) { throw new Error('balasan bukan JSON (cek file API di server)'); }
}
async function fetchMessages() {
  const room = cur();
  try {
    const data = await getJson(room.api);
    if (room !== cur()) return;
    if (!Array.isArray(data)) throw new Error('balasan API bukan daftar pesan');
    onMessages(data, room);
  } catch (e) {
    if (room === cur()) setStatus('Gagal konek: ' + e.message + ' [' + room.api + '] - cek config.php atau buka ?cek=1', true);
  }
}
function onMessages(data, room) {
  const last = data[data.length - 1] || {};
  const sig = data.length + '|' + (last.time || '') + '|' + (last.cid || '') + '|' + String(last.message || '').length;
  setStatus('Terhubung - ' + room.name + ' - ' + data.length + ' pesan');
  if (sig === S.sig) return;
  S.sig = sig;
  const prev = S.lastCount;
  const prevMax = S.lastMax;
  S.msgs = data; S.lastCount = data.length;
  S.lastMax = data.reduce((a, m) => Math.max(a, Number(m.time) || 0), 0);
  render(S.forceBottom); S.forceBottom = false;
  updateMyRead();
  if (prev >= 0) {
    const fresh = data.length > prev ? data.slice(prev) : data.filter((m) => (Number(m.time) || 0) > prevMax);
    let n = 0;
    fresh.forEach((m) => {
      if (String(m.cid || '') === CID) return;                       // pesan sendiri
      const { rep } = parseMessage(String(m.message || ''));
      if (rep && rep.c !== CID) return;                              // balasan untuk orang lain: tampil, tanpa notif
      n++;
    });
    if (n) notify(n);
  }
}

// ---------- kirim ----------
async function send() {
  const text = ta.value.trim();
  if (!text) return;
  const room = cur();
  let message = text;
  if (S.reply) { message = encodeReply(S.reply, text); cancelReply(); }
  closePops();
  ta.value = ''; autoGrow(); S.forceBottom = true;
  try {
    const r = await fetch(room.api, { method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: getName(), message: message, cid: CID }) });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    fetchMessages();
  } catch (e) { setStatus('Gagal kirim: ' + e.message, true); ta.value = text; autoGrow(); }
}
$('#btnSend').addEventListener('click', send);
ta.addEventListener('keydown', (e) => {
  if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); send(); }
});
function autoGrow() { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 120) + 'px'; }
ta.addEventListener('input', autoGrow);

// ---------- reply ----------
function startReply(item) {
  const s = String(item.s).replace(/\s+/g, ' ').slice(0, 50);
  S.reply = { c: item.c, n: item.n, t: item.t, s: s };
  $('#rbName').textContent = 'Membalas ' + item.n; $('#rbText').textContent = s;
  $('#replybar').classList.add('show'); ta.focus();
}
function cancelReply() { S.reply = null; $('#replybar').classList.remove('show'); }
$('#rbClose').addEventListener('click', cancelReply);

// ---------- online (presence) & status baca ----------
async function pingPresence() {
  const room = cur();
  const body = { cid: CID, name: getName(), ttl: Math.max(15, Math.floor(POLL * 2.5 / 1000) + 5) };
  if (S.myRead > 0) body.read = S.myRead;
  try {
    const r = await fetch(room.presence + '?room=' + encodeURIComponent(room.key), { method: 'POST', cache: 'no-store',
      headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    const j = await r.json();
    if (room !== cur()) return;
    if (!Array.isArray(j.users)) throw new Error('balasan presence.php tidak valid');
    S.online = j.users.map((u) => ({ cid: String(u.cid || ''), name: String(u.name || '?') })); S.onlineErr = '';
    let others = 0;
    if (j.reads && typeof j.reads === 'object') {
      Object.keys(j.reads).forEach((c) => { if (c !== CID) others = Math.max(others, parseInt(j.reads[c], 10) || 0); });
    }
    if (others > S.othersRead) { S.othersRead = others; updateTicks(); }
  } catch (e) { if (room === cur()) S.onlineErr = e.message; }
  paintOnline();
}
function leave(room) {
  try {
    const url = room.presence + '?room=' + encodeURIComponent(room.key);
    navigator.sendBeacon(url, new Blob([JSON.stringify({ cid: CID, leave: true })], { type: 'application/json' }));
  } catch (e) {}
}
function updateMyRead() {
  if (document.visibilityState !== 'visible' || !S.msgs.length || !atBottom()) return;
  const t = S.msgs.reduce((a, m) => Math.max(a, Number(m.time) || 0), 0);
  if (t > S.myRead) { S.myRead = t; pingPresence(); }
}
chat.addEventListener('scroll', updateMyRead, { passive: true });

function paintOnline() {
  const b = $('#btnOnline');
  if (S.online) { b.textContent = 'Chatroom Online : ' + S.online.length + ' orang'; b.title = S.onlineErr ? 'Update terakhir gagal: ' + S.onlineErr : 'Klik untuk melihat daftar'; }
  else if (S.onlineErr) { b.textContent = 'Chatroom Online : -'; b.title = 'presence.php belum terpasang / tidak bisa dihubungi: ' + S.onlineErr; }
  else { b.textContent = 'Chatroom Online : ...'; b.title = ''; }
  if ($('#online').classList.contains('show')) fillOnline();
}
function fillOnline() {
  const box = $('#online'); box.textContent = '';
  box.appendChild(el('h4', null, 'Chatroom Online'));
  if (!S.online) { box.appendChild(el('div', 'note', S.onlineErr ? 'presence.php tidak bisa dihubungi' : 'Memuat...')); return; }
  if (!S.online.length) { box.appendChild(el('div', 'note', 'Belum ada yang online')); return; }
  S.online.forEach((u, i) => {
    const me = u.cid === CID, row = el('div', 'u' + (me ? ' me' : ''));
    row.appendChild(el('i', null, String(i + 1))); row.appendChild(el('span', null, u.name + (me ? '  (kamu)' : '')));
    if (!me) row.addEventListener('click', () => pickOnline(u));
    box.appendChild(row);
  });
}
function pickOnline(u) {                     // klik nama -> reply pesan terbawah dari dia
  closePops();
  for (let i = S.msgs.length - 1; i >= 0; i--) {
    const m = S.msgs[i];
    if (String(m.cid || '') === u.cid) {
      const { body } = parseMessage(String(m.message || ''));
      startReply({ c: u.cid, n: String(m.name || u.name), t: Number(m.time) || 0, s: body });
      jumpTo(u.cid, Number(m.time) || 0);
      return;
    }
  }
  setStatus('Belum ada pesan dari ' + u.name + ' di chatroom ini.');
}
$('#btnOnline').addEventListener('click', (e) => {
  e.stopPropagation();
  const box = $('#online'), show = !box.classList.contains('show');
  closePops();
  if (show) { fillOnline(); box.classList.add('show'); pingPresence(); }
});

// ---------- emoji ----------
const RECENT_KEY = 'obrolan_emoji_recent';
const getRecent = () => { try { return JSON.parse(LS.get(RECENT_KEY, '[]')) || []; } catch (e) { return []; } };
let eTab = 0;
function buildEmoji() {
  const tabs = $('#etabs'), grid = $('#egrid');
  const recent = getRecent();
  const cats = [{ n: 'Terakhir', i: '\u{1F552}', e: recent }].concat(EMOJI);
  tabs.textContent = '';
  cats.forEach((c, idx) => {
    const b = el('button', idx === eTab ? 'on' : '', c.i); b.type = 'button'; b.title = c.n;
    b.addEventListener('click', (ev) => { ev.stopPropagation(); eTab = idx; buildEmoji(); });
    tabs.appendChild(b);
  });
  grid.textContent = '';
  const list = cats[eTab].e;
  if (!list.length) grid.appendChild(el('div', 'empty', 'Belum ada emoji terakhir'));
  list.forEach((em) => {
    const b = el('button', null, em); b.type = 'button';
    b.addEventListener('click', (ev) => { ev.stopPropagation(); insertEmoji(em); });
    grid.appendChild(b);
  });
  grid.scrollTop = 0;
}
function insertEmoji(em) {
  const s = ta.selectionStart, e = ta.selectionEnd;
  ta.setRangeText(em, s, e, 'end'); autoGrow(); ta.focus();
  const rec = getRecent().filter((x) => x !== em); rec.unshift(em); LS.set(RECENT_KEY, JSON.stringify(rec.slice(0, 24)));
}
$('#btnEmoji').addEventListener('click', (e) => {
  e.stopPropagation();
  const box = $('#emoji'), show = !box.classList.contains('show');
  closePops();
  if (show) { eTab = getRecent().length ? 0 : 1; buildEmoji(); box.classList.add('show'); }
});
function closePops() { $('#emoji').classList.remove('show'); $('#online').classList.remove('show'); }
document.addEventListener('click', (e) => { if (!e.target.closest('#emoji') && !e.target.closest('#online')) closePops(); });
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Escape') return;
  if ($('#emoji').classList.contains('show') || $('#online').classList.contains('show')) closePops(); else if (S.reply) cancelReply();
});

// ---------- chatroom & background ----------
function applyBg(room) {
  const w = $('#chatwrap');
  w.style.backgroundColor = /^#[0-9a-fA-F]{3,8}$/.test(room.bg_color || '') ? room.bg_color : '';
  if (!room.bg) { w.style.backgroundImage = 'none'; return; }
  w.style.backgroundImage = 'url("' + encodeURI(room.bg).replace(/"/g, '%22') + '")';
  const mode = (room.bg_mode || 'cover').toLowerCase();
  if (mode === 'stretch') { w.style.backgroundSize = '100% 100%'; w.style.backgroundRepeat = 'no-repeat'; }
  else if (mode === 'tile') { w.style.backgroundSize = 'auto'; w.style.backgroundRepeat = 'repeat'; }
  else if (mode === 'center') { w.style.backgroundSize = 'auto'; w.style.backgroundRepeat = 'no-repeat'; }
  else { w.style.backgroundSize = 'cover'; w.style.backgroundRepeat = 'no-repeat'; }
}
function switchRoom(i) {
  if (S.room) leave(S.room);
  S.room = CONF.rooms[i]; LS.set('obrolan_room', S.room.name);
  S.msgs = []; S.sig = ''; S.lastCount = -1; S.lastMax = -1; S.online = null; S.onlineErr = '';
  S.myRead = 0; S.othersRead = 0; S.forceBottom = true;
  cancelReply(); closePops(); applyBg(S.room); chat.textContent = '';
  clearBadge(); paintOnline(); setStatus('Menghubungkan ke ' + S.room.name + '...');
  fetchMessages(); pingPresence();
}
sel.addEventListener('change', () => switchRoom(sel.selectedIndex));

// ---------- start ----------
CONF.rooms.forEach((r) => { const o = document.createElement('option'); o.textContent = 'Chatroom : ' + r.name; sel.appendChild(o); });
nama.value = LS.get('obrolan_name', '');
nama.addEventListener('change', () => { LS.set('obrolan_name', nama.value.trim()); pingPresence(); });
$('#ver').textContent = 'v' + CONF.version + ' web';
paintNotifBtn();
const savedRoom = LS.get('obrolan_room', '');
const idx = Math.max(0, CONF.rooms.findIndex((r) => r.name === savedRoom));
sel.selectedIndex = idx;
switchRoom(idx);
setInterval(() => { fetchMessages(); pingPresence(); updateMyRead(); }, POLL);
document.addEventListener('visibilitychange', () => { if (!document.hidden) { clearBadge(); fetchMessages(); updateMyRead(); } });
addEventListener('focus', () => { clearBadge(); updateMyRead(); });
addEventListener('pagehide', () => { if (S.room) leave(S.room); });
if (!nama.value) nama.focus(); else ta.focus();
})();
</script>
</body>
</html>
