<?php
require_once __DIR__ . '/includes/auth.php';

$id = trim((string)($_GET['id'] ?? $_GET['notice'] ?? ''));
if ($id === '') { http_response_code(404); exit('Không tìm thấy thông báo.'); }

$rows = is_file(DATA_PATH . '/cm_docs.json') ? json_decode((string)@file_get_contents(DATA_PATH . '/cm_docs.json'), true) : [];
$notice = null;
foreach (is_array($rows) ? $rows : [] as $row) {
    if (($row['section'] ?? '') === 'kh_thongbao' && (string)($row['id'] ?? '') === $id) { $notice = $row; break; }
}
if (!$notice) { http_response_code(404); exit('Không tìm thấy thông báo.'); }

$title = trim((string)($notice['title'] ?? 'Thông báo')) ?: 'Thông báo';
$content = trim((string)($notice['content'] ?? ''));
$plain = trim(strip_tags($content));
$filePath = trim((string)($notice['file_path'] ?? ''));
$link = trim((string)($notice['link'] ?? ''));
$fileId = str_starts_with($filePath, 'gdrive:') ? trim(substr($filePath, 7)) : '';
$back = trim((string)($_GET['back'] ?? ''));
if ($back === '' || preg_match('~^(?:https?:)?//~i', $back)) $back = '/';

if ($plain === '' && $fileId !== '') {
    header('Location: ' . BASE_URL . 'public_drive_viewer.php?' . http_build_query(['id'=>$fileId,'title'=>$title,'back'=>$back]));
    exit;
}
if ($plain === '' && $link !== '') {
    header('Location: ' . $link);
    exit;
}

$fileUrl = $fileId !== '' ? BASE_URL . 'public_drive_viewer.php?' . http_build_query(['id'=>$fileId,'title'=>$title,'back'=>BASE_URL.'public_notice.php?id='.rawurlencode($id)]) : '';
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=e($title)?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>*{box-sizing:border-box}body{margin:0;background:#f3f6fa;color:#172033;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}.top{position:sticky;top:0;z-index:5;display:flex;align-items:center;gap:.7rem;padding:.7rem .9rem;background:#fff;border-bottom:1px solid #dbe5ee;box-shadow:0 2px 10px #0001}.top strong{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.top a,.top button{border:1px solid #cbd5e1;background:#fff;color:#1e293b;border-radius:10px;min-height:40px;padding:.55rem .8rem;font:inherit;font-weight:750;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer}.top .close{background:#0f4c81;color:#fff;border-color:#0f4c81}.body{width:min(920px,calc(100% - 2rem));margin:1.2rem auto;background:#fff;border:1px solid #dfe8f0;border-radius:18px;box-shadow:0 10px 32px #173b5d12;padding:1.2rem 1.3rem}.meta{color:#64748b;font-size:.9rem;margin:.35rem 0 1rem}.content{font-size:1rem;line-height:1.65;white-space:pre-wrap}.actions{display:flex;flex-wrap:wrap;gap:.65rem;margin-top:1.2rem;padding-top:1rem;border-top:1px solid #e5edf4}.actions a{display:inline-flex;align-items:center;gap:.45rem;padding:.65rem .9rem;border-radius:10px;text-decoration:none;font-weight:750}.file{background:#0f4c81;color:#fff}.link{border:1px solid #cbd5e1;color:#1e293b}@media(max-width:600px){.top{padding:.55rem}.top .label{display:none}.body{width:calc(100% - 1rem);margin:.5rem auto;border-radius:12px;padding:1rem}}</style></head><body><header class="top"><button class="close" type="button" onclick="closePage()"><i class="bi bi-x-lg"></i><span class="label">Đóng</span></button><strong><?=e($title)?></strong></header><main class="body"><h2><?=e($title)?></h2><div class="meta"><?=e((string)($notice['date'] ?? ''))?><?=!empty($notice['by'])?' · '.e((string)$notice['by']):''?></div><div class="content"><?=nl2br(e(strip_tags($content)))?></div><?php if($fileUrl!==''||$link!==''):?><div class="actions"><?php if($fileUrl!==''):?><a class="file" href="<?=e($fileUrl)?>"><i class="bi bi-file-earmark-text"></i> Xem file đính kèm</a><?php endif;?><?php if($link!==''):?><a class="link" href="<?=e($link)?>" target="_blank" rel="noopener"><i class="bi bi-link-45deg"></i> Mở liên kết</a><?php endif;?></div><?php endif;?></main><script>function closePage(){if(history.length>1){history.back();return;}location.href=<?=json_encode($back,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;}</script></body></html>