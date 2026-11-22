# Loại học liệu, hình thức và xem trong trang

Biểu mẫu nhập/sửa dùng một cấu hình chung giữa PHP và JavaScript:

| Loại | Hình thức |
|---|---|
| Bài viết | Soạn trực tiếp, tải tệp, liên kết |
| Văn bản | Tải tệp văn bản/PDF, liên kết tài liệu |
| Ứng dụng / Trò chơi | Tệp HTML tương tác, liên kết website |
| Video | Tệp MP4/WebM/OGV, YouTube, Vimeo, liên kết tệp video HTTPS |
| Trình chiếu | Tệp PPT/PPTX/ODP/PDF, liên kết |
| Hình ảnh | Tệp PNG/JPEG/GIF/WebP, liên kết hình ảnh HTTPS |
| Đề kiểm tra / Thi thử / Kỳ thi | Tải tệp, liên kết |

Đổi loại tự cập nhật các lựa chọn và trường nhập, định dạng file và hướng dẫn. Máy chủ kiểm tra lại cặp loại/hình thức, liên kết nhà cung cấp, phần mở rộng và chữ ký video/ảnh. Khi đổi loại/hình thức của tệp đã lưu, cần chọn tệp mới phù hợp. Giới hạn tải lên vẫn là 50 MB.

Nút Xem ở Học liệu, Kiểm tra và Tổng quan mở modal cùng trang qua `hoclieu_file.php?id=…&preview=1`. Bài viết và HTML tương tác giữ sandbox; YouTube/Vimeo dùng URL nhúng được chuẩn hóa theo ID (không nhận mã iframe). Upload Video/Hình ảnh dùng trình phát/video hoặc ảnh qua endpoint đã xác thực `inline=1`; video có hỗ trợ Range để tua. Tài liệu tải lên dùng khung preview của Drive. Website chung được nhúng trong sandbox; website/video do chủ nguồn chặn nhúng hoặc giới hạn quyền sẽ không thể buộc hiển thị. Không tự chuyển sang cửa sổ khác.

Tất cả chế độ wrapper, bài viết và byte tệp đều kiểm tra quyền của ngăn (`hl.xem` hoặc `hl.kiemtra`) và trạng thái duyệt/chủ sở hữu. Không chỉ bảo vệ nút giao diện. Đóng modal dỡ iframe để dừng phát. Tệp nhị phân và trang nhúng không qua bộ chèn HTML giao diện chung; không ghi nhận action lưu trữ trên mỗi yêu cầu video.

Dữ liệu cũ không bị di trú: liên kết YouTube cũ vẫn nhận diện khi xem; bản từng lưu Video + bài viết vẫn mở như bài viết. Khi sửa, giao diện nhận diện Bài viết từ hình thức article, Ứng dụng từ HTML và nhận diện nhà cung cấp từ liên kết Video. Chỉ ghi thay đổi khi người dùng lưu; không làm mất nội dung cũ khi chỉ mở biểu mẫu.

Không cần SQL/Node.js mới. Cập nhật các tệp root, includes và assets theo quy trình triển khai hiện có.

Kiểm tra: `php tests/learning_content_test.php`, `node tests/learning_content_browser.cjs`. Browser test chạy controller/file endpoint thật với kho lưu trữ/quyền/YouTube/Vimeo giả, không gọi dịch vụ thật. Kiểm tra form liên động, lưu record, server từ chối tổ hợp sai, legacy article, same-page modal, upload video/range, sandbox và giới hạn quyền theo ngăn trên màn hình 320–1440px. TinyMCE được mô phỏng trong bài kiểm tra này; logic bộ soạn thảo hiện có được giữ nguyên.

Trang `hoclieu_file.php` đánh dấu `CDS_EMBEDDED_RESPONSE` trước khi nạp cấu hình. Bộ lọc điều hướng `page_navigation.php` không chèn thanh Quay lại/Trang chủ hoặc tài nguyên giao diện vào phản hồi này. Điều này giữ nguyên CSP của trình xem và tránh biểu tượng SVG không có CSS bị phóng lớn trong khung video. Fixture HTTP nạp bộ lọc điều hướng thật để kiểm tra hồi quy; các trang ứng dụng bình thường vẫn có thanh điều hướng như cũ.

## Google Drive xem trong trang

Khi nhập/sửa học liệu hoặc đề thi, chọn **Hình thức nội dung → Google Drive — xem trong trang**, dán link chia sẻ của tệp rồi lưu. Hỗ trợ link Drive dạng `/file/d/…/view`, `/open?id=…`, `/uc?id=…`, Google Docs, Sheets và Slides. Khi bấm Xem hoặc ảnh đại diện, tệp mở trong khung ngay trên trang hiện tại. Link Drive đã lưu dưới hình thức Liên kết cũng được tự chuyển sang trình xem nhúng. Khóa `resourcekey` trong link được giữ.

Tệp cần được cấp quyền xem phù hợp cho người sử dụng (ví dụ Bất kỳ ai có đường liên kết). Hệ thống không thay đổi quyền chia sẻ. Link thư mục không dùng để nhập một học liệu/đề thi. Máy chủ chỉ chuẩn hóa URL từ các tên miền Google hợp lệ; không tải tệp qua link và không nhận mã HTML nhúng. Nội dung vẫn chịu quyền xem và quy trình duyệt hiện có. Không cần SQL.
