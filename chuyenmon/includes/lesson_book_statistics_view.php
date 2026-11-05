<?php
// Navigation never carries a load flag: changing a tab cannot trigger another report.
$statsSections=['indicators'=>'Ký sổ','attendance'=>'Sĩ số học sinh','scores'=>'Xếp loại','audit'=>'Nhật ký'];
$statsSection=(string)($_GET['stats_section']??'indicators');
if(!isset($statsSections[$statsSection]))$statsSection='indicators';
$statsLoad=($_GET['stats_load']??'')==='1';
?>
<nav class="lb-tabs" aria-label="Các mục thống kê">
<?php foreach($statsSections as$key=>$label):?><a class="<?=$statsSection===$key?'active':''?>" href="<?=e(BASE_URL.'sodaubai.php?'.http_build_query(['tab'=>'progress','week'=>$weekKey,'stats_section'=>$key]))?>"><?=e($label)?></a><?php endforeach;?>
</nav>
<?php if($statsSection==='audit'):require __DIR__.'/sodaubai_sign_audit.php';else:require __DIR__.'/sodaubai_progress.php';endif;?>
