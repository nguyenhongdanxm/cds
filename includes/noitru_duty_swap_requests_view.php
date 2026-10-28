<?php
if (!isset($dutySwapRequests, $dutyManagerDates, $canManageDuty) || !function_exists('noitru_duty_swap_can_decide')) { http_response_code(403); return; }
$dutyDecisionColumn = $canManageDuty || (bool)$dutyManagerDates;
?>
<section class="duty-panel">
  <div class="duty-panel-head"><div>
    <h6><?=$canManageDuty?'Yêu cầu đổi lịch cần xử lý':($dutyManagerDates?'Yêu cầu liên quan và thuộc ngày quản lý':'Yêu cầu liên quan đến tôi')?></h6>
    <div class="duty-help">Người được phân công quản lý trực được duyệt hoặc từ chối yêu cầu có ngày nguồn hoặc ngày đích thuộc ngày mình quản lý. Khi duyệt, lịch trực được cập nhật ngay.</div>
  </div></div>
  <div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Ngày gửi</th><th>Người đề nghị</th><th>Đổi lịch</th><th>Lý do</th><th>Trạng thái</th><?php if($dutyDecisionColumn):?><th>Xử lý</th><?php endif;?></tr></thead>
    <tbody><?php foreach($dutySwapRequests as $request): $canDecideRequest=noitru_duty_swap_can_decide($request,$dutyManagerDates); ?>
    <tr><td><?=e(!empty($request['created_at'])?date('d/m/Y H:i',strtotime($request['created_at'])):'')?></td>
      <td><strong><?=e($request['requester_name']??'')?></strong></td>
      <td><?=e($request['source_teacher_name']??'')?> · <?=e(date('d/m/Y',strtotime($request['source_date']??'')))?><br><i class="bi bi-arrow-down-up text-info"></i> <?=e($request['target_teacher_name']??'')?> · <?=e(date('d/m/Y',strtotime($request['target_date']??'')))?></td>
      <td><?=e($request['reason']??'')?><?php if(!empty($request['decision_note'])):?><div class="text-muted small">Phản hồi: <?=e($request['decision_note'])?></div><?php endif;?></td>
      <td><span class="duty-status <?=e($request['status']??'pending')?>"><?=e(['pending'=>'Chờ duyệt','approved'=>'Đã duyệt','rejected'=>'Từ chối'][$request['status']??'pending']??'Chờ duyệt')?></span></td>
      <?php if($dutyDecisionColumn):?><td>
        <?php if($canDecideRequest):?>
        <form method="post" action="<?=e(BASE_URL)?>noitru.php?tab=duty" class="d-flex gap-1 flex-wrap mb-1">
          <input type="hidden" name="action" value="duty_swap_request_decide">
          <input type="hidden" name="duty_swap_csrf" value="<?=e(noitru_duty_swap_csrf_token())?>">
          <input type="hidden" name="request_id" value="<?=e($request['id']??'')?>">
          <input type="hidden" name="month" value="<?=e($dutyMonth)?>">
          <input type="text" name="decision_note" class="form-control form-control-sm" placeholder="Ghi chú">
          <button name="decision" value="approved" class="btn btn-sm btn-success" onclick="return confirm('Duyệt và cập nhật lịch trực?')">Duyệt</button>
          <button name="decision" value="rejected" class="btn btn-sm btn-outline-danger">Từ chối</button>
        </form>
        <?php elseif(($request['status']??'')!=='pending'):?>
        <small class="d-block mb-1"><?=e($request['decision_by']??'')?><?=!empty($request['decided_at'])?' · '.e(date('d/m/Y H:i',strtotime($request['decided_at']))):''?></small>
        <?php else:?><small class="text-muted">Không thuộc phạm vi duyệt</small><?php endif;?>
        <?php if($canManageDuty):?>
        <form method="post" onsubmit="return confirm('Xóa vĩnh viễn yêu cầu đổi lịch trực này?')"><input type="hidden" name="action" value="duty_swap_request_delete"><input type="hidden" name="request_id" value="<?=e($request['id']??'')?>"><input type="hidden" name="month" value="<?=e($dutyMonth)?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Xóa</button></form>
        <?php endif;?>
      </td><?php endif;?>
    </tr><?php endforeach;?>
    <?php if(!$dutySwapRequests):?><tr><td colspan="<?=$dutyDecisionColumn?6:5?>" class="text-center text-muted py-4">Chưa có yêu cầu đổi lịch.</td></tr><?php endif;?></tbody>
  </table></div>
</section>
