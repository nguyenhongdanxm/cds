<?php
$aiFingerprint=ttb_ai_fingerprint($data,$assignments,$plan);
$aiPreview=$_SESSION['tte_ai_preview']??null;
if(($aiPreview['workspace']??'')!==$data['_workspace_id']||($aiPreview['plan_id']??'')!==$plan['id'])$aiPreview=null;
$aiStale=$aiPreview&&(($aiPreview['fingerprint']??'')!==$aiFingerprint||time()-(int)($aiPreview['created_at']??0)>900);
$aiNotice=(string)($_SESSION['tte_ai_notice']??'');unset($_SESSION['tte_ai_notice']);
$aiUrl='/tkb_editor.php?'.http_build_query(['mode'=>$mode,'class'=>$class,'teacher'=>$teacher,'assistant'=>'1']);
?>
<div class="side-body d-none" id="sideAi" style="overflow-wrap:anywhere" data-open="<?=isset($_GET['assistant'])?'1':'0'?>">
  <h3 class="h6"><i class="bi bi-stars"></i> Trợ lý AI chỉnh TKB</h3>
  <p class="small text-muted">Đang mở: <strong><?=e($plan['name']??'Phương án')?></strong>. AI đề xuất, thầy/cô xem trước và chọn áp dụng vào bản nháp.</p>
  <?php if($aiNotice!==''):?><div class="alert alert-info small" role="status"><?=e($aiNotice)?></div><?php endif;?>
  <form method="post" action="<?=e($aiUrl)?>" id="editorAiCommandForm">
    <input type="hidden" name="action" value="editor_ai_preview">
    <input type="hidden" name="csrf" value="<?=e($csrf)?>">
    <input type="hidden" name="workspace_id" value="<?=e($data['_workspace_id'])?>">
    <input type="hidden" name="plan_id" value="<?=e($plan['id'])?>">
    <input type="hidden" name="fingerprint" value="<?=e($aiFingerprint)?>">
    <label for="editorAiCommand" class="form-label small fw-bold">Yêu cầu bằng tiếng Việt</label>
    <textarea id="editorAiCommand" name="ai_command" class="form-control form-control-sm" rows="4" maxlength="500" required placeholder="Chuyển tiết 2 Toán lớp 10A sáng thứ 3 sang tiết 4 chiều thứ 5"><?=e($aiPreview['request']??'')?></textarea>
    <div class="small text-muted my-2">Mỗi lần một yêu cầu: chuyển tiết, đổi hai tiết, chuyển buổi, xếp tiết còn thiếu, giảm tiết trống hoặc kiểm tra lỗi. Ghi rõ lớp/giáo viên, thứ, buổi và số tiết. Nếu chưa rõ, hệ thống yêu cầu bổ sung.</div>
    <button class="btn btn-primary btn-sm" type="submit">Phân tích và xem trước</button>
    <button class="btn btn-outline-secondary btn-sm" type="button" id="editorAiCheck">Kiểm tra lỗi</button>
    <div class="small text-muted mt-2" id="editorAiProgress" role="status"></div>
  </form>
  <?php if($aiPreview):?>
    <hr><h4 class="h6">Kết quả đề xuất</h4>
    <p class="small"><?=e($aiPreview['message'])?></p>
    <?php if($aiStale):?><div class="alert alert-warning small">Lịch/ràng buộc đã thay đổi hoặc đề xuất đã hết hạn. Hãy phân tích lại.</div><?php endif;?>
    <?php if(!empty($aiPreview['changes'])):?>
      <p class="small fw-bold"><?=count($aiPreview['changes'])?> tiết thay đổi · <?=count($aiPreview['candidate']['unplaced']??[])?> tiết còn chưa xếp</p>
      <ol class="small ps-3"><?php foreach($aiPreview['changes']as$change):?><li class="mb-2"><strong><?=e($change['lesson'])?></strong><div>Từ: <?=e($change['from'])?></div><div class="text-primary">Đến: <?=e($change['to'])?></div></li><?php endforeach;?></ol>
      <form method="post" action="<?=e($aiUrl)?>" id="editorAiApplyForm">
        <input type="hidden" name="action" value="editor_ai_apply"><input type="hidden" name="csrf" value="<?=e($csrf)?>">
        <input type="hidden" name="workspace_id" value="<?=e($data['_workspace_id'])?>"><input type="hidden" name="plan_id" value="<?=e($plan['id'])?>">
        <input type="hidden" name="fingerprint" value="<?=e($aiPreview['fingerprint'])?>"><input type="hidden" name="preview_token" value="<?=e($aiPreview['token'])?>">
        <button type="submit" class="btn btn-success btn-sm" <?=$aiStale?'disabled':''?>>Áp dụng <?=count($aiPreview['changes'])?> thay đổi</button>
      </form>
    <?php endif;?>
    <details class="small mt-3" <?=empty($aiPreview['changes'])?'open':''?>><summary><?=count($aiPreview['errors'])?> vấn đề còn trong lịch</summary><?php if($aiPreview['errors']):?><ul class="ps-3 mt-2"><?php foreach($aiPreview['errors']as$error):?><li><?=e($error)?></li><?php endforeach;?></ul><?php else:?><p class="text-success mt-2">Chưa phát hiện vi phạm trong các ràng buộc đã khai báo.</p><?php endif;?></details>
  <?php endif;?>
  <p class="small text-muted mt-3">Tiết cố định, ô bận, phòng và buổi không học được kiểm tra bằng bộ xếp hiện có. Muốn thêm ngày/buổi nghỉ, khai báo tại mục Ràng buộc rồi yêu cầu AI xếp lại. Bản nháp chỉ được công bố bằng thao tác Công bố riêng.</p>
</div>
