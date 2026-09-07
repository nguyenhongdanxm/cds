<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

const DUCK_RACE_MUSIC_DIR = DATA_PATH . '/game_assets/duck_race_music';
const DUCK_RACE_MUSIC_CONFIG = DATA_PATH . '/duck_race_music.json';
const DUCK_RACE_MUSIC_MAX_BYTES = 20 * 1024 * 1024;

function duck_race_music_config(): array {
    $config = load_json(DUCK_RACE_MUSIC_CONFIG, ['tracks' => [], 'default' => '']);
    $tracks = is_array($config['tracks'] ?? null) ? $config['tracks'] : [];
    $safeTracks = [];
    foreach ($tracks as $track) {
        if (!is_array($track) || !preg_match('/^[a-f0-9]{32}\.(mp3|ogg|wav)$/', (string)($track['file'] ?? ''))) continue;
        if (is_file(DUCK_RACE_MUSIC_DIR . '/' . $track['file'])) {
            $safeTracks[] = [
                'file' => (string)$track['file'],
                'name' => trim((string)($track['name'] ?? 'Nhạc nền')) ?: 'Nhạc nền',
                'mime' => (string)($track['mime'] ?? 'audio/mpeg'),
                'size' => (int)($track['size'] ?? 0),
                'created_at' => (string)($track['created_at'] ?? ''),
            ];
        }
    }
    $default = (string)($config['default'] ?? '');
    if (!array_filter($safeTracks, fn(array $track): bool => $track['file'] === $default)) $default = '';
    return ['tracks' => $safeTracks, 'default' => $default];
}

function duck_race_music_save(array $config): void {
    if (!save_json(DUCK_RACE_MUSIC_CONFIG, $config)) throw new RuntimeException('Không lưu được cấu hình nhạc nền.');
}

function duck_race_music_upload(array $upload): array {
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Hãy chọn một tệp nhạc hợp lệ.');
    $size = (int)($upload['size'] ?? 0);
    $tmp = (string)($upload['tmp_name'] ?? '');
    if ($size < 1 || $size > DUCK_RACE_MUSIC_MAX_BYTES || !is_uploaded_file($tmp)) throw new RuntimeException('Tệp nhạc phải nhỏ hơn hoặc bằng 20 MB.');
    $extension = strtolower((string)pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
    $expectedMimes = [
        'mp3' => ['audio/mpeg', 'audio/mp3', 'audio/x-mpeg'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/wave'],
    ];
    if (!isset($expectedMimes[$extension])) throw new RuntimeException('Chỉ nhận tệp MP3, OGG hoặc WAV.');
    $mime = function_exists('finfo_open') ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : '';
    if (!in_array($mime, $expectedMimes[$extension], true)) throw new RuntimeException('Nội dung tệp không khớp với định dạng âm thanh đã chọn.');
    $header = (string)@file_get_contents($tmp, false, null, 0, 12);
    $validHeader = ($extension === 'ogg' && str_starts_with($header, 'OggS'))
        || ($extension === 'wav' && substr($header, 0, 4) === 'RIFF' && substr($header, 8, 4) === 'WAVE')
        || ($extension === 'mp3' && (str_starts_with($header, 'ID3') || (isset($header[0], $header[1]) && ord($header[0]) === 0xff && (ord($header[1]) & 0xe0) === 0xe0)));
    if (!$validHeader) throw new RuntimeException('Tệp âm thanh không có chữ ký định dạng hợp lệ.');
    if (!is_dir(DUCK_RACE_MUSIC_DIR) && !mkdir(DUCK_RACE_MUSIC_DIR, 0755, true) && !is_dir(DUCK_RACE_MUSIC_DIR)) throw new RuntimeException('Không tạo được thư mục nhạc nền.');
    $file = bin2hex(random_bytes(16)) . '.' . $extension;
    if (!move_uploaded_file($tmp, DUCK_RACE_MUSIC_DIR . '/' . $file)) throw new RuntimeException('Không lưu được tệp nhạc lên máy chủ.');
    @chmod(DUCK_RACE_MUSIC_DIR . '/' . $file, 0644);
    return ['file' => $file, 'name' => mb_substr(basename((string)$upload['name']), 0, 160, 'UTF-8'), 'mime' => $mime, 'size' => $size, 'created_at' => date('c')];
}

$isDuckRaceAdmin = (current_user()['role'] ?? '') === 'admin';
if (empty($_SESSION['duck_race_music_csrf'])) $_SESSION['duck_race_music_csrf'] = bin2hex(random_bytes(32));
$duckRaceMusicCsrf = (string)$_SESSION['duck_race_music_csrf'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isDuckRaceAdmin || !hash_equals($duckRaceMusicCsrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Không có quyền thực hiện thao tác này.');
    }
    try {
        $musicConfig = duck_race_music_config();
        $action = (string)($_POST['music_action'] ?? '');
        if ($action === 'upload') {
            $musicConfig['tracks'][] = duck_race_music_upload(is_array($_FILES['music_file'] ?? null) ? $_FILES['music_file'] : []);
            duck_race_music_save($musicConfig);
            $message = 'Đã tải nhạc nền lên.';
        } elseif ($action === 'default') {
            $selected = (string)($_POST['default_track'] ?? '');
            if ($selected !== '' && !array_filter($musicConfig['tracks'], fn(array $track): bool => $track['file'] === $selected)) throw new RuntimeException('Bản nhạc đã chọn không tồn tại.');
            $musicConfig['default'] = $selected;
            duck_race_music_save($musicConfig);
            $message = $selected === '' ? 'Đã dùng nhạc tổng hợp mặc định.' : 'Đã chọn nhạc nền mặc định.';
        } elseif ($action === 'delete') {
            $file = (string)($_POST['track'] ?? '');
            $found = false;
            $musicConfig['tracks'] = array_values(array_filter($musicConfig['tracks'], function (array $track) use ($file, &$found): bool {
                if ($track['file'] !== $file) return true;
                $found = true;
                $path = DUCK_RACE_MUSIC_DIR . '/' . $track['file'];
                if (is_file($path) && !unlink($path)) throw new RuntimeException('Không xóa được tệp nhạc.');
                return false;
            }));
            if (!$found) throw new RuntimeException('Bản nhạc không tồn tại.');
            if ($musicConfig['default'] === $file) $musicConfig['default'] = '';
            duck_race_music_save($musicConfig);
            $message = 'Đã xóa bản nhạc.';
        } else throw new RuntimeException('Thao tác không hợp lệ.');
        $_SESSION['duck_race_music_notice'] = ['message' => $message, 'type' => 'success'];
    } catch (Throwable $e) {
        $_SESSION['duck_race_music_notice'] = ['message' => $e->getMessage(), 'type' => 'error'];
    }
    header('Location: ' . strtok((string)$_SERVER['REQUEST_URI'], '?'));
    exit;
}
$duckRaceMusic = duck_race_music_config();
$duckRaceMusicNotice = $_SESSION['duck_race_music_notice'] ?? null;
unset($_SESSION['duck_race_music_notice']);
if (!function_exists('csdl_students_all')) {
    $store = __DIR__ . '/includes/csdl_store.php';
    if (is_file($store)) require_once $store;
}

$classes = function_exists('csdl_classes_all') ? csdl_classes_all() : [];
$classMap = [];
$classNames = [];
foreach ($classes as $class) {
    if (!is_array($class) || (array_key_exists('active', $class) && empty($class['active']))) continue;
    $id = (string)($class['id'] ?? '');
    $name = trim((string)($class['name'] ?? ''));
    if ($name === '') continue;
    $classMap[$id] = $name;
    $classNames[] = $name;
}
$studentsByClass = [];
if (function_exists('csdl_students_all')) {
    foreach (csdl_students_all() as $student) {
        if (!is_array($student) || (array_key_exists('active', $student) && empty($student['active']))) continue;
        $name = trim((string)($student['name'] ?? $student['ho_ten'] ?? ''));
        $class = trim((string)($student['class_name'] ?? $student['class'] ?? $student['lop'] ?? ''));
        if ($class === '' && !empty($student['class_id'])) $class = $classMap[(string)$student['class_id']] ?? '';
        if ($name !== '' && $class !== '') $studentsByClass[$class][] = $name;
    }
    foreach ($studentsByClass as &$names) {
        $names = array_values(array_unique($names));
        sort($names, SORT_NATURAL);
    }
    unset($names);
}
sort($classNames, SORT_NATURAL);
$base = defined('BASE_URL') ? BASE_URL : '/';
$school = defined('SCHOOL_NAME') ? SCHOOL_NAME : 'CDS';
$duckRaceMusicUrl = $duckRaceMusic['default'] !== '' ? $base . 'data/game_assets/duck_race_music/' . rawurlencode($duckRaceMusic['default']) : '';
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đường đua vịt – <?= htmlspecialchars($school) ?></title>
<style>
:root{--ink:#13354c;--navy:#07314a;--aqua:#35c8df;--yellow:#ffd452;--coral:#ff785a;--leaf:#52a96c}
*{box-sizing:border-box}html,body{margin:0;min-height:100%;font-family:Inter,Segoe UI,system-ui,sans-serif;color:var(--ink)}
body{background:linear-gradient(180deg,#75d4e6 0 20%,#e8fbf7 20%);overflow-x:hidden}
.top{min-height:74px;padding:12px clamp(14px,4vw,48px);display:flex;align-items:center;gap:16px;background:#06334d;color:#fff;box-shadow:0 3px 14px #00314755;position:relative;z-index:5}
.brand{font-weight:900;font-size:clamp(18px,2.3vw,25px);letter-spacing:.03em;white-space:nowrap}.brand span{color:var(--yellow)}
.controls{margin-left:auto;display:flex;gap:9px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.controls select,.btn{border:0;border-radius:12px;min-height:40px;padding:0 13px;font:700 14px inherit;cursor:pointer}
.controls select{background:#fff;color:var(--ink);min-width:155px}.btn{background:#ffffff24;color:#fff;border:1px solid #ffffff55}.btn:hover{transform:translateY(-1px);background:#ffffff38}.btn.active{background:var(--yellow);color:#633c00;border-color:var(--yellow)}.back{color:#d8f7ff;text-decoration:none;font-weight:700}
.page{max-width:1400px;margin:auto;padding:clamp(16px,3vw,30px) clamp(12px,3vw,28px) 22px}.intro{display:flex;align-items:end;justify-content:space-between;gap:15px;margin-bottom:14px}.intro h1{margin:0;color:#083b55;font-size:clamp(25px,4vw,42px);letter-spacing:.02em}.intro p{margin:4px 0 0;color:#286070;font-weight:600}.timer{font-variant-numeric:tabular-nums;background:#fff;border:4px solid var(--yellow);border-radius:18px;padding:6px 18px;text-align:center;box-shadow:0 5px 0 #dcae2c;color:#0b4963;font-size:clamp(28px,5vw,48px);font-weight:950;line-height:1}.timer small{display:block;color:#5d8090;font-size:10px;letter-spacing:.12em;margin-top:4px}
.stadium{position:relative;overflow:hidden;min-height:520px;border:7px solid #fff;border-radius:28px;background:linear-gradient(#8cdef0 0 16%,#c1f0e8 16% 21%,#209ec1 21% 100%);box-shadow:0 14px 35px #174e623b}
.sky{height:105px;background:linear-gradient(115deg,#63cbe4,#b9f5fa);position:relative}.cloud{position:absolute;background:#fff9;border-radius:99px;width:82px;height:25px;top:28px;left:13%;box-shadow:32px -12px 0 7px #fff9,65px 1px #fff9}.cloud:last-child{left:auto;right:18%;top:50px;transform:scale(.65)}
.stands{height:55px;background:repeating-linear-gradient(90deg,#e75d55 0 13px,#f8d264 13px 26px,#5dbf87 26px 39px,#4d8fd1 39px 52px);border-top:6px solid #fff;border-bottom:6px solid #095c75;position:relative}.stands:after{content:"★  ★  ★  ★  ★  ★  ★  ★  ★  ★  ★";position:absolute;inset:11px 0 auto;text-align:center;word-spacing:40px;color:#fff;font-size:16px;text-shadow:0 1px 2px #244}
.pond{position:relative;height:clamp(470px,68vh,760px);min-height:470px;background:linear-gradient(180deg,#5bd3e8 0%,#29b7d4 32%,#1389b4 100%);isolation:isolate;transition:height .35s ease}.pond:before,.pond:after{content:"";position:absolute;inset:0;background:repeating-radial-gradient(ellipse at 20% 35%,#eaffff42 0 2px,transparent 3px 25px);animation:water 6s linear infinite;pointer-events:none}.pond:after{opacity:.6;animation-direction:reverse;animation-duration:9s;background-size:260px 100px}@keyframes water{to{background-position:190px 40px}}.bank{position:absolute;z-index:3;left:0;right:0;height:28px;pointer-events:none;background:linear-gradient(180deg,#6fcf68,#27894e);filter:drop-shadow(0 3px 2px #07556a55)}.bank.top{top:0;clip-path:polygon(0 0,100% 0,100% 48%,94% 75%,88% 52%,80% 88%,72% 48%,64% 78%,55% 50%,45% 84%,35% 52%,25% 78%,14% 50%,6% 86%,0 58%)}.bank.bottom{bottom:0;transform:rotate(180deg)}.flower{position:absolute;z-index:4;font-size:23px;filter:drop-shadow(0 2px 1px #07556a44);animation:sway 1.8s ease-in-out infinite alternate;pointer-events:none}.flower.f1{left:2%;top:8px}.flower.f2{left:22%;bottom:5px;animation-delay:-.7s}.flower.f3{right:17%;top:5px;animation-delay:-1.2s}.flower.f4{right:2%;bottom:4px}@keyframes sway{to{transform:rotate(8deg) translateY(-2px)}}.finish{position:absolute;top:-10px;bottom:-10px;right:7%;width:34px;transform:skewX(-8deg);background:repeating-conic-gradient(#fff 0 25%,#214d69 0 50%) 0/16px 16px;border-left:3px solid #fff;border-right:3px solid #fff;opacity:.96;z-index:4}.finish b{position:absolute;top:8px;left:50%;transform:translateX(-50%) rotate(90deg);white-space:nowrap;background:#083b55;color:#fff;border-radius:999px;padding:4px 9px;font-size:10px;letter-spacing:.1em}
.river{position:absolute;inset:0;z-index:2;overflow:hidden}.start-line{position:absolute;z-index:1;top:27px;bottom:27px;left:9%;width:5px;background:repeating-linear-gradient(180deg,#fff 0 11px,#183f59 11px 22px);border-radius:3px;box-shadow:0 0 0 2px #ffffff99,0 0 12px #06334d66}.start-line:after{content:"XUẤT PHÁT";position:absolute;top:50%;left:-34px;transform:rotate(-90deg);transform-origin:center;background:#fff;color:#0b4963;border:2px solid #0b4963;border-radius:9px;padding:3px 8px;font-size:9px;font-weight:950;letter-spacing:.08em;white-space:nowrap}.duck{position:absolute;width:82px;height:58px;transform:translate(-50%,-50%);filter:drop-shadow(0 5px 2px #075a7370);will-change:left,top,transform;z-index:2;animation:duckBob 1.1s ease-in-out infinite alternate}.duck:after{content:"";position:absolute;left:-20px;top:36px;width:48px;height:9px;border-top:3px solid #dffbffb8;border-radius:50%;animation:wake 1s ease-in-out infinite}.duck .body{position:absolute;inset:14px 5px 0;border-radius:56% 45% 50% 48%;background:radial-gradient(circle at 34% 22%,#fff9 0 7%,transparent 8%),linear-gradient(145deg,var(--duck-light,#fff080),var(--duck-body,#ffc52d) 64%,var(--duck-dark,#e99b00));border:2px solid var(--duck-edge,#d88e00)}.duck .head{position:absolute;right:2px;top:0;width:34px;height:34px;border-radius:50%;background:var(--duck-head,#ffdf4e);border:2px solid var(--duck-edge,#d88e00)}.duck .head:before{content:"";position:absolute;right:4px;top:8px;width:6px;height:6px;background:#102d40;border-radius:50%;box-shadow:0 0 0 2px #fff}.duck .beak{position:absolute;right:-11px;top:19px;width:20px;height:11px;border-radius:2px 9px 9px 2px;background:#ff7845;border:1px solid #c94f2c}.duck .wing{position:absolute;left:16px;top:29px;width:33px;height:21px;border-radius:50%;background:var(--duck-dark,#f2ab12);border:1px solid var(--duck-edge,#d88e00);transform-origin:80% 50%;animation:wingIdle 1.2s ease-in-out infinite alternate}.duck .cap{position:absolute;right:8px;top:-5px;width:22px;height:9px;border-radius:10px 10px 2px 2px;background:#ef5f5b}.duck .name{position:absolute;z-index:5;left:50%;bottom:56px;transform:translateX(-50%);max-width:170px;min-width:92px;padding:3px 8px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;text-align:center;color:#082f47;background:#fff;border:2px solid var(--duck-edge,#d88e00);border-radius:9px;font-size:11px;font-weight:950;line-height:15px;box-shadow:0 3px 7px #06334d55}.duck .name:after{content:"";position:absolute;left:50%;bottom:-6px;width:8px;height:8px;background:#fff;border-right:2px solid var(--duck-edge,#d88e00);border-bottom:2px solid var(--duck-edge,#d88e00);transform:translateX(-50%) rotate(45deg)}.duck .number{position:absolute;z-index:4;left:24px;top:27px;min-width:22px;height:18px;padding:0 4px;display:grid;place-items:center;border-radius:8px;background:#fff;color:#17384b;font-size:10px;font-weight:950;border:1px solid #17384b}.duck .speed-trail{position:absolute;right:64px;top:28px;width:0;height:14px;border-top:3px solid #fff;border-bottom:2px solid #dffaff;opacity:0;filter:drop-shadow(0 0 4px #fff)}.river.racing .duck{animation-duration:.42s}.river.racing .duck.breakaway{z-index:8!important;filter:drop-shadow(0 0 13px #fff6a0) drop-shadow(0 7px 3px #075a7370)}.river.racing .duck.breakaway .speed-trail{width:58px;opacity:.9;animation:trailPulse .18s linear infinite alternate}.river.racing .duck.breakaway .name{background:#fff8b8;transform:translateX(-50%) scale(1.08)}@keyframes trailPulse{to{width:78px;opacity:.35}}.river.racing .duck .wing{animation:wingRace .22s ease-in-out infinite alternate}.duck.winner{z-index:6;filter:drop-shadow(0 0 11px #fff500) drop-shadow(0 5px 2px #075a7370)}@keyframes duckBob{to{margin-top:-3px}}@keyframes wingIdle{to{transform:rotate(7deg)}}@keyframes wingRace{from{transform:rotate(-28deg)}to{transform:rotate(18deg) scaleY(.8)}}@keyframes wake{50%{width:61px;opacity:.45}}.ripple{position:absolute;width:20px;height:8px;border:2px solid #e3fcff99;border-radius:50%;transform:translate(-50%,-50%);pointer-events:none;animation:ripple .8s ease-out forwards}@keyframes ripple{to{width:75px;height:29px;opacity:0}}.splash{position:absolute;color:#e9feff;font-size:21px;font-weight:900;pointer-events:none;animation:splash .65s ease-out forwards}@keyframes splash{to{transform:translate(var(--sx),-28px) rotate(25deg);opacity:0}}
.lily{position:absolute;width:52px;height:25px;border-radius:100% 0 100% 0;background:#56ae68;opacity:.9}.lily:after{content:"";position:absolute;left:20px;top:5px;width:12px;height:12px;border-radius:50%;background:#ff8bb4}.lily.one{left:4%;bottom:15px}.lily.two{right:3%;bottom:30px;transform:scale(.7)}.note{position:absolute;bottom:10px;left:50%;transform:translateX(-50%);z-index:4;background:#073c55e8;color:#fff;border-radius:999px;padding:8px 18px;font-size:13px;font-weight:800;white-space:nowrap;box-shadow:0 3px 12px #003a4d77}
.empty{display:grid;place-items:center;height:100%;text-align:center;color:#eaffff;font-weight:800;font-size:18px;padding:32px}.empty span{display:block;font-size:44px;margin-bottom:8px}.lower{display:flex;gap:12px;align-items:center;justify-content:space-between;margin-top:15px;flex-wrap:wrap}.status{font-size:14px;font-weight:700;color:#24586a}.start{background:linear-gradient(135deg,#ff8b57,#ed5356);border:0;border-radius:16px;color:#fff;padding:13px 29px;font-size:17px;font-weight:950;letter-spacing:.06em;cursor:pointer;box-shadow:0 5px 0 #b63842}.start:hover{transform:translateY(-2px)}.start:disabled{opacity:.55;cursor:not-allowed;transform:none}.winners{background:#fff;border-radius:16px;padding:10px 14px;box-shadow:0 5px 16px #1f657222;display:flex;gap:8px;align-items:center;max-width:100%}.winners strong{white-space:nowrap}.winner-list{font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#50717c}.race-options{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:800}.race-options select{border:2px solid #9ddae5;border-radius:10px;padding:7px;color:var(--ink);font:inherit}
.overlay{display:none;position:fixed;inset:0;z-index:20;place-items:center;background:#02293dcc;padding:18px}.overlay.show{display:grid}.winner-card{position:relative;overflow:hidden;width:min(520px,94vw);padding:34px 24px 28px;text-align:center;color:#0b415b;background:linear-gradient(145deg,#fff,#fff4c4);border:6px solid #fff;border-radius:28px;box-shadow:0 18px 55px #0008;animation:arrive .42s cubic-bezier(.2,1.5,.4,1)}@keyframes arrive{from{opacity:0;transform:scale(.55) rotate(-5deg)}}.cup{font-size:58px}.winner-card h2{font-size:17px;letter-spacing:.14em;margin:4px 0;color:#c76128}.winner-card .winner-name{font-size:clamp(29px,7vw,52px);font-weight:950;margin:8px 0 22px}.winner-card button{border:0;border-radius:999px;padding:11px 20px;background:#0b4a65;color:#fff;font-weight:900;cursor:pointer}.confetti{pointer-events:none;position:fixed;inset:0;z-index:21;overflow:hidden}.piece{position:absolute;width:10px;height:16px;animation:fall 2.4s linear forwards}@keyframes fall{to{transform:translate(var(--x),110vh) rotate(760deg);opacity:0}}
.cheer{font-size:22px;letter-spacing:5px;animation:bounce .55s ease-in-out infinite alternate}@keyframes bounce{to{transform:translateY(-7px) rotate(3deg)}}@media(max-width:650px){.top{align-items:flex-start}.brand{padding-top:8px}.controls{margin-left:0}.back{display:none}.stadium{min-height:455px}.pond{min-height:430px}.finish{right:4%;width:28px}.duck{transform:translate(-50%,-50%) scale(.82)}.duck .name{font-size:10px;min-width:78px;max-width:120px}.note{font-size:11px;max-width:90%;white-space:normal;text-align:center}.intro{align-items:flex-start}.timer{padding:5px 10px}}@media(prefers-reduced-motion:reduce){.duck,.duck .wing,.flower,.pond:before,.pond:after{animation:none!important}}
.music-settings{margin:0 auto 16px;max-width:1400px;padding:0 clamp(12px,3vw,28px)}.music-settings details{background:#fff;border-radius:14px;padding:12px 16px;box-shadow:0 4px 15px #174e6222}.music-settings summary{cursor:pointer;font-weight:900;color:#083b55}.music-settings form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px}.music-settings select,.music-settings input{max-width:100%;padding:8px;border:1px solid #9ddae5;border-radius:8px}.music-settings button{border:0;border-radius:8px;padding:8px 11px;background:#0b4a65;color:#fff;font-weight:800;cursor:pointer}.music-settings .danger{background:#bd3b3b}.music-settings small{color:#52717c}.music-notice{margin-top:10px;font-weight:700}.music-notice.error{color:#b42318}.music-notice.success{color:#16803c}
</style>
</head>
<body>
<header class="top"><div class="brand">ĐƯỜNG ĐUA <span>VỊT VUI NHỘN</span></div><div class="controls">
  <select id="classSelect"><option value="">Chọn lớp để bắt đầu</option><?php foreach ($classNames as $name): ?><option value="<?= htmlspecialchars($name) ?>"><?= htmlspecialchars($name) ?></option><?php endforeach; ?></select>
  <button class="btn" id="shuffle" type="button">↻ Xáo vị trí</button>
  <button class="btn" id="removeWinners" type="button">Loại người thắng: Tắt</button>
  <button class="btn" id="sound" type="button" aria-pressed="false">♪ Âm thanh: Tắt</button>
  <button class="btn" id="reset" type="button">↺ Làm mới</button>
  <a class="back" href="<?= htmlspecialchars($base) ?>hoclieu.php?tab=games">← Học liệu</a>
</div></header>
<?php if ($isDuckRaceAdmin): ?>
<section class="music-settings"><details<?= $duckRaceMusicNotice ? ' open' : '' ?>><summary>⚙ Quản lý nhạc nền cuộc đua (quản trị viên)</summary>
  <?php if ($duckRaceMusicNotice): ?><div class="music-notice <?= htmlspecialchars((string)$duckRaceMusicNotice['type']) ?>"><?= htmlspecialchars((string)$duckRaceMusicNotice['message']) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= htmlspecialchars($duckRaceMusicCsrf) ?>"><input type="hidden" name="music_action" value="upload"><input type="file" name="music_file" accept=".mp3,.ogg,.wav,audio/mpeg,audio/ogg,audio/wav" required><button type="submit">Tải nhạc lên</button><small>MP3, OGG hoặc WAV · tối đa 20 MB</small></form>
  <form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($duckRaceMusicCsrf) ?>"><input type="hidden" name="music_action" value="default"><select name="default_track"><option value="">Nhạc tổng hợp của trò chơi</option><?php foreach ($duckRaceMusic['tracks'] as $track): ?><option value="<?= htmlspecialchars($track['file']) ?>"<?= $duckRaceMusic['default'] === $track['file'] ? ' selected' : '' ?>><?= htmlspecialchars($track['name']) ?> (<?= number_format($track['size'] / 1048576, 1) ?> MB)</option><?php endforeach; ?></select><button type="submit">Chọn mặc định</button></form>
  <?php foreach ($duckRaceMusic['tracks'] as $track): ?><form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($duckRaceMusicCsrf) ?>"><input type="hidden" name="music_action" value="delete"><input type="hidden" name="track" value="<?= htmlspecialchars($track['file']) ?>"><span><?= htmlspecialchars($track['name']) ?></span><button class="danger" type="submit" onclick="return confirm('Xóa bản nhạc này?')">Xóa</button></form><?php endforeach; ?>
</details></section>
<?php endif; ?>
<main class="page">
 <div class="intro"><div><h1>Sẵn sàng về đích!</h1><p>Tất cả vịt cùng bơi trên một dòng sông — hãy chờ những màn bứt phá bất ngờ!</p></div><div class="timer"><span id="timer">00.00</span><small>THỜI GIAN</small></div></div>
 <section class="stadium"><div class="sky"><i class="cloud"></i><i class="cloud"></i></div><div class="stands"></div><div class="pond" id="pond"><i class="bank top"></i><i class="bank bottom"></i><i class="flower f1">🌼🌿</i><i class="flower f2">🌷🌱</i><i class="flower f3">🌻🌿</i><i class="flower f4">🌺🌱</i><div class="start-line"></div><div class="finish"><b>VỀ ĐÍCH</b></div><div id="river" class="river"><div class="empty"><div><span>🦆</span>Hãy chọn một lớp để mở dòng sông.</div></div></div><i class="lily one"></i><i class="lily two"></i><div class="note" id="note">Tên học sinh hiển thị trong nhãn riêng phía trên mỗi chú vịt</div></div></section>
 <div class="lower"><div class="winners"><strong>🏆 Đã thắng:</strong><span class="winner-list" id="winnerList">Chưa có lượt đua nào</span></div><div class="race-options"><label for="duration">Thời lượng</label><select id="duration"><option value="8">8 giây</option><option value="12" selected>12 giây</option><option value="18">18 giây</option><option value="25">25 giây</option></select></div><div class="status" id="status">Chọn lớp để nạp danh sách học sinh.</div><button id="start" class="start" type="button" disabled>BẮT ĐẦU ĐUA!</button></div>
</main>
<div class="overlay" id="overlay"><div class="winner-card"><div class="cup">🏆</div><div class="cheer">🙌 🎉 🦆 🎉 🙌</div><h2>NGƯỜI VỀ ĐÍCH ĐẦU TIÊN</h2><div class="winner-name" id="winnerName"></div><p>Khán giả đang reo hò chúc mừng!</p><button id="raceAgain" type="button">Đua lượt tiếp theo</button></div></div><div class="confetti" id="confetti"></div>
<script>
const studentsByClass = <?= json_encode($studentsByClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const configuredMusicUrl = <?= json_encode($duckRaceMusicUrl, JSON_UNESCAPED_SLASHES) ?>;
const $ = id => document.getElementById(id);
let racers=[], winners=[], removeWinners=false, racing=false, startedAt=0, raf=0, audioContext=null, soundOn=false, lastQuack=0, backgroundMusic=null, racePhase='';
const classSelect=$('classSelect'), river=$('river'), start=$('start'), timer=$('timer'), duration=$('duration');
function shuffle(items){ for(let i=items.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[items[i],items[j]]=[items[j],items[i]]} return items }
function savedWinners(){try{return JSON.parse(localStorage.getItem('cds_duck_winners_'+classSelect.value)||'[]')}catch(e){return[]}}
function saveWinners(){localStorage.setItem('cds_duck_winners_'+classSelect.value,JSON.stringify(winners))}
function render(){
  const source=(studentsByClass[classSelect.value]||[]).slice();
  const available=removeWinners ? source.filter(name=>!winners.includes(name)) : source;
  racers=shuffle(available);
  if(!classSelect.value){river.innerHTML='<div class="empty"><div><span>🦆</span>Hãy chọn một lớp để mở dòng sông.</div></div>';start.disabled=true;return}
  if(!racers.length){river.innerHTML='<div class="empty"><div><span>🏁</span>Tất cả học sinh của lớp này đã thắng. Nhấn “Làm mới” để đua lại.</div></div>';start.disabled=true;return}
  const palettes=[['#fff7a8','#ffc928','#e79a00','#d68600','#ffe158'],['#ffd9ef','#f38bbc','#d74c91','#b92f78','#f7a9ce'],['#d9f7ff','#69c7ee','#2698ce','#1478ad','#8edafa'],['#e8dcff','#9c78e9','#6948c8','#5134a7','#b49afa'],['#dbffd7','#69cf70','#2b9f4d','#17823a','#8ee595'],['#ffe2c2','#f49a48','#d66a24','#b84d14','#ffb66e']];
  river.innerHTML=racers.map((name,i)=>{const p=palettes[i%palettes.length];return `<div class="duck" data-name="${escapeAttr(name)}" data-index="${i}" style="--duck-light:${p[0]};--duck-body:${p[1]};--duck-dark:${p[2]};--duck-edge:${p[3]};--duck-head:${p[4]}"><i class="body"></i><i class="wing"></i><i class="head"></i><i class="beak"></i><i class="cap" style="background:${['#ef5f5b','#476fc0','#50a66d','#a663ae','#f09139','#1b9aaa'][i%6]}"></i><i class="speed-trail"></i><b class="number">${i+1}</b><span class="name" title="${escapeAttr(name)}">${escapeHtml(name)}</span></div>`}).join('');
  start.disabled=racers.length<2;
  positionDucks();
  $('status').textContent=racers.length<2?'Cần ít nhất 2 học sinh để đua.':`${racers.length} chú vịt cùng tập trung trên dòng sông.`;
}
function escapeHtml(value){const el=document.createElement('span');el.textContent=value;return el.innerHTML}
function escapeAttr(value){return escapeHtml(value).replace(/"/g,'&quot;')}
function showWinners(){ $('winnerList').textContent=winners.length?winners.join(' · '):'Chưa có lượt đua nào'; }
function loadClass(){winners=savedWinners();timer.textContent='00.00';showWinners();render()}
function positionDucks(){
 const ducks=[...river.querySelectorAll('.duck')], total=ducks.length, pond=$('pond');
 pond.style.height=Math.max(520,Math.min(760,total*16+90))+'px';
 ducks.forEach((duck,i)=>{duck.dataset.x='8';duck.dataset.prevX='8';duck.dataset.y=(8+((i+.5)/Math.max(1,total))*84).toFixed(2);duck.dataset.seed=(Math.random()*100).toFixed(2);duck.dataset.drama=(Math.random()*2-1).toFixed(3);duck.style.left='8%';duck.style.top=duck.dataset.y+'%';duck.style.zIndex=String(2+i%3)});
}
function tone(freq, seconds, type='sine', volume=.04){
 if(!soundOn)return;audioContext ||= new (window.AudioContext||window.webkitAudioContext)();const osc=audioContext.createOscillator(), gain=audioContext.createGain();osc.type=type;osc.frequency.setValueAtTime(freq,audioContext.currentTime);gain.gain.setValueAtTime(volume,audioContext.currentTime);gain.gain.exponentialRampToValueAtTime(.001,audioContext.currentTime+seconds);osc.connect(gain).connect(audioContext.destination);osc.start();osc.stop(audioContext.currentTime+seconds);
}
function quack(){tone(480,.09,'square',.035);setTimeout(()=>tone(360,.12,'square',.025),65)}
function music(){if(!soundOn||!racing)return;[523,659,784,659].forEach((n,i)=>setTimeout(()=>tone(n,.18,'triangle',.018),i*190));setTimeout(music,900)}
function startBackgroundMusic(){if(!soundOn||!racing)return;if(!configuredMusicUrl){music();return}backgroundMusic ||= new Audio(configuredMusicUrl);backgroundMusic.loop=true;backgroundMusic.volume=.28;backgroundMusic.play().catch(()=>{});}
function stopBackgroundMusic(){if(backgroundMusic){backgroundMusic.pause();backgroundMusic.currentTime=0;}}
function splash(x,y){const ripple=document.createElement('i');ripple.className='ripple';ripple.style.left=x+'%';ripple.style.top=y+'%';river.appendChild(ripple);setTimeout(()=>ripple.remove(),900);if(Math.random()<.4){const drop=document.createElement('b');drop.className='splash';drop.textContent='💦';drop.style.left=x+'%';drop.style.top=y+'%';drop.style.setProperty('--sx',(Math.random()>.5?12:-12)+'px');river.appendChild(drop);setTimeout(()=>drop.remove(),700)}}
function animate(now){
 const elapsed=now-startedAt, raceMs=+duration.value*1000, progress=Math.min(1,elapsed/raceMs);timer.textContent=(elapsed/1000).toFixed(2).padStart(5,'0');
 const ducks=[...river.querySelectorAll('.duck')];
 const positions=[];ducks.forEach((duck,i)=>{const seed=+duck.dataset.seed,rank=+duck.dataset.rank,rankRate=ducks.length>1?rank/(ducks.length-1):0,drama=+duck.dataset.drama;const middle=Math.sin(Math.min(1,progress/.68)*Math.PI)*drama*9;const chase=Math.sin(progress*24+seed)*2.7+Math.sin(progress*51+seed)*1.15;const finalT=Math.max(0,(progress-.62)/.38),finalEase=finalT*finalT*(3-2*finalT),finishBoost=finalEase*(rankRate*14-4);let x=8+progress*78+middle+chase*Math.sin(Math.PI*progress)+finishBoost;const previous=+duck.dataset.prevX||8;x=Math.max(previous-.12,Math.min(94,x));duck.dataset.prevX=x.toFixed(2);let y=+duck.dataset.y+Math.sin(progress*15+seed)*2.8+Math.sin(progress*37+seed)*1.2;y=Math.max(7,Math.min(93,y));duck.style.left=x+'%';duck.style.top=y+'%';duck.style.transform=`translate(-50%,-50%) rotate(${Math.sin(progress*20+seed)*4}deg) scale(${progress>.72&&rankRate>.72?1.12:1})`;positions.push([duck,x]);if(Math.random()<(progress>.65?.09:.04))splash(x,y);});positions.sort((a,b)=>b[1]-a[1]);ducks.forEach(d=>d.classList.remove('breakaway'));if(progress>.62)positions.slice(0,Math.min(3,positions.length)).forEach(p=>p[0].classList.add('breakaway'));if(progress>.72&&racePhase!=='sprint'){racePhase='sprint';$('status').textContent='🔥 BỨT PHÁ! Nhóm dẫn đầu đang tăng tốc về đích!';tone(880,.18,'sawtooth',.045)}else if(progress>.34&&progress<=.72&&racePhase!=='chase'){racePhase='chase';$('status').textContent='🌊 Bám đuổi quyết liệt — thứ hạng đang thay đổi liên tục!';}
 if(soundOn&&now-lastQuack>1300+Math.random()*1200){quack();lastQuack=now}
 if(progress<1){raf=requestAnimationFrame(animate);return} finishRace();
}
function finishRace(){
 racing=false; river.classList.remove('racing'); stopBackgroundMusic(); start.disabled=false;
 const duck=[...river.querySelectorAll('.duck')].sort((a,b)=>+b.dataset.rank- +a.dataset.rank)[0];
 duck.classList.add('winner');const name=duck.dataset.name;
 if(!winners.includes(name)){winners.push(name);saveWinners();showWinners()}
 $('winnerName').textContent=name;$('overlay').classList.add('show');$('status').textContent=`${name} đã cán đích đầu tiên! Cả sân đang reo hò!`;confetti();tone(784,.25,'triangle',.08);setTimeout(()=>tone(1047,.55,'triangle',.07),180);
}
function begin(){
 if(racing||racers.length<2)return;racing=true;racePhase='start';river.classList.add('racing');start.disabled=true;$('status').textContent='Xuất phát! Cổ vũ thật lớn nào!';
 shuffle([...river.querySelectorAll('.duck')]).forEach((duck,i)=>{duck.dataset.rank=i;duck.dataset.prevX='8';duck.classList.remove('winner','breakaway')});startedAt=performance.now();lastQuack=startedAt;quack();startBackgroundMusic();raf=requestAnimationFrame(animate);
}
function confetti(){const box=$('confetti'), colors=['#ffd452','#ff785a','#35c8df','#7ecf74','#b078d1'];box.innerHTML='';for(let i=0;i<90;i++){const p=document.createElement('i');p.className='piece';p.style.left=Math.random()*100+'vw';p.style.top=(-10-Math.random()*35)+'px';p.style.background=colors[i%colors.length];p.style.setProperty('--x',(-120+Math.random()*240)+'px');p.style.animationDelay=Math.random()*.35+'s';box.appendChild(p)}setTimeout(()=>box.innerHTML='',3000)}
classSelect.addEventListener('change',loadClass);
$('shuffle').onclick=()=>{if(!racing&&classSelect.value){render();$('status').textContent='Đã xáo lại các làn đua.'}};
$('removeWinners').onclick=function(){if(racing)return;removeWinners=!removeWinners;this.classList.toggle('active',removeWinners);this.textContent='Loại người thắng: '+(removeWinners?'Bật':'Tắt');render()};
$('reset').onclick=()=>{if(racing)return;winners=[];saveWinners();showWinners();timer.textContent='00.00';render();$('status').textContent='Đã khôi phục tất cả học sinh.'};
$('sound').onclick=function(){soundOn=!soundOn;this.classList.toggle('active',soundOn);this.setAttribute('aria-pressed',String(soundOn));this.textContent='♪ Âm thanh: '+(soundOn?'Bật':'Tắt');if(soundOn){tone(523,.12,'triangle',.04);if(racing)startBackgroundMusic()}else stopBackgroundMusic()};
start.onclick=begin;$('raceAgain').onclick=()=>{$('overlay').classList.remove('show');timer.textContent='00.00';render()};
</script>
</body>
</html>
