<?php
$olympiaStages = [
    'Khởi động' => ['points'=>10, 'note'=>'Câu hỏi ngắn, rõ ý; ưu tiên kiến thức nhận biết và trả lời nhanh.'],
    'Vượt chướng ngại vật' => ['points'=>20, 'note'=>'Mỗi câu là một gợi ý liên quan đến từ khóa hoặc chủ đề chung.'],
    'Tăng tốc' => ['points'=>30, 'note'=>'Câu hỏi cần suy luận nhanh; có thể dùng dữ kiện, hình ảnh hoặc tình huống.'],
    'Về đích' => ['points'=>40, 'note'=>'Câu hỏi vận dụng, phân hóa; ghi rõ đáp án và cách chấp nhận đáp án tương đương.'],
];
function olympia_template_uri(string $stage, int $points): string {
    $sample = "6,7 | " . $stage . " | Nhập nội dung câu hỏi tại đây | Nhập đáp án tại đây | " . $points . "\n"
        . "Tất cả | " . $stage . " | Câu hỏi dùng chung minh họa | Đáp án minh họa | " . $points . "\n";
    return 'data:text/plain;charset=utf-8;base64,' . base64_encode("# Mỗi dòng: Khối | Vòng | Câu hỏi | Đáp án | Điểm\n# Khối có thể là 6, 7, 6,7 hoặc Tất cả. Không dùng ký tự | trong câu hỏi và đáp án.\n" . $sample);
}
function olympia_all_template_uri(array $stages): string {
    $content="# MẪU DÁN CHUNG 4 VÒNG OLYMPIA - 40 CÂU\n";
    $content.="# Cấu trúc: Khối | Vòng | Câu hỏi | Đáp án | Điểm\n";
    $content.="# Thay 6,7 bằng 6, 7, 6,7 hoặc Tất cả theo phạm vi áp dụng. Không dùng ký tự | trong câu hỏi và đáp án.\n";
    foreach($stages as $stage=>$meta){
        for($i=1;$i<=10;$i++)$content.="6,7 | ".$stage." | Nhập câu hỏi ".$i." | Nhập đáp án ".$i." | ".(int)$meta['points']."\n";
    }
    return 'data:text/plain;charset=utf-8;base64,'.base64_encode($content);
}
?>
<?php if (!empty($weekId)): ?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="btn btn-warning fw-bold" target="_blank" href="<?= BASE_URL ?>hoclieu_game_olympia_screen.php?week=<?= e($weekId) ?><?= !empty($classId)?'&class='.urlencode($classId):'' ?>"><i class="bi bi-display"></i> Màn hình 1 · Trình chiếu</a>
  <a class="btn btn-primary fw-bold" href="<?= BASE_URL ?>hoclieu_game_olympia_play.php?week=<?= e($weekId) ?><?= !empty($classId)?'&class='.urlencode($classId):'' ?>"><i class="bi bi-phone"></i> Màn hình 2 · Điều khiển</a>
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
    <div><h5 class="fw-bold mb-1"><i class="bi bi-file-earmark-arrow-down text-success"></i> Mẫu nạp nhanh câu hỏi</h5><div class="small text-muted">Tải mẫu chung đủ bốn vòng hoặc mẫu riêng từng vòng. Mọi mẫu đều có đủ cột Khối, Vòng, Câu hỏi, Đáp án và Điểm.</div></div>
    <a class="btn btn-primary" download="mau-dan-chung-olympia-40-cau.txt" href="<?= e(olympia_all_template_uri($olympiaStages)) ?>"><i class="bi bi-clipboard-check"></i> Tải mẫu dán chung 4 vòng</a>
  </div>
  <div class="row g-2">
    <?php foreach ($olympiaStages as $stage => $meta): $slug = ['Khởi động'=>'khoi-dong','Vượt chướng ngại vật'=>'vuot-chuong-ngai-vat','Tăng tốc'=>'tang-toc','Về đích'=>'ve-dich'][$stage]; ?>
      <div class="col-md-6 col-xl-3">
        <div class="bg-white border rounded-3 p-3 h-100">
          <div class="stage mb-1"><?= e($stage) ?></div>
          <div class="small mb-3"><?= e($meta['note']) ?></div>
          <div class="d-grid gap-2">
            <a class="btn btn-sm btn-success" href="<?= BASE_URL ?>hoclieu_game_olympia_template.php?stage=<?= urlencode($stage) ?>"><i class="bi bi-file-earmark-excel"></i> Tải mẫu Excel</a>
            <a class="btn btn-sm btn-outline-success" download="mau-dan-<?= e($slug) ?>.txt" href="<?= e(olympia_template_uri($stage, (int)$meta['points'])) ?>"><i class="bi bi-clipboard"></i> Tải mẫu dán</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="alert alert-warning small mt-3 mb-0"><strong>Ghi chú:</strong> Cấu trúc thống nhất là <code>Khối | Vòng | Câu hỏi | Đáp án | Điểm</code>. Khối nhận <code>6</code>, <code>7</code>, <code>6,7</code> hoặc <code>Tất cả</code>. Có thể sao chép trực tiếp các dòng từ Excel rồi dán vào ô nạp nhanh; hệ thống nhận cả cột ngăn bằng tab và dấu <code>|</code>. Không dùng ký tự <code>|</code> trong câu hỏi hoặc đáp án.</div>
</div>
<?php endif; ?>
