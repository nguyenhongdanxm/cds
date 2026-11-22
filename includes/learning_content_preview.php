<?php
$preview=lc_preview($row);
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; frame-src 'self' https:; media-src 'self' https:; img-src 'self' https: data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'");
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($row['title']??'Xem nội dung')?></title><style>html,body{margin:0;height:100%;font:14px/1.5 system-ui;background:#f4f7fb;color:#456}main{height:calc(100% - 48px);display:grid;place-items:center;overflow:auto}iframe{border:0;width:100%;height:100%;background:#fff}video{width:100%;height:100%;background:#111}img{max-width:100%;max-height:100%;object-fit:contain}p{margin:0;padding:10px 16px;text-align:center;font-size:12px}main>p{font-size:16px}</style></head><body><main>
<?php if(empty($preview['src'])||$preview['mode']==='unavailable'):?><p>Nội dung này chưa hỗ trợ xem trực tiếp.</p>
<?php elseif($preview['mode']==='video'):?><video controls playsinline preload="metadata" src="<?=e($preview['src'])?>">Trình duyệt chưa hỗ trợ video này.</video>
<?php elseif($preview['mode']==='image'):?><img src="<?=e($preview['src'])?>" alt="<?=e($row['title']??'Hình ảnh')?>">
<?php else:?><iframe src="<?=e($preview['src'])?>" title="<?=e($row['title']??'Nội dung học liệu')?>" sandbox="allow-scripts allow-forms allow-modals allow-presentation<?=!empty($preview['trusted'])?' allow-same-origin':''?>" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe><?php endif;?>
</main><p>Nội dung hiển thị ngay trong trang. Nếu nguồn chặn nhúng hoặc giới hạn quyền xem, hãy chọn nguồn hỗ trợ nhúng hoặc tải tệp lên.</p></body></html>
