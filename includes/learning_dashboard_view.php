<?php /* Dashboard presentation only; data and permission decisions come from the model. */ ?>
<div class="lh-dashboard">
    <header class="lh-dashboard-heading"><span>KHO TRI THỨC NHÀ TRƯỜNG</span><h1>Khám phá không gian dạy và học</h1><p>Học liệu, kiểm tra và tiện ích được sắp xếp theo từng ngăn để dễ tìm, dễ sử dụng.</p></header>
    <nav class="lh-dashboard-shortcuts" aria-label="Các ngăn nội dung">
        <?php foreach($dashboard as$key=>$section):?><a href="#lh-section-<?=e($key)?>" class="lh-shortcut lh-tone-<?=e($key)?>"><span class="lh-section-icon"><i class="bi <?=e($section['icon'])?>" aria-hidden="true"></i></span><span><strong><?=$section['unavailable']?'—':(int)$section['count']?></strong><span><?=e($section['title'])?></span></span><i class="bi bi-arrow-down-right" aria-hidden="true"></i></a><?php endforeach;?>
    </nav>
    <?php if(!$dashboard):?><div class="lh-dashboard-empty">Tài khoản chưa được cấp quyền xem các ngăn nội dung. Vui lòng liên hệ quản trị.</div><?php endif;?>
    <?php foreach($dashboard as$key=>$section):?>
    <section id="lh-section-<?=e($key)?>" class="lh-dashboard-section lh-tone-<?=e($key)?>" aria-labelledby="lh-title-<?=e($key)?>">
        <header class="lh-section-heading"><div class="lh-section-intro"><span class="lh-section-icon"><i class="bi <?=e($section['icon'])?>" aria-hidden="true"></i></span><div><h2 id="lh-title-<?=e($key)?>"><?=e($section['title'])?> <span class="lh-section-count"><?=$section['unavailable']?'—':(int)$section['count']?></span></h2><p><?=e($section['description'])?></p></div></div><a class="lh-view-all" href="?tab=<?=e($key)?>">Xem tất cả <i class="bi bi-arrow-right" aria-hidden="true"></i></a></header>
        <?php if(!empty($section['categories'])):?><nav class="lh-category-links" aria-label="Chuyên mục <?=e($section['title'])?>"><?php foreach($section['categories']as$categoryKey=>$category):?><a href="<?=e(lhc_url($key,$categoryKey))?>"><?=e($category['name'])?></a><?php endforeach;?></nav><?php endif;?>
        <?php if(!$section['items']):?><div class="lh-dashboard-empty"><i class="bi <?=e($section['icon'])?>" aria-hidden="true"></i><p><?=$section['unavailable']?'Chưa tải được danh sách tiện ích. Vui lòng thử lại sau.':($key==='resources'?'Chưa có học liệu.':($key==='exams'?'Chưa có đề kiểm tra hoặc kỳ thi.':($key==='games'?'Chưa có tiện ích được bật.':'Chưa có liên kết.')))?></p></div>
        <?php else:?>
        <div class="lh-content-grid <?=$key==='games'?'lh-games-preview':''?>">
        <?php foreach($section['items']as$row):?>
            <?php if($key==='games'):?>
            <a class="lh-preview-game" data-game-key="<?=e($row['key'])?>" href="<?=e(BASE_URL.$row['url'])?>"><img src="<?=e(BASE_URL.'assets/game-covers/illustrated-v3/'.$row['key'].'.webp?v=20261110-5')?>" width="720" height="405" loading="lazy" decoding="async" alt=""><div><h3><?=e($row['name'])?></h3><p><?=e($row['description'])?></p><span>Mở tiện ích <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span></div></a>
            <?php elseif($key==='links'):?>
            <a class="lh-preview-item lh-preview-link" href="<?=e(lh_dashboard_safe_url((string)($row['url']??'')))?>" target="_blank" rel="noopener noreferrer"><span class="lh-preview-icon"><i class="bi <?=e($row['icon']??'bi-link-45deg')?>" aria-hidden="true"></i></span><div><h3><?=e($row['title']??'Liên kết')?></h3><span class="lh-preview-action">Truy cập <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></span></div></a>
            <?php else:?>
            <a class="lh-preview-item lh-with-cover" href="<?=e(lh_dashboard_safe_url(lh_item_url($row)))?>" data-learning-preview data-title="<?=e($row['title']??'Xem nội dung')?>"><?=lt_cover($row)?><div class="lh-thumbnail-copy"><div class="lh-preview-copy"><div class="lh-preview-badges"><?php if(empty($row['approved'])):?><span class="lh-pending">Chờ duyệt</span><?php elseif(!empty($row['featured'])):?><span class="lh-featured"><i class="bi bi-star-fill" aria-hidden="true"></i> Nổi bật</span><?php endif;?></div><h3><?=e($row['title']??'')?></h3><p><?=e($row['kind']??'Tài liệu')?><?php if(($row['grade']??'')!==''):?> · <?=($row['grade']==='Toàn trường')?'Toàn trường':'Khối '.e($row['grade'])?><?php endif;?></p><span class="lh-preview-author"><i class="bi bi-person" aria-hidden="true"></i> <?=e($row['author']??'')?></span></div><i class="bi bi-arrow-up-right lh-preview-corner" aria-hidden="true"></i></div></a>
            <?php endif;?>
        <?php endforeach;?>
        </div>
        <?php if($section['count']>count($section['items'])):?><p class="lh-section-footnote">Đang hiển thị <?=count($section['items'])?> / <?=(int)$section['count']?> mục. Chọn “Xem tất cả” để xem đầy đủ.</p><?php endif;?>
        <?php endif;?>
    </section>
    <?php endforeach;?>
</div>
