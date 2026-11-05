<?php
/** Navigation only: existing forms, handlers, feature codes and records stay in their original pages. */
$pccmInputPages=['monhoc','lop','giaovien','kiemnhiem'];$pccmWorkPages=['them','danhsach','doicheo','rasoat','sua'];$pccmResultPages=['ketqua','thongke','xuat_bang'];
$pccmStage=in_array($current,$pccmInputPages,true)?'input':(in_array($current,$pccmWorkPages,true)?'work':(in_array($current,$pccmResultPages,true)?'result':''));
if($pccmStage==='')return;
$pccmTabs=$pccmStage==='input'?[
 ['Môn học','monhoc.php','cm.nhaplieu'],['Lớp','lop.php','cm.nhaplieu'],['Giáo viên','giaovien.php','cm.nhaplieu'],['Kiêm nhiệm','kiemnhiem.php','cm.nhaplieu']
]:($pccmStage==='work'?[
 ['Phân công','them.php','cm.pccm'],['Danh sách','danhsach.php','cm.pccm'],['Đổi chéo','doicheo.php','cm.pccm'],['Rà soát','rasoat.php','cm.pccm']
]:[['Phiên bản','ketqua.php','cm.tracuu'],['Thống kê','thongke.php','cm.thongke'],['Xuất kết quả','xuat_bang.php','cm.thongke']]);
$pccmSelectedVersion=null;$pccmVersionId='';
if($pccmStage==='result'){$candidate=$view_id??$vid??get_active_version_id();if(is_scalar($candidate)&&($pccmSelectedVersion=get_version((string)$candidate)))$pccmVersionId=(string)$candidate;}
?>
<link rel="stylesheet" href="<?=e(BASE_URL)?>assets/pccm-workflow.css?v=20261105-2">
<?php if ($pccmStage === 'input'): ?><link rel="stylesheet" href="<?=e(BASE_URL)?>assets/pccm-input.css?v=20261105-1"><?php endif; ?>
<section class="pccm-workflow" aria-label="<?=e(['input'=>'Nhập liệu','work'=>'Phân công','result'=>'Kết quả phiên bản'][$pccmStage])?>">
 <div class="pccm-workflow-title"><strong><?=e(['input'=>'Nhập liệu','work'=>'Phân công','result'=>'Kết quả phiên bản'][$pccmStage])?></strong><span><?=e(['input'=>'Danh mục dùng cho phân công chuyên môn','work'=>'Các thao tác luôn áp dụng phiên bản đang làm việc','result'=>'Chọn phiên bản để xem, thống kê và xuất kết quả'][$pccmStage])?></span></div>
 <nav class="pccm-workflow-tabs" aria-label="Các tab <?=e($pccmStage)?>">
 <?php foreach($pccmTabs as[$label,$route,$permission]):if(!$cmLayoutCan($permission))continue;$isCurrent=$current===pathinfo($route,PATHINFO_FILENAME)||($current==='sua'&&$route==='them.php');$query=$pccmStage==='result'&&$pccmVersionId!==''?'?'.http_build_query(['v'=>$pccmVersionId]):'';?>
 <a href="<?=e(BASE_URL.$route.$query)?>" <?=$isCurrent?'aria-current="page"':''?> <?=$pccmStage==='result'?'data-pccm-result-link':''?>><?=e($label)?></a>
 <?php endforeach;?>
 </nav>
 <?php if($pccmSelectedVersion):?><div class="pccm-viewing-version">Đang xem: <strong data-pccm-version-label><?=e($pccmSelectedVersion['name']??$pccmVersionId)?></strong>. Xem phiên bản khác không tự đổi phiên bản đang làm việc.</div><?php endif;?>
</section>
<?php if($current==='xuat_bang'):?>
<script>document.addEventListener('DOMContentLoaded',()=>{const select=document.getElementById('selVer');if(!select)return;select.addEventListener('change',()=>{document.querySelectorAll('[data-pccm-result-link]').forEach(link=>{const url=new URL(link.href,location.href);url.searchParams.set('v',select.value);link.href=url.href;});const label=document.querySelector('[data-pccm-version-label]');if(label)label.textContent=select.selectedOptions[0]?.textContent||select.value;});});</script>
<?php endif;?>
