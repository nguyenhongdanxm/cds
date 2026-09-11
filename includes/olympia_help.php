<?php
$olympiaStages = [
    'Khởi động' => ['points'=>10, 'note'=>'Câu hỏi ngắn, rõ ý; ưu tiên kiến thức nhận biết và trả lời nhanh.'],
    'Vượt chướng ngại vật' => ['points'=>20, 'note'=>'Mỗi câu là một gợi ý liên quan đến từ khóa hoặc chủ đề chung.'],
    'Tăng tốc' => ['points'=>30, 'note'=>'Câu hỏi cần suy luận nhanh; có thể dùng dữ kiện, hình ảnh hoặc tình huống.'],
    'Về đích' => ['points'=>40, 'note'=>'Câu hỏi vận dụng, phân hóa; ghi rõ đáp án và cách chấp nhận đáp án tương đương.'],
];
function olympia_template_uri(string $stage, int $points): string {
    $sample = $stage . " | Nhập nội dung câu hỏi tại đây | Nhập đáp án tại đây | " . $points . "\n"
        . $stage . " | Câu hỏi minh họa thứ hai | Đáp án minh họa | " . $points . "\n";
    return 'data:text/plain;charset=utf-8;base64,' . base64_encode("# Mỗi dòng: Vòng | Câu hỏi | Đáp án | Điểm\n# Không xóa dấu | ngăn cách các cột. Dòng bắt đầu bằng # chỉ là ghi chú, cần xóa trước khi nạp.\n" . $sample);
}
?>
<?php if (!empty($weekId)): ?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-warning fw-bold" href="<?= BASE_URL ?>hoclieu_game_olympia_play.php?week=<?= e($weekId) ?><?= !empty($classId)?'&class='.urlencode($classId):'' ?>"><i class="bi bi-arrows-fullscreen"></i> Mở màn hình thi đấu</a>
  <?php if (!empty($admin)): ?><a class="btn btn-outline-primary fw-bold" href="<?= BASE_URL ?>hoclieu_game_olympia_rankings.php?period=week&week=<?= e($weekId) ?>"><i class="bi bi-trophy"></i> Xếp hạng tuần/tháng</a><?php endif; ?>
</div>
<?php endif; ?>
<details class="admin-box mb-3" open>
  <summary class="fw-bold fs-5"><i class="bi bi-journal-check text-primary"></i> Luật chơi Đường lên đỉnh Olympia</summary>
  <div class="row g-3 mt-1">
    <div class="col-lg-7">
      <ol class="mb-0 ps-3">
        <li>Quản trị tạo tuần, chọn các lớp tham gia và mở tuần theo lịch hoặc mở thủ công.</li>
        <li>GVCN mở trò chơi cho lớp mình, lần lượt đọc hoặc trình chiếu câu hỏi theo bốn chặng.</li>
        <li>Học sinh trả lời đúng được GVCN tích tên ngay tại câu hỏi; mỗi học sinh có thể được ghi nhận ở nhiều câu.</li>
        <li>Điểm của câu hỏi được cộng tự động cho từng học sinh và tổng hợp thành điểm lớp, điểm toàn trường.</li>
        <li>GVCN có thể sửa lựa chọn trước khi tuần bị khóa. Khi tuần đã khóa, dữ liệu được giữ nguyên.</li>
        <li>Trường hợp có nhiều học sinh trả lời đúng, giáo viên được tích nhiều em trong cùng một câu.</li>
      </ol>
    </div>
    <div class="col-lg-5">
      <div class="p-3 bg-white rounded-3 border h-100">
        <div class="fw-bold mb-2">Cơ cấu điểm đề xuất</div>
        <?php foreach ($olympiaStages as $stage => $meta): ?>
          <div class="d-flex justify-content-between border-bottom py-1"><span><?= e($stage) ?></span><strong><?= (int)$meta['points'] ?> điểm/câu</strong></div>
        <?php endforeach; ?>
        <div class="small text-muted mt-2">Quản trị có thể thay đổi điểm của từng câu khi nhập.</div>
      </div>
    </div>
  </div>
</details>

<?php if (!empty($admin) && $view === 'manage'): ?>
<div class="admin-box mb-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h5 class="fw-bold mb-1"><i class="bi bi-file-earmark-arrow-down text-success"></i> Mẫu nạp nhanh câu hỏi</h5><div class="small text-muted">Tải từng mẫu riêng, điền nội dung rồi sao chép các dòng dữ liệu vào ô “Nạp nhanh câu hỏi”.</div></div>
  </div>
  <div class="row g-2">
    <?php foreach ($olympiaStages as $stage => $meta): $slug = ['Khởi động'=>'khoi-dong','Vượt chướng ngại vật'=>'vuot-chuong-ngai-vat','Tăng tốc'=>'tang-toc','Về đích'=>'ve-dich'][$stage]; ?>
      <div class="col-md-6 col-xl-3">
        <div class="bg-white border rounded-3 p-3 h-100">
          <div class="stage mb-1"><?= e($stage) ?></div>
          <div class="small mb-3"><?= e($meta['note']) ?></div>
          <a class="btn btn-sm btn-outline-success w-100" download="mau-<?= e($slug) ?>.txt" href="<?= e(olympia_template_uri($stage, (int)$meta['points'])) ?>"><i class="bi bi-download"></i> Tải mẫu <?= e($stage) ?></a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="alert alert-warning small mt-3 mb-0"><strong>Ghi chú:</strong> Mỗi câu nằm trên một dòng theo cấu trúc <code>Vòng | Câu hỏi | Đáp án | Điểm</code>. Không dùng ký tự <code>|</code> bên trong nội dung câu hỏi hoặc đáp án. Hệ thống tự bỏ qua các dòng hướng dẫn bắt đầu bằng dấu <code>#</code>.</div>
</div>
<?php endif; ?>
