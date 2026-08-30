# TRIỂN KHAI CDS SANG TRƯỜNG KHÁC KHÔNG SỬA CODE

Mục tiêu: sao chép nguyên bộ source CDS sang môi trường khác và **chỉ cấu hình trên giao diện**, không chỉnh PHP/JS/YAML trong source.

## 1. Một nơi cấu hình duy nhất

Sau khi đăng nhập bằng tài khoản quản trị, mở:

`Quản trị CDS → Cấu hình trường`

hoặc truy cập trực tiếp:

`/instance_settings.php`

Trang này quản lý tập trung:

- tên trường, mã trường, tên rút gọn;
- cơ quan chủ quản, năm học, website, địa chỉ, điện thoại, email;
- logo, tiêu đề CDS, PWA;
- cấp học THCS/THPT;
- module sử dụng;
- chế độ khởi tạo dữ liệu Chuyên môn;
- kết nối MySQL/MariaDB;
- Google Drive cơ bản;
- thư mục triển khai trên hosting.

Người quản trị **không cần mở hoặc sửa các file source**.

## 2. Bộ cấu hình hợp nhất

Giao diện lưu cấu hình chính vào một file duy nhất ngoài web root:

`/home/<user>/cds_private/instance.json`

Có thể thay vị trí bằng biến môi trường `CDS_INSTANCE_CONFIG` nếu hosting yêu cầu.

File này có các nhóm chính:

- `school`
- `database`
- `drive`
- `deployment`

Mật khẩu database, OAuth secret và khóa Service Account của Drive đều được lưu
trong file ngoài web root với quyền file hạn chế. Giao diện không hiển thị lại
mật khẩu hoặc khóa đã lưu.

Mẫu đầy đủ: `docs/instance.example.json`.

## 3. Tương thích với hệ thống cũ

Các cơ chế cũ vẫn được giữ làm fallback:

- `school.json`
- `database.conf`
- `deploy.json`
- cấu hình Drive cũ

Nếu chưa có `instance.json`, CDS Xín Mần tiếp tục hoạt động theo cấu hình hiện tại. Không có quá trình tự động ghi đè, xóa hay chuyển đổi dữ liệu cũ.

Khi `instance.json` tồn tại, cấu hình hợp nhất được ưu tiên.

## 4. Database

Trang `Cấu hình trường` kiểm tra kết nối MySQL trước khi lưu. Nếu host/database/user/password sai, hệ thống từ chối lưu để tránh làm hỏng một bản CDS đang vận hành.

Mỗi trường vẫn nên dùng database riêng.

## 5. Google Drive

Trang cấu hình cho phép bật/tắt Drive, nhập Service Account JSON và các thư mục Drive cơ bản.

Toàn bộ cấu hình Drive được lưu trực tiếp trong nhóm `drive` của `instance.json`;
không cần tạo thêm `google_drive_settings.json`. Các thiết lập Drive nâng cao vẫn
có thể quản lý tại trang Kho Google Drive hiện có và cũng được ghi về cùng
`instance.json`.

Nếu một bản cũ chưa có nhóm `drive`, hệ thống vẫn đọc file Drive cũ. Khi lưu lại
trang **Cấu hình trường**, cấu hình Drive hiện có được đưa vào `instance.json` và
từ đó file hợp nhất được ưu tiên.

## 6. Triển khai

`tools/deploy_cds.php` ưu tiên đọc:

1. `CDS_DEPLOY_PATH` nếu được đặt;
2. `instance.json → deployment.target_path`;
3. cấu hình `deploy.json` cũ;
4. fallback Xín Mần để tương thích bản hiện tại.

Vì vậy sau khi đã cấu hình trường, các lần cập nhật/deploy sau không cần sửa `.cpanel.yml` hoặc đường dẫn trong code.

## 7. Quy trình dùng cho trường mới

1. Copy nguyên source CDS sang hosting/domain mới.
2. Đăng nhập quản trị.
3. Mở **Cấu hình trường**.
4. Khai báo thông tin trường.
5. Chọn cấp học và module sử dụng.
6. Nhập database và mật khẩu database.
7. Cấu hình Drive nếu sử dụng; không cần khai báo thêm đường dẫn file Drive.
8. Khai báo thư mục website trên hosting.
9. Bấm **Lưu bộ cấu hình trường**.
10. Tải lại trang và bắt đầu nhập dữ liệu của trường mới.

Không cần sửa `includes/config.php`, `school_config.php`, `database.php`, `.cpanel.yml`, `manifest.php`, file Chuyên môn hoặc các file PHP khác.

## 8. An toàn với bản Xín Mần hiện tại

Nếu chưa bấm lưu tại trang `Cấu hình trường` và chưa có `instance.json`, hệ thống vẫn dùng toàn bộ fallback hiện tại của Xín Mần.

Việc bổ sung cơ chế cấu hình hợp nhất không tự động thay database, dữ liệu Chuyên môn, dữ liệu nội trú, Drive hoặc đường dẫn deploy của hệ thống đang chạy.
