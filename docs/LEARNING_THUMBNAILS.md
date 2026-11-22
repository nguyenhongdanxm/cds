# Ảnh đại diện học liệu

Biểu mẫu Nhập/Sửa học liệu và đề kiểm tra có mục **Ảnh đại diện (không bắt buộc)**: dán link trực tiếp đến ảnh bên ngoài hoặc tải ảnh riêng, xem trước, thay ảnh hoặc chọn **Bỏ ảnh, dùng hình mặc định**. Khi sửa mà không chọn ảnh khác, ảnh đang lưu được giữ nguyên.

Không chọn ảnh sẽ dùng hình minh họa có màu, biểu tượng và tên loại: bài viết, văn bản, video, hình ảnh, trình chiếu, ứng dụng/trò chơi và đề thi. Học liệu cũ tự có hình mặc định, không cần sửa dữ liệu. Ảnh/hình mặc định hiển thị ở cả danh sách và ngăn Tổng quan; bấm ảnh ở danh sách mở khung xem học liệu.

Ảnh nhận PNG/JPEG/GIF/WebP, không nhận SVG/HTML; tối đa 2 MB và 4096 × 4096 px. Giao diện gợi ý ảnh ngang 16:9. Ảnh tự phủ kín khung 16:9 với `object-fit: cover`, giữ tỉ lệ và cắt phần rìa khi cần. Áp dụng cho ảnh tải lên, ảnh qua link, danh sách, ngăn chuyên mục và Tổng quan. Máy chủ kiểm tra nội dung ảnh thực tế, kích thước, dung lượng và tệp upload hợp lệ; không tin MIME/tên từ trình duyệt.

Tệp ảnh lưu riêng ở `DATA_PATH/learning_thumbnails/` với tên ngẫu nhiên và đuôi theo MIME thật; JSON học liệu dùng các trường tùy chọn `thumbnail_file` và `thumbnail_external_url`. Thư mục nằm trong vùng dữ liệu riêng hiện có, không phục vụ file tĩnh. Không cần kết nối Drive để tải ảnh đại diện của học liệu liên kết. Không có SQL mới.

Ảnh tải lên chỉ được đọc qua `hoclieu_file.php?id=…&thumbnail=1&v=…` sau khi xác thực quyền xem ngăn và duyệt/chủ sở hữu của học liệu. URL không chấp nhận đường dẫn tệp. Đọc ảnh chặn symlink/đường dẫn ra ngoài thư mục; có nosniff, MIME ảnh, ETag và private/no-cache. Kiểm tra quyền trước cả phản hồi 304. Ảnh cũ được dọn sau khi cập nhật/xóa bản ghi thành công; không dọn trước khi JSON được lưu. Các tệp dữ liệu/ảnh không được đưa lên GitHub.

Triển khai mã nguồn theo quy trình hiện tại; giữ và sao lưu thư mục dữ liệu cùng JSON học liệu. PHP cần quyền tạo/ghi thư mục dữ liệu như các chức năng tải tệp hiện có. Kiểm tra `tests/learning_thumbnail_test.php` và HTTP/Chromium `tests/learning_thumbnail_browser.cjs` bao gồm upload, thay/bỏ/giữ ảnh, fallback, dữ liệu cũ, ảnh giả, đường dẫn, quyền/ETag, dọn tệp, danh sách/Tổng quan và giao diện 320–1440px.

Link ảnh bên ngoài nhận HTTP/HTTPS hợp lệ, không nhận mã JavaScript, data URL hay URL kèm tài khoản. Trình duyệt tải ảnh trực tiếp, không gửi referrer; máy chủ không tải/proxy URL bên ngoài. Cần link công khai trả về ảnh trực tiếp; trang chia sẻ hoặc trang đăng nhập không dùng được làm ảnh. Khi chọn tệp mới, tệp thay thế link. Xóa link hoặc chọn Bỏ ảnh đưa về hình mặc định; sửa bằng biểu mẫu cũ không gửi trường link vẫn giữ link đang có.
