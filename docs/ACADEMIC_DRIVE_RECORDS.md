# Hồ sơ chuyên môn trên Google Drive

Nhóm menu Chuyên môn **Hồ sơ - Báo cáo** gồm Hồ sơ chuyên môn, Kế hoạch giáo dục và Báo cáo định kỳ. Trang mới: `/chuyenmon/hoso.php`.

## Cài đặt

1. Cập nhật mã nguồn bằng quy trình triển khai hiện có. Không cần SQL hay Node.js cho chức năng này; PHP cần cURL và mbstring như phân hệ Chuyên môn.
2. Trong cài đặt Google Drive của trường, bật và kết nối tài khoản Google có quyền đọc thư mục nguồn. Quyền OAuth hiện có phải cho phép đọc danh sách/nội dung siêu dữ liệu của thư mục đã chọn. Nếu kết nối chỉ được cấp `drive.file`, một thư mục bất kỳ chưa được ứng dụng cho phép có thể không đọc được; quản trị cần kiểm tra lại phạm vi cấp quyền của kết nối. Trang không tự xin thêm quyền hoặc đổi chia sẻ.
3. Đăng nhập quản trị hệ thống, vào **Hồ sơ - Báo cáo → Hồ sơ chuyên môn → Cấu hình thư mục Google Drive**. Dán đường dẫn thư mục rồi lưu. Máy chủ kiểm tra đây là thư mục mà tài khoản kết nối có quyền đọc trước khi lưu.
4. Cấp quyền **Hồ sơ chuyên môn (Google Drive)** (`cm.hoso`) mức Xem cho nhóm/tài khoản cần sử dụng trong màn hình phân quyền. Không tự cấp thêm quyền cho các nhóm nhân sự hiện có; quy tắc quyền toàn phân hệ cũ và tài khoản trải nghiệm chỉ xem vẫn tuân theo cơ chế chung.

Cấu hình riêng tại `chuyenmon/data/academic_drive_folder.json` (thực tế theo `DATA_PATH` của phân hệ), không lưu khóa OAuth tại đây và không ghi đè cấu hình Drive chung. Tệp dữ liệu nằm trong vùng dữ liệu được bảo vệ hiện có. Hai phiên quản trị cùng sửa sẽ được kiểm tra phiên bản để tránh ghi đè.

## Duyệt và xem

- Tên và cây thư mục đọc trực tiếp từ Drive mỗi lần mở hoặc bấm Làm mới; có đường dẫn quay về thư mục cha.
- Thư mục hiển thị trước, tên theo thứ tự tự nhiên A–Z mặc định; có Z–A và cập nhật mới nhất. Không sao chép tùy chọn sắp xếp riêng của giao diện Drive từng người dùng.
- Tìm theo tên trong thư mục đang mở, 100 mục/lần, Xem thêm để đọc trang tiếp. Hỗ trợ Shared Drive và resource key trong liên kết.
- Bấm tài liệu mở khung xem Google; luôn có liên kết mở tài liệu gốc trên Drive. Một số loại tệp hoặc chính sách Google không cho xem trong khung: dùng liên kết nguồn.
- Tên tài liệu/thư mục được trả qua tài khoản Google của trường cho người có quyền `cm.hoso`. Nội dung trong khung xem/Drive tuân theo quyền chia sẻ và tài khoản Google đang đăng nhập của người xem. Không cần chia sẻ công khai.
- Lối tắt mở trên Drive, không tự đưa thư mục bên ngoài vào cây hồ sơ. Thư mục ngoài gốc, đã chuyển ra ngoài hoặc vào thùng rác bị từ chối.
- Thêm/đổi tên/chuyển/xóa tài liệu thực hiện tại Drive. Gỡ liên kết trên CDS chỉ gỡ cấu hình, không xóa dữ liệu Drive.

Chưa kiểm chứng kết nối với Drive thực tế của trường nếu chưa cung cấp thư mục và tài khoản được chia sẻ. Các kiểm thử dùng dữ liệu giả, không gọi API Google thật.

## Kiểm tra

```sh
php tests/academic_drive_test.php
php tests/cm_menu_test.php --html
php tests/cm_menu_pccm_test.php
NODE_PATH=/path/to/node_modules node tests/academic_drive_browser.cjs
NODE_PATH=/path/to/node_modules node tests/cm_menu_browser.cjs
```

Browser test cần Playwright 1.58.2; bài kiểm tra menu cần Bootstrap 5.3.3. GitHub Actions chạy PHP 8.2/8.3 và Chromium trên Ubuntu 24.04. Fixture HTTP sao chép trang thật với bộ xác thực/Drive giả trong thư mục tạm, kiểm tra CSRF, quyền quản trị, phiên bản cũ, XSS, phân trang, thư mục con, lỗi và màn hình nhỏ; không truy cập dữ liệu trường.
