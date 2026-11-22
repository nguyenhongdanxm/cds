# Tổng quan Học liệu và thi

`hoclieu.php?tab=dashboard` hiển thị các ngăn Học liệu, Kiểm tra và thi, Trò chơi; Liên kết hữu ích nằm riêng cuối trang. Các ô thống kê đầu trang dẫn tới từng ngăn; mỗi ngăn có số lượng và nút Xem tất cả để tới tab tương ứng.

- Học liệu/kiểm tra: tối đa 6 mục mỗi ngăn, ưu tiên nội dung đã duyệt được đánh dấu nổi bật rồi mới đến nội dung mới nhất. Không cần đánh dấu nổi bật để xuất hiện.
- Nội dung dùng chính chính sách hiển thị hiện có: đã duyệt, bản chờ duyệt của chính người đăng, hoặc nội dung quản trị/tài khoản trải nghiệm được xem. Bản chờ duyệt có nhãn riêng. Không mở rộng quyền cho tài khoản hoặc nhóm.
- Trò chơi: tối đa 8 mục có ảnh minh họa lấy từ cùng danh mục 14 trò chơi ở tab Trò chơi. Giáo viên không thấy trò chơi bị ẩn; quản trị có cách xem giống tab hiện có. Nếu không đọc được cấu hình ẩn/hiện, ngăn Tổng quan báo chưa tải được và không tự hiện trò chơi bị ẩn.
- Liên kết: tối đa 6 mục. Số lượng ở các ngăn tính toàn bộ mục người dùng được xem, không chỉ số mục xem trước. Ngăn rỗng vẫn có tiêu đề, thông báo gọn và nút Xem tất cả.
- Quyền `hl.xem`, `hl.kiemtra`, `hl.lienket` quyết định từng ngăn. Tài khoản chỉ có quyền kiểm tra không nhận nội dung/thống kê học liệu, trò chơi hay liên kết trên Tổng quan.

Cập nhật mã nguồn theo quy trình hiện tại, không cần SQL/Node.js hay nhập lại dữ liệu. `includes/learning_game_catalog.php` dùng chung cho Tổng quan và thư viện trò chơi. Các thao tác đăng bài, duyệt, sửa/xóa và chơi vẫn dùng các trang/handler hiện có.

Kiểm tra PHP: `tests/learning_dashboard_test.php`, `tests/game_gallery_test.php`. HTTP/Chromium: `tests/learning_dashboard_browser.cjs` dùng trang thật trong thư mục tạm với dữ liệu/quyền giả, không kết nối cơ sở dữ liệu của trường; kiểm tra phân quyền, bản chờ duyệt, trò chơi ẩn, lỗi dữ liệu, XSS/liên kết và màn hình 320–1440px. CI chạy PHP 8.2/8.3.
