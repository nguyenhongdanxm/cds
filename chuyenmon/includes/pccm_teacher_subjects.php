<?php
/** Presentation only: one subject heading per teacher, all original assignment IDs retained. */
foreach($subjectGroups as$boardSubject=>$subjectRows): ?>
<div class="pccm-subject-row" data-subject-row="<?=e($boardSubject)?>"><strong class="pccm-subject-name"><?=e($boardSubject)?></strong><div class="pccm-class-chips">
<?php foreach($subjectRows as$a): $class=(string)$a['class']; ?>
<?php if(!empty($a['program_assignment'])):
$rangeLabels=[];foreach((array)($programConfigs[$class]['ranges']??[])as$r)if($r['teacher']===$a['teacher'])$rangeLabels[]=$r['from'].'–'.$r['to'];
?>
<a class="chip pccm-program-chip" data-assignment-class="<?=e($class)?>" data-grade="<?=e(get_grade($class))?>" href="<?=e(BASE_URL)?>them.php?program=1&amp;class=<?=urlencode($class)?>" title="Sửa phân công GDĐP lớp <?=e($class)?>"><?=e($class)?> · Tiết <?=e(implode(', ',$rangeLabels))?> <small>(<?=e($a['annual_periods'])?> tiết/năm)</small></a>
<?php elseif(!empty($a['is_manual'])): ?>
<button type="button" class="chip pccm-manual-chip" data-assignment-class="<?=e($class)?>" data-grade="<?=e(get_grade($class))?>" data-pccm-manual="<?=e($t)?>" title="Mở quản lý phân công thủ công"><?=e($class)?> · <?=e($a['periods'])?> tiết <small>Thủ công</small></button>
<?php else: ?>
<span class="chip chip-clickable" draggable="true" data-assignment-class="<?=e($class)?>" data-grade="<?=e(get_grade($class))?>" data-id="<?=e($a['id'])?>" data-type="day" data-teacher="<?=e($a['teacher'])?>" data-subject="<?=e($a['subject'])?>" data-class="<?=e($class)?>" data-periods="<?=e($a['periods'])?>" data-note="<?=e($a['note']??'')?>" ondragstart="onDragChip(event)" onclick="openChipModal(this)"><?=e($class)?> · <?=e($a['periods'])?> tiết</span>
<?php endif; endforeach; ?>
<button type="button" class="pccm-inline-add" data-pccm-assign="<?=e($t)?>" data-pccm-subject="<?=e($boardSubject)?>" data-pccm-mode="<?=gdp_subject($boardSubject)?'program':'day'?>" aria-label="Gán thêm lớp môn <?=e($boardSubject)?>">+ Lớp</button>
</div></div>
<?php endforeach; ?>
