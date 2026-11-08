# Mực ơi đừng dậy!

Trò chơi gọi tên học sinh trong Học liệu → Trò chơi (`hoclieu.php?tab=games`). Trang chơi: `hoclieu_game_muc.php`.

## Sử dụng

1. Đăng nhập bằng tài khoản có quyền xem học liệu; chọn lớp từ CSDL chung.
2. Bỏ tích học sinh vắng. Có thể tìm tên hoặc số thứ tự, chọn/bỏ chọn cả lớp.
3. Chọn nhịp 10, 16 hoặc 24 giây; bấm Bắt đầu. Học sinh rón rén, Mực tỉnh giấc, rượt đuổi rồi công bố một em.
4. Lượt tiếp theo loại em đã gọi khi bật Không gọi lại. Vòng mới xóa lịch sử riêng của lớp đang chọn trên trình duyệt hiện tại.
5. Có âm thanh tổng hợp, toàn màn hình, tạm dừng/tiếp tục và kết thúc giữa lượt. Chuyển sang tab khác tự tạm dừng. Kết thúc giữa lượt không ghi lịch sử.

Quản trị ẩn/hiện game trong phần cài đặt trò chơi hiện có. Quyền truy cập trang giống các game gọi tên khác: đăng nhập và `hl.xem:view` hoặc admin. Ẩn/hiện là cấu hình hiển thị thẻ trong danh mục, cùng hành vi các game hiện có.

## Dữ liệu và lựa chọn

- Dùng `csdl_classes_all()` và `csdl_students_all()`, chỉ lấy học sinh đang hoạt động; hỗ trợ tên lớp hoặc `class_id`, `name/ho_ten`, ID/mã và giới tính. Không sửa dữ liệu CSDL, sổ đầu bài hay trò chơi khác.
- Mỗi ID được tích chọn có xác suất bằng nhau. `crypto.getRandomValues` kết hợp rejection sampling tránh lệch phép chia dư; chọn kết quả trước hoạt hình, chỉ ghi lịch sử sau khi hoàn tất. Học sinh trùng họ tên vẫn được phân biệt bằng ID.
- Với hơn 8 ứng viên, hoạt hình minh họa tối đa 8 nhân vật (luôn có người được chọn). Thuật toán vẫn xét toàn bộ ứng viên. Số lượng ứng viên thật được ghi trên sân khấu.
- Lịch sử và tích chọn dùng localStorage với khóa riêng tài khoản/lớp `cds.muc.v1.<user-hash>.<class>`. Chỉ lưu ID, thời điểm, tùy chọn; không lưu tên. Không đồng bộ giữa thiết bị, không dùng chung lịch sử với game khác.
- Đồ họa mới theo hình ảnh tham khảo; phông sân trường cố định, atlas 24 hình (6 tư thế chó, 12 tư thế học sinh, 6 vật che), chuyển động Canvas 2D theo đường chạy và xếp lớp trước/sau vật che. Không phải mã nguồn hoặc bản sao từng khung hình của sản phẩm tham khảo. Tiếng sủa dùng mẫu ghi âm CC0 được đóng gói tại `assets/muc/dog-bark.mp3` (nguồn trong `assets/muc/audio-sources.md`); nhạc và tiếng bước chân dùng Web Audio, chạy theo từng cảnh, không tải nhạc bên ngoài.

## Triển khai

Đưa các tệp sau từ cùng một commit lên thư mục CDS đang chạy:

- `hoclieu.php`
- `hoclieu_game_muc.php`
- `assets/cds-muc.css`
- `assets/cds-muc-engine.js`
- `assets/cds-muc-scene.js`
- `assets/cds-muc-motion.js`
- `assets/cds-muc-audio.js`
- `assets/cds-muc.js`
- Cả thư mục `assets/muc/` (gồm ảnh WebP, bản đồ atlas JSON và tiếng sủa MP3).

Nếu dùng cPanel Git Version Control: chọn repository CDS → Manage → Pull or Deploy → Update from Remote, sau đó Deploy HEAD Commit. Script `tools/deploy_cds.php` phải có `hoclieu_game_muc.php` trong `$rootFiles`; nếu thiếu mục này thì menu có thể đã cập nhật nhưng trang game vẫn báo 404. Toàn bộ `assets/` được script sao chép tự động.

Không cần tạo bảng hoặc thay đổi dữ liệu. Dùng bảng cấu hình ẩn/hiện đã có của hệ thống. Không đưa thư mục preview hoặc dữ liệu thử lên hosting. Sau tải lên, làm mới mạnh trình duyệt và mở Học liệu → Trò chơi → Mực ơi đừng dậy!.

Kiểm tra với một lớp thật: đối chiếu số học sinh, bỏ một em, chơi hai lượt không lặp, tạm dừng/tiếp tục, kết thúc trước công bố, đổi lớp và thử trên điện thoại. Commit GitHub không tự xác nhận hosting đã cập nhật.

## Kiểm tra

```sh
php -l hoclieu.php
php -l hoclieu_game_muc.php
php tests/cds_muc_roster_test.php
node tests/cds_muc_engine_test.cjs
node tests/cds_muc_controller_test.cjs
node tests/cds_muc_motion_test.cjs
node tests/cds_muc_audio_test.cjs
node --check assets/cds-muc-scene.js
```

CI `.github/workflows/muc-game.yml` chạy các kiểm tra PHP/JavaScript. Fixture PHP dùng thư mục tạm và các hàm CSDL/auth giả lập, không kết nối dữ liệu sản xuất.

## Tài nguyên hình ảnh

Các ảnh trong `assets/muc/` được tạo mới bằng công cụ tạo ảnh tích hợp, sau đó đóng gói WebP để chạy tại hosting. Đầu vào là ảnh tham khảo do người dùng cung cấp. Tóm tắt bộ yêu cầu tạo ảnh:

- `school.webp`: minh họa hoạt hình 2D ngang 16:9, trường vàng/cửa sổ xanh/cổng đỏ, hoa phượng bên trái, xe đạp/chậu cây/xe bán hàng; đường xám trống phía dưới; không nhân vật, chữ hoặc giao diện.
- `street.webp`: phố Việt Nam hoạt hình 2D ngang 16:9, nhà xanh/vàng/hồng, ban công hoa, mái hiên sọc, dây điện; đường xám trống phía dưới; không nhân vật, chữ hoặc giao diện.
- `sprites.webp`: atlas nền trong suốt 4 cột × 3 hàng; hàng 1 chó đen vàng nâu, vòng cổ đỏ chuông vàng ở tư thế ngủ/giận/chạy/vui; hàng 2 học sinh nam và hàng 3 học sinh nữ với áo trắng khăn đỏ/quần hoặc váy xanh ở tư thế rón rén/chạy/ăn mừng/sợ hãi; nét viền đậm, chibi, không chữ và không nền.

## Bản hoạt hình 20261108-2

- Mở đầu sợ/khóc → rón rén → Mực thức/sủa → chạy → nấp → bạn chậm/vấp bị bắt trúng.
- Tư thế đi/chạy thay hai khung chân, có nhún người, đổi hướng và cúi nấp.
- Đường chạy tránh chó đang ngủ và bạn còn đứng giữa sân; hàng sau chờ hàng trước rời vị trí. Các bạn đã nấp được vẽ sau vật che và không còn nhãn tên nổi phía trước.
- Mực đuổi theo vị trí bạn chạy chậm và chỉ có tín hiệu bắt khi mõm chạm vùng nhân vật. Việc này là hoạt hình minh họa kết quả ngẫu nhiên, không đánh giá khả năng chạy của học sinh thật.
- Giới hạn 8 nhân vật minh họa để có đủ khoảng trống cho cả người và tên. Mọi học sinh được tích chọn vẫn có cơ hội như nhau; người được chọn luôn có trong hoạt hình của lượt.
- `actors-v2.webp` được tạo mới theo nhân vật tham khảo: lưới 6 cột × 4 hàng, chó ngủ/cảnh giác/sủa/chạy A/chạy B/vui; nam và nữ khóc/đi A/đi B/chạy A/chạy B/vấp; cây/chậu/ghế/bụi cây/xe hàng/trụ cổng. `actors-v2.json` lưu vùng cắt từng ô để chân không bị lệch giữa các tư thế.
- Có nút Thử tiếng chó; nhạc đổi theo mốc cảnh, tắt nhạc lúc Mực sủa và dừng khi tạm dừng/hủy lượt.
