<?php
/** One catalog for the game gallery and learning overview. */
function lh_game_catalog(): array {
    return [
    ['key'=>'achievement','name'=>'Bảng thành tích','description'=>'Điểm thưởng, điểm phạt theo lớp và môn; xếp hạng tuần, tháng, giai đoạn.','icon'=>'','emoji'=>'🏆','url'=>'hoclieu_thanhtich.php','action'=>'Mở Bảng thành tích'],
    ['key'=>'egg','name'=>'Trộm trứng','description'=>'Gà mẹ rượt đuổi và gọi tên học sinh trong lớp.','icon'=>'','emoji'=>'🥚','url'=>'hoclieu_game_goiten.php?game=egg','cover'=>'assets/goi-ten/egg-thumbnail-v2.webp','action'=>'Chơi Trộm trứng'],
    ['key'=>'ufo','name'=>'UFO','description'=>'Đĩa bay xuất hiện và chọn một học sinh thật bất ngờ.','icon'=>'','emoji'=>'🛸','url'=>'hoclieu_game_goiten.php?game=ufo','cover'=>'assets/goi-ten/ufo-thumbnail-v2.webp','action'=>'Chơi UFO'],
    ['key'=>'secret','name'=>'Ô cửa bí mật – Thử thách kiến thức','description'=>'Chọn ô, trả lời và lật ngược tình thế cùng 2–4 đội. Có ô bất ngờ, Excel và AI.','icon'=>'','emoji'=>'🎁','url'=>'hoclieu_game_secret.php','action'=>'Khám phá ô cửa'],
    ['key'=>'muc','name'=>'Mực ơi đừng dậy!','description'=>'Rón rén qua sân trường, chạy trốn Mực và gọi tên học sinh thật bất ngờ.','icon'=>'','emoji'=>'🐕','url'=>'hoclieu_game_muc.php','action'=>'Vào sân cùng Mực'],
    ['key'=>'buzzer','name'=>'Bấm chuông','description'=>'Mở phòng bằng mã, đồng hồ trực tiếp và giành quyền trả lời trên điện thoại.','icon'=>'bi-bell-fill','emoji'=>'','url'=>'hoclieu_game_buzzer.php','action'=>'Mở phòng bấm chuông'],
    ['key'=>'rocket','name'=>'Tên lửa hạ cánh','description'=>'Bay qua 24 hành tinh kỳ thú, hạ cánh và gọi tên học sinh bất ngờ','icon'=>'','emoji'=>'🚀','url'=>'hoclieu_game_rocket.php','action'=>'Phóng tên lửa'],
    ['key'=>'balldrop','name'=>'Thả bóng gọi tên','description'=>'Bóng zíc zắc, đường ống bí mật và những cái tên bất ngờ','icon'=>'bi-circle-fill','emoji'=>'🎱','url'=>'hoclieu_game_thabong.php','action'=>'Thả bóng gọi tên'],

    ['key'=>'clock','name'=>'Bộ đếm thời gian','description'=>'11 công cụ thời gian: báo thức, đếm ngược, bấm giờ, múi giờ và tính ngày','icon'=>'bi-clock','emoji'=>'','url'=>'hoclieu_clock.php','action'=>'Mở bộ đếm'],

    ['key'=>'quiz','name'=>'Hỏi Nhanh - Đáp Gọn','description'=>'Hỏi đáp kiến thức linh hoạt bằng máy và bảng','icon'=>'bi-qr-code-scan','emoji'=>'','url'=>'hoclieu_game_quiz.php','action'=>'Tạo câu hỏi và chơi'],
    ['key'=>'cards','name'=>'Thẻ trả lời A–D','description'=>'In thẻ QR theo lớp để học sinh dùng lại trong các buổi học.','icon'=>'bi-person-vcard','emoji'=>'','url'=>'hoclieu_quiz_cards.php','action'=>'Quản lý và in thẻ'],
    ['key'=>'olympia','name'=>'Đường lên đỉnh Olympia','description'=>'Chinh phục các vòng thi kiến thức và theo dõi bảng xếp hạng.','icon'=>'','emoji'=>'🏔️','url'=>'hoclieu_game_olympia.php','action'=>'Mở Olympia'],
    ['key'=>'wheel','name'=>'Vòng quay may mắn','description'=>'Quay phần thưởng, câu hỏi hoặc nhiệm vụ bất ngờ trên lớp.','icon'=>'','emoji'=>'🎡','url'=>'hoclieu_game.php','action'=>'Mở trò chơi'],
    ['key'=>'keoco','name'=>'Kéo co kiến thức','description'=>'Hai đội cùng trả lời để kéo dây về phía mình.','icon'=>'','emoji'=>'🪢','url'=>'hoclieu_game_keoco.php','action'=>'Mở trò chơi'],
    ['key'=>'duavit','name'=>'Đua vịt gắn tên học sinh','description'=>'Chọn lớp, xáo làn và cổ vũ chú vịt về đích.','icon'=>'','emoji'=>'🦆','url'=>'hoclieu_game_duavit.php','action'=>'Mở trò chơi'],
    ['key'=>'loto','name'=>'Quay số gọi tên','description'=>'Chọn ngẫu nhiên học sinh từ danh sách của lớp.','icon'=>'','emoji'=>'🎲','url'=>'hoclieu_game_loto.php','action'=>'Mở trò chơi'],
    ['key'=>'chopnot','name'=>'Chớp Nốt','description'=>'Khám phá khuông nhạc, trường độ và nhạc cụ qua trò chơi.','icon'=>'','emoji'=>'🎼','url'=>'hoclieu_game_chopnot.php','action'=>'Mở trò chơi'],
];
}
