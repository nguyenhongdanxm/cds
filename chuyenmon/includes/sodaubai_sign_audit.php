<?php
// Do not read the audit store until the user explicitly submits a lookup.
$auditLoad=($_GET['audit_load']??'')==='1';$auditRows=[];$auditTotal=0;
$auditQuery=trim((string)($_GET['audit_q']??''));$auditFrom=trim((string)($_GET['audit_from']??''));$auditTo=trim((string)($_GET['audit_to']??''));$auditError='';
$auditZone=new DateTimeZone('Asia/Ho_Chi_Minh');
foreach([$auditFrom,$auditTo] as $auditDate){if($auditDate==='')continue;$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$auditDate,$auditZone);if(!$parsed||$parsed->format('Y-m-d')!==$auditDate)$auditError='Ngày tra cứu không hợp lệ.';}
if($auditFrom!==''&&$auditTo!==''&&$auditFrom>$auditTo)$auditError='Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.';
if($auditLoad&&$auditError===''){
 $query=lb_norm($auditQuery);
 foreach(lb_rows(LB_AUDIT_FILE) as $entry){
  if(!in_array($entry['action']??'',['sign_record','save_and_sign'],true))continue;
  if(!lb_is_admin()&&!lb_same(lb_teacher_name(),(string)($entry['by']??'')))continue;
  try{$stamp=new DateTimeImmutable((string)($entry['at']??''));$entry['_time']=$stamp->setTimezone($auditZone)->format('d/m/Y H:i:s');$day=$stamp->setTimezone($auditZone)->format('Y-m-d');}catch(Throwable $e){$entry['_time']=(string)($entry['at']??'');$day='';}
  if(($auditFrom!==''&&$day<$auditFrom)||($auditTo!==''&&$day>$auditTo))continue;
  if($query!==''&&strpos(lb_norm(json_encode($entry,JSON_UNESCAPED_UNICODE)),$query)===false)continue;
  $auditTotal++;if(count($auditRows)<200)$auditRows[]=$entry;
 }
}
?>
<section class="lb-card" id="sign-audit">
<h2 class="h5 mb-1"><i class="bi bi-clock-history"></i> Tra nhật ký ký sổ</h2>
<p class="lb-note">Chỉ tải nhật ký khi bấm Tra cứu. Giáo viên xem nhật ký của mình; quản trị xem toàn trường. Ngày lọc là ngày thực hiện ký, theo giờ Việt Nam.</p>
<form method="get" action="<?=e(BASE_URL.'sodaubai.php')?>#sign-audit" class="row g-2 align-items-end">
<input type="hidden" name="tab" value="<?=e($tab)?>"><input type="hidden" name="week" value="<?=e($weekKey)?>"><input type="hidden" name="audit_load" value="1">
<?php if($tab==='progress'):foreach(['stats_range','stats_week','stats_from','stats_to','stats_teacher','stats_subject','stats_class','stats_completion']as$filter):if(!isset($_GET[$filter]))continue;?><input type="hidden" name="<?=e($filter)?>" value="<?=e((string)$_GET[$filter])?>"><?php endforeach;endif;?>
<div class="col-md-3"><label class="form-label">Ngày ký từ</label><input type="date" class="form-control" name="audit_from" value="<?=e($auditFrom)?>"></div>
<div class="col-md-3"><label class="form-label">Đến ngày</label><input type="date" class="form-control" name="audit_to" value="<?=e($auditTo)?>"></div>
<div class="col-md-4"><label class="form-label">Giáo viên, lớp, môn hoặc ngày dạy</label><input class="form-control" name="audit_q" value="<?=e($auditQuery)?>" placeholder="Ví dụ: 7B hoặc 2026-10-09"></div>
<div class="col-md-2"><button class="btn btn-primary w-100"><i class="bi bi-search"></i> Tra cứu</button></div>
</form>
<?php if($auditLoad):?>
<?php if($auditError!==''):?><div class="alert alert-danger mt-3"><?=e($auditError)?></div><?php else:?>
<div class="small text-muted mt-3"><?=count($auditRows)?> / <?=$auditTotal?> lượt ký phù hợp<?=($auditTotal>200?' · Chỉ hiện 200 lượt gần nhất; hãy thu hẹp bộ lọc.':'')?>. Nhật ký hiện lưu tối đa 5.000 thao tác gần nhất.</div>
<div class="table-responsive mt-2"><table class="table table-sm table-bordered table-hover align-middle"><thead><tr><th>Thời điểm ký</th><th>Người ký</th><th>Ngày dạy</th><th>Lớp</th><th>Môn</th><th>Tiết TKB</th></tr></thead><tbody>
<?php foreach($auditRows as$entry):?><tr><td class="text-nowrap"><?=e($entry['_time'])?></td><td><?=e($entry['signed_by']??$entry['by']??'')?></td><td><?=e($entry['date']??'')?></td><td><?=e($entry['class']??'')?></td><td><?=e($entry['subject']??'')?></td><td><?=e($entry['period']??'')?></td></tr><?php endforeach;if(!$auditRows):?><tr><td colspan="6" class="lb-empty">Không có lượt ký phù hợp.</td></tr><?php endif;?>
</tbody></table></div><?php endif;endif;?>
</section>
