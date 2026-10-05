<?php
$preview=$_SESSION['ttb_ai_preview']??null;
$currentFingerprint=$activePlan?ttb_ai_fingerprint($data,$assignments,$activePlan):'';
$stale=$preview&&($preview['fingerprint']??'')!==$currentFingerprint;
?>
<section class="card p-3 p-md-4 mb-3" style="border-top:4px solid #7c3aed">
  <h4><i class="bi bi-stars text-primary"></i> Trợ lý AI xếp TKB</h4>
  <p class="text-muted">Kiểm tra lỗi, đề xuất đổi tiết và hỗ trợ sắp xếp. Xem trước từng thay đổi rồi áp dụng vào bản nháp.</p>
  <?php if(!$activePlan): ?><div class="alert alert-warning">Hãy tạo hoặc chọn phương án tại mục Phương án trước.</div><?php else: ?>
  <div class="alert alert-light border">Đang phân tích: <strong><?=e($activePlan['name']??'Phương án hiện tại')?></strong>. Tiết đã khóa và ràng buộc cố định được giữ nguyên.</div>
  <form method="post" class="row g-3" id="ttbAiForm">
    <input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="ttb_ai_analyze">
    <div class="col-md-5"><label class="form-label fw-bold">Hỗ trợ</label><select name="ai_mode" class="form-select"><option value="check">Kiểm tra lỗi và giải thích</option><option value="suggest">Đề xuất chuyển / đổi / xếp tiết</option><?php if(empty($activePlan['manual_mode'])): ?><option value="rearrange">Tìm phương án sắp xếp toàn bộ</option><?php endif; ?></select><div class="form-text">Sắp xếp toàn bộ có thể mất khoảng 15–45 giây.</div></div>
    <div class="col-md-7"><label class="form-label fw-bold">Tiết cần đề xuất</label><select name="activity_id" class="form-select"><option value="">Tự tìm tiết phù hợp / ưu tiên tiết chưa xếp</option><?php foreach($activePlan['unplaced']??[]as $entry): ?><option value="<?=e($entry['id'])?>">Chưa xếp · <?=e($entry['subject'].' · '.implode('+',$entry['classes']).' · '.$entry['teacher'])?></option><?php endforeach; ?><?php foreach($activePlan['entries']??[]as $entry): if(!empty($entry['locked']))continue; ?><option value="<?=e($entry['activity_id'])?>"><?=e($entry['subject'].' · '.implode('+',$entry['classes']).' · '.$entry['teacher'].' · '.ttb_ai_slot_text($entry))?></option><?php endforeach; ?></select><div class="form-text">Áp dụng cho chế độ đề xuất tiết. Bộ xếp ưu tiên giảm tiết chưa xếp, lỗi và khoảng trống.</div></div>
    <div class="col-12"><label class="form-label fw-bold">Yêu cầu để AI đánh giá phương án</label><textarea name="ai_request" maxlength="1500" class="form-control" rows="2" placeholder="Ví dụ: Ưu tiên phương án ít ảnh hưởng các lớp khác, giải thích vì sao môn này chưa xếp được."><?=e($preview['request']??'')?></textarea><div class="form-text">Muốn bắt buộc ngày nghỉ, buổi học hoặc vị trí tiết: khai báo ở Ràng buộc rồi phân tích lại.</div></div>
    <div class="col-12"><button class="btn btn-primary" id="ttbAiSubmit"><i class="bi bi-stars"></i> Phân tích và xem phương án</button><span class="small text-muted ms-2" id="ttbAiProgress" role="status"></span></div>
  </form>
  <?php endif; ?>
</section>
<?php if($preview): ?>
<?php if($stale): ?><div class="alert alert-warning">TKB hoặc ràng buộc đã thay đổi. Kết quả dưới đây là bản trước; hãy phân tích lại để áp dụng.</div><?php endif; ?>
<section class="card p-3 mb-3"><h5>Kết quả kiểm tra</h5><p><strong><?=count($preview['errors']??[])?></strong> vấn đề · <strong><?=(int)($preview['unplaced']??0)?></strong> tiết chưa xếp</p><?php if(empty($preview['errors'])): ?><div class="text-success">Chưa phát hiện vi phạm trong các ràng buộc đang khai báo.</div><?php else: ?><ul><?php foreach($preview['errors']as $error): ?><li><?=e($error)?></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php if(!empty($preview['message'])): ?><div class="alert alert-warning"><?=e($preview['message'])?> Kết quả kiểm tra và phương án thuật toán vẫn có thể xem bên dưới.</div><?php endif; ?>
<?php if(!empty($preview['answer'])): ?><section class="card p-3 mb-3"><h5><i class="bi bi-chat-square-text"></i> AI giải thích và đề xuất</h5><div style="white-space:pre-wrap;overflow-wrap:anywhere"><?=e($preview['answer'])?></div></section><?php endif; ?>
<?php foreach($preview['candidates']??[]as $i=>$candidate): ?>
<section class="card p-3 mb-3"><h5>Phương án <?=$i+1?> · <?=count($candidate['changes'])?> tiết thay đổi</h5><p class="small text-muted">Còn <?=count($candidate['plan']['unplaced']??[])?> tiết chưa xếp · <?=count($candidate['errors'])?> vấn đề. Đã kiểm tra không phát sinh vi phạm mới.</p>
  <div class="table-responsive" style="max-height:420px"><table class="table table-sm table-striped align-middle"><thead><tr><th>Môn · Lớp · Giáo viên</th><th>Trước</th><th>Sau</th></tr></thead><tbody><?php foreach($candidate['changes']as $change): ?><tr><td><?=e($change['lesson'])?></td><td><?=e($change['from'])?></td><td class="text-primary fw-semibold"><?=e($change['to'])?></td></tr><?php endforeach; ?></tbody></table></div>
  <form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="ttb_ai_apply"><input type="hidden" name="preview_token" value="<?=e($preview['token']??'')?>"><input type="hidden" name="candidate" value="<?=$i?>"><button class="btn btn-success" <?=$stale?'disabled':''?>>Áp dụng phương án <?=$i+1?> vào bản nháp</button></form>
</section>
<?php endforeach; ?>
<?php if(empty($preview['candidates'])): ?><div class="small text-muted mb-3">Chọn Đề xuất chuyển/đổi/xếp tiết để tìm phương án. Nếu không tìm được, hãy chọn một tiết cụ thể hoặc kiểm tra các ràng buộc đang khóa.</div><?php endif; ?>
<?php endif; ?>
<script>document.getElementById('ttbAiForm')?.addEventListener('submit',function(){document.getElementById('ttbAiSubmit').disabled=true;document.getElementById('ttbAiProgress').textContent='Đang kiểm tra lịch và chờ AI đánh giá…';});</script>
