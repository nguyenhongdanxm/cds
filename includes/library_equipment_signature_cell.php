<?php foreach(['borrow'=>'Ký mượn','return'=>'Ký trả'] as $phase=>$signLabel):
    $signed=!empty($loan[$phase.'_signature']);
    $reason=$equipmentSignReason;
    if($reason===''&&$phase==='return'&&empty($loan['returned_at']))$reason='Chưa xác nhận trả thiết bị';
?>
<div class="mb-2" data-equipment-signature-phase="<?=$phase?>">
<?php if($signed):?><span class="badge text-bg-success">Đã <?=e(mb_strtolower($signLabel,'UTF-8'))?></span>
<?php if(function_exists('cds_ts_data_uri')&&($signedImage=cds_ts_data_uri($loan[$phase.'_signature']))!==''):?><img src="<?=e($signedImage)?>" alt="<?=e($signLabel)?> đã xác nhận" style="display:block;width:90px;height:30px;object-fit:contain"><?php endif;?>
<?php elseif($reason===''):?>
<form method="post" class="d-inline" onsubmit="return confirm('<?=e($signLabel)?> dòng thiết bị này bằng chữ ký của bạn?')">
<input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="sign_equipment_row"><input type="hidden" name="id" value="<?=e($loan['id']??'')?>"><input type="hidden" name="phase" value="<?=$phase?>">
<button type="submit" class="btn btn-sm btn-outline-primary text-nowrap" data-equipment-sign-button="<?=$phase?>"><?=e($signLabel)?></button></form>
<?php else:?><button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" disabled data-equipment-sign-button="<?=$phase?>"><?=e($signLabel)?></button><small class="d-block text-muted" style="min-width:140px;max-width:210px"><?=e($reason)?></small><?php endif;?>
</div>
<?php endforeach;?>
