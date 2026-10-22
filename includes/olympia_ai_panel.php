<?php
if (!olympia_admin() || !$week) return;
require_once __DIR__ . '/olympia_ai.php';
$importToken = bin2hex(random_bytes(24));
$_SESSION['olympia_ai_imports'][$importToken] = (string)$weekId;
$_SESSION['olympia_ai_imports'] = array_slice($_SESSION['olympia_ai_imports'], -5, null, true);
?>
<details class="admin-collapse mt-3" id="olympia-ai-panel" data-csrf="<?=e($csrf)?>" data-week="<?=e($weekId)?>" data-token="<?=e($importToken)?>">
<summary>✨ AI tạo câu hỏi Olympia</summary>
<div class="admin-box">
<p>Dùng cấu hình trong <a href="<?=BASE_URL?>trolyai.php">Trợ lý AI</a>. Tạo bản nháp, sửa và chọn câu trước khi thêm vào tuần <strong><?=e($week['title'])?></strong>.</p>
<div class="row g-3" data-ai-options>
<div class="col-md-6"><label class="form-label" for="oly-ai-topic">Chủ đề / yêu cầu</label><textarea id="oly-ai-topic" class="form-control" maxlength="1000" rows="3" placeholder="Ví dụ: Kiến thức tổng hợp Toán, Văn, Lịch sử, Địa lí; câu hỏi ngắn, phù hợp khối lớp."></textarea></div>
<div class="col-md-6"><label class="form-label" for="oly-ai-reference">Nội dung tham khảo (không bắt buộc)</label><textarea id="oly-ai-reference" class="form-control" maxlength="4000" rows="3" placeholder="Dán nội dung cần bám sát. Nội dung này và một phần câu hỏi cũ được gửi tới nhà cung cấp AI đã cấu hình."></textarea></div>
<div class="col-md-2"><label for="oly-ai-grade" class="form-label">Khối</label><select id="oly-ai-grade" class="form-select"><?php foreach(range(6,12) as $g):?><option value="<?=$g?>">Khối <?=$g?></option><?php endforeach?><option value="all">Tất cả khối</option></select></div>
<div class="col-md-3"><label for="oly-ai-stage" class="form-label">Vòng thi</label><select id="oly-ai-stage" class="form-select"><?php foreach(olympia_ai_stages() as $stage):?><option><?=e($stage)?></option><?php endforeach?></select></div>
<div class="col-md-3"><label for="oly-ai-difficulty" class="form-label">Độ khó</label><select id="oly-ai-difficulty" class="form-select"><option value="easy">Dễ · nhận biết</option><option value="medium" selected>Vừa · thông hiểu</option><option value="hard">Khó · vận dụng</option></select></div>
<div class="col-md-2"><label for="oly-ai-count" class="form-label">Số câu (1–25)</label><input id="oly-ai-count" class="form-control" type="number" min="1" max="25" value="5"></div>
<div class="col-md-2"><label for="oly-ai-points" class="form-label">Điểm / câu</label><input id="oly-ai-points" class="form-control" type="number" min="0" max="1000" value="10"></div>
</div>
<div class="mt-3" data-ai-options>
<label><input type="checkbox" id="oly-ai-batch"> Tạo hàng loạt theo khối và phần thi</label>
<div id="oly-ai-batch-options" hidden class="border rounded p-3 mt-2">
<p>Chọn các khối cần tạo (mỗi khối có bộ câu hỏi riêng):</p>
<?php foreach(range(6,12) as $g):?><label class="me-3"><input type="checkbox" data-ai-grade value="<?=$g?>" checked> Khối <?=$g?></label><?php endforeach?>
<p class="mt-3">Cấu hình từng phần; số câu tính cho mỗi khối đã chọn. Tối đa 175 câu trong một bản nháp.</p>
<?php foreach(olympia_ai_stages() as $i=>$stage):?>
<div class="row g-2 align-items-center mb-2" data-ai-stage-row>
<div class="col-md-4"><label><input type="checkbox" data-ai-stage value="<?=e($stage)?>" checked> <?=e($stage)?></label></div>
<div class="col-md-2"><label class="d-block">Số câu<input aria-label="Số câu <?=e($stage)?>" data-ai-count type="number" class="form-control" min="1" max="25" value="<?=$i===0?10:5?>"></label></div>
<div class="col-md-2"><label class="d-block">Điểm / câu<input aria-label="Điểm <?=e($stage)?>" data-ai-points type="number" class="form-control" min="0" max="1000" value="10"></label></div>
<div class="col-md-4"><label class="d-block">Độ khó<select aria-label="Độ khó <?=e($stage)?>" data-ai-difficulty class="form-select"><option value="easy">Dễ · nhận biết</option><option value="medium" selected>Vừa · thông hiểu</option><option value="hard">Khó · vận dụng</option></select></label></div>
</div>
<?php endforeach?>
<p class="small text-muted mb-0">Mặc định 25 câu/khối: 10 Khởi động và 5 câu cho mỗi phần còn lại. AI được yêu cầu bám kiến thức từng khối; người biên soạn cần kiểm chứng mức độ phù hợp trước khi lưu. Tạo lần lượt có thể mất vài phút; giữ trang mở đến khi hoàn tất.</p>
</div></div>
<div class="d-flex flex-wrap gap-2 align-items-center mt-3"><button type="button" class="btn btn-primary" id="oly-ai-generate">✨ Tạo bản nháp</button><button type="button" class="btn btn-outline-secondary" id="oly-ai-stop" hidden>Dừng tạo</button><span id="oly-ai-status" role="status" aria-live="polite"></span></div>
<p class="small text-muted mt-2">Hệ thống chặn câu trùng nội dung sau khi chuẩn hóa chữ hoa/thường và dấu câu. Câu diễn đạt khác nhưng cùng kiến thức vẫn cần người biên soạn kiểm tra. AI có thể trả lời sai; hãy kiểm chứng đáp án trước khi sử dụng.</p>
<div id="oly-ai-drafts"></div>
<div id="oly-ai-save-area" hidden>
<label class="d-block my-3"><input type="checkbox" id="oly-ai-reviewed"> Tôi đã kiểm tra nội dung, đáp án và trùng kiến thức của các câu được chọn.</label>
<button type="button" id="oly-ai-save" class="btn btn-success">Thêm các câu đã chọn vào tuần này</button>
</div>
</div></details>
<script src="<?=BASE_URL?>assets/olympia-ai.js?v=20261022-batch-1" defer></script>
