<?php
$gdpSource=gdp_builder_configs();$gdpConfigs=$gdpSource['configs'];
if($gdpConfigs):
$gdpCtx=gdp_week_context($data);$gdpRecords=[];
if($gdpCtx['start']!==''){
    require_once __DIR__.'/database_lesson_book.php';
    if(cds_lb_read_effective()){try{$gdpRecords=cds_lb_sql_range($gdpCtx['year_start'],date('Y-m-d',strtotime($gdpCtx['start'].' -1 day')));}catch(Throwable $e){$gdpRecords=(array)load_json(ttb_pccm_dir().'/lesson_book_records.json',[]);}}
    else $gdpRecords=(array)load_json(ttb_pccm_dir().'/lesson_book_records.json',[]);
}
?>
<section class="card p-3 mb-3"><div class="d-flex flex-wrap justify-content-between gap-2"><h5 class="mb-1">GDĐP · Tiết sẽ dạy trong tuần</h5><a href="/chuyenmon/them.php?program=1">Phân công tiết 1–35</a></div>
<p class="small text-muted mb-2">Đề xuất theo tiết đã dạy và ký trước tuần này, không lấy số tuần làm số tiết. Kiểm tra sổ đã cập nhật; có thể điều chỉnh rồi xác nhận. 36 = đã hoàn thành 35 tiết.</p>
<?php if($gdpCtx['start']===''): ?><div class="alert alert-warning mb-0">Chọn tuần áp dụng trong Phiên bản làm việc trước khi xác nhận GDĐP.</div><?php else: ?>
<form method="post"><input type="hidden" name="action" value="program_week_confirm"><input type="hidden" name="program_workspace" value="<?=e($data['_workspace_id']??'')?>"><input type="hidden" name="program_week_key" value="<?=e($gdpCtx['key'])?>"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="program_version" value="<?=e($gdpSource['version'])?>"><input type="hidden" name="program_hash" value="<?=e(hash('sha256',json_encode($gdpConfigs)))?>">
<div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Lớp</th><th>Đề xuất từ sổ</th><th>Tiết sẽ dạy</th><th>Giáo viên phụ trách</th></tr></thead><tbody>
<?php foreach($gdpConfigs as$c=>$config): $suggest=gdp_next($gdpRecords,$c,$gdpCtx['start'],$gdpCtx['year_start']);$saved=$data['program_week']??[];$pick=(($saved['week_key']??'')===$gdpCtx['key']&&($saved['version_id']??'')===$gdpSource['version'])?($saved['classes'][$c]??[]):[];$value=(int)($pick['period']??$suggest); ?>
<tr><td><?=e($c)?></td><td><?=$suggest>35?'Hoàn thành':$suggest?></td><td><input class="form-control form-control-sm" style="width:85px" type="number" min="1" max="36" step="1" required name="program_period[<?=e($c)?>]" value="<?=$value?>" aria-label="Tiết chương trình lớp <?=e($c)?>" data-gdp-ranges="<?=e(json_encode($config['ranges'],JSON_UNESCAPED_UNICODE))?>"></td><td data-gdp-teacher><?=e($value>35?'Hoàn thành':gdp_teacher($config,$value))?><?php if($value!==$suggest): ?> <span class="text-warning">· khác tiến độ đề xuất</span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<button class="btn btn-primary btn-sm">Xác nhận tiết và giáo viên tuần <?=e($gdpCtx['key'])?></button>
<?php if(!empty($GLOBALS['gdp_builder_error'])): ?><span class="text-danger small ms-2"><?=e($GLOBALS['gdp_builder_error'])?></span><?php else: ?><span class="small text-success ms-2">Đã xác nhận. Xếp lại nếu cần cập nhật phương án.</span><?php endif; ?>
</form>
<?php endif; ?></section>
<script>document.querySelectorAll('[data-gdp-ranges]').forEach(input=>input.addEventListener('input',()=>{const p=Number(input.value),ranges=JSON.parse(input.dataset.gdpRanges),found=ranges.find(r=>p>=r.from&&p<=r.to);input.closest('tr').querySelector('[data-gdp-teacher]').textContent=p===36?'Hoàn thành':found?found.teacher:'Chưa phân công';}));</script>
<?php endif; ?>
