# Hồ sơ chuyên môn trên Google Drive

Nhóm menu Chuyên môn **Hồ sơ - Báo cáo** gồm Hồ sơ chuyên môn, Kế hoạch giáo dục và Báo cáo định kỳ. Trang mới: `/chuyenmon/hoso.php`.

## Cài đặt

1. Cập nhật mã nguồn bằng quy trình triển khai hiện có. Không cần SQL hay Node.js cho chức năng này; PHP cần cURL và mbstring như phân hệ Chuyên môn.
2. Trong cài đặt Google Drive của trường, bật và kết nối tài khoản Google có quyền đọc thư mục nguồn. Quyền OAuth hiện có phải cho phép đọc danh sách/nội dung siêu dữ liệu của thư mục đã chọn. Nếu kết nối chỉ được cấp `drive.file`, một thư mục bất kỳ chưa được ứng dụng cho phép có thể không đọc được; quản trị cần kiểm tra lại phạm vi cấp quyền của kết nối. Trang không tự xin thêm quyền hoặc đổi chia sẻ.
3. Đăng nhập quản trị hệ thống, vào **Hồ sơ - Báo cáo → Hồ sơ chuyên môn → Quản lý thư viện**. Dán đường dẫn thư mục rồi lưu. Máy chủ kiểm tra đây là thư mục mà tài khoản kết nối có quyền đọc trước khi lưu.
4. Cấp quyền **Hồ sơ chuyên môn** (`cm.hoso`) mức Xem cho nhóm/tài khoản cần sử dụng trong màn hình phân quyền. Không tự cấp thêm quyền cho các nhóm nhân sự hiện có; quy tắc quyền toàn phân hệ cũ và tài khoản trải nghiệm chỉ xem vẫn tuân theo cơ chế chung.

Cấu hình riêng tại `chuyenmon/data/academic_drive_folder.json` (thực tế theo `DATA_PATH` của phân hệ), không lưu khóa OAuth tại đây và không ghi đè cấu hình Drive chung. Tệp dữ liệu nằm trong vùng dữ liệu được bảo vệ hiện có. Hai phiên quản trị cùng sửa sẽ được kiểm tra phiên bản để tránh ghi đè.

## Giao diện thư viện

- Trang hiển thị **Thư viện nhà trường / Hồ sơ chuyên môn**, không có ngày cập nhật, cột nguồn, nhãn Google/Drive, liên kết nguồn hay iframe trình xem Google. Cấu hình quản trị được thu gọn ở cuối trang thành **Quản lý thư viện**.
- Hai chế độ lưới/danh sách, nhớ lựa chọn trên trình duyệt; biểu tượng theo thư mục, PDF, văn bản, bảng tính, trình chiếu, hình ảnh và tệp nén. Tên dài xuống dòng; không chèn tên tệp dưới dạng HTML.
- Tên và cấu trúc đọc từ nguồn khi mở hoặc làm mới; sắp xếp tự nhiên A–Z/Z–A, tìm kiếm theo tên trong thư mục hiện tại (tự tìm sau 350 ms), 100 mục/lần và Xem thêm. Các con số chỉ đếm các mục đã tải, ghi “còn tiếp” khi có trang sau.
- PDF và ảnh PNG/JPEG/GIF/WebP mở bằng trình xem của trình duyệt trong hộp thoại CDS, qua dữ liệu cùng hệ thống. Google Docs/Sheets/Slides/Drawings có thể tải bản PDF qua API export; Google giới hạn bản xuất ở 10 MB. Tệp thường giới hạn 32 MB cho mỗi lượt đọc.
- Word/Excel/PowerPoint tải lên dưới dạng tệp thường chưa có bộ chuyển đổi phía máy chủ: giao diện cung cấp nút Tải tài liệu, không chuyển ra trình xem bên ngoài. Lối tắt không theo đích ra ngoài thư mục gốc và không tải được.
- Tên tài liệu và nội dung được đọc bằng tài khoản lưu trữ đã kết nối của trường. **Tài khoản CDS được cấp Xem `cm.hoso` được xem/tải nội dung hợp lệ trong thư mục đã liên kết**, không cần phiên đăng nhập Google của từng người. Đây là thay đổi so với phiên bản iframe cũ: kiểm tra phạm vi chia sẻ trước khi cấp quyền. Không tự bật chia sẻ công khai.
- Mỗi lần xem/tải đều kiểm tra lại tài liệu, thùng rác, chuỗi thư mục cha đến thư mục gốc và `capabilities.canDownload` từ tài khoản kết nối. Tài liệu bị nguồn chặn tải sẽ bị từ chối; không bỏ qua hạn chế nguồn.
- Nội dung qua cURL GET tới API Google cố định, không theo redirect, thời gian kết nối 7 giây/tải 60 giây và giới hạn dung lượng. Tệp tạm tự xóa khi đóng stream; không lưu bản sao lâu dài. Băng thông đọc tài liệu đi qua hosting.
- Chỉ MIME PDF/ảnh được cho xem inline, kiểm tra chữ ký định dạng; tệp khác buộc tải dạng octet-stream với nosniff và filename mã hóa. Không cho HTML/SVG chạy trên origin ứng dụng. API danh sách không trả URL nguồn hoặc ngày cập nhật.
- Thêm/đổi tên/chuyển/xóa tại nguồn lưu trữ. Gỡ liên kết trên CDS không xóa tài liệu nguồn. Không có thay đổi SQL hoặc Node.js.

Chưa kiểm chứng nội dung Drive thực tế của trường nếu chưa có thư mục và tài khoản được chia sẻ. Kiểm thử dùng dữ liệu giả, không gọi Google thật.

## Kiểm tra

```sh
php tests/academic_drive_test.php
php tests/academic_drive_transport_test.php
php tests/cm_menu_test.php --html
php tests/cm_menu_pccm_test.php
NODE_PATH=/path/to/node_modules node tests/academic_drive_browser.cjs
NODE_PATH=/path/to/node_modules node tests/cm_menu_browser.cjs
```

Browser test cần Playwright 1.58.2; bài kiểm tra menu cần Bootstrap 5.3.3. GitHub Actions chạy PHP 8.2/8.3 và Chromium trên Ubuntu 24.04. Fixture HTTP sao chép trang thật với bộ xác thực/Drive giả trong thư mục tạm, kiểm tra CSRF, quyền quản trị, phiên bản cũ, XSS, phân trang, thư mục con, lỗi và màn hình nhỏ; không truy cập dữ liệu trường.
