<?php
// Navigation never carries a load flag: changing a tab cannot trigger another report.
$statsSections=['scores'=>'Bảng quy đổi và điểm sổ đầu bài','indicators'=>'Chỉ số ký sổ theo đối tượng','attendance'=>'Sĩ số · học sinh vắng','audit'=>'Nhật ký ký sổ'];
$statsSection=(string)($_GET['stats_section']??'scores');
if(!isset($statsSections[$statsSection]))$statsSection='scores';
$statsLoad=($_GET['stats_load']??'')==='1';
?>
<nav class="lb-tabs" aria-label="Các mục thống kê">
<?php foreach($statsSections as$key=>$label):?><a class="<?=$statsSection===$key?'active':''?>" href="<?=e(BASE_URL.'sodaubai.php?'.http_build_query(['tab'=>'progress','week'=>$weekKey,'stats_section'=>$key]))?>"><?=e($label)?></a><?php endforeach;?>
</nav>
<?php if($statsSection==='audit'):require __DIR__.'/sodaubai_sign_audit.php';else:require __DIR__.'/sodaubai_progress.php';endif;?>
