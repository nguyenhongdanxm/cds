# TRIỂN KHAI CDS CHO TRƯỜNG MỚI

CDS dùng **một bộ cấu hình hợp nhất** cho mỗi trường. Người quản trị không cần
sửa PHP, JavaScript, YAML hoặc nhiều file cấu hình riêng.

## 1. File cấu hình duy nhất

Mặc định CDS lưu cấu hình tại:

`/home/<user>/cds_private/instance.json`

File nằm ngoài web root và được đặt quyền hạn chế. Có thể đổi vị trí bằng biến
môi trường `CDS_INSTANCE_CONFIG`.

Các nhóm cấu hình trong cùng file:

- `school`: nhận diện trường, năm học, cấp học, module và PWA;
- `database`: kết nối MySQL/MariaDB;
- `drive`: OAuth hoặc Service Account và các thư mục Google Drive;
- `deployment`: thư mục đích trên hosting.

Mẫu cấu trúc đầy đủ: `docs/instance.example.json`.

Không commit `instance.json` lên GitHub vì file có thể chứa mật khẩu database và
khóa Google Drive.

## 2. Cấu hình bằng giao diện

Sau khi đăng nhập tài khoản quản trị, mở:

`Quản trị CDS → Cấu hình trường`

hoặc truy cập `/instance_settings.php`.

Trang này cho phép khai báo toàn bộ thông tin trường, database, Drive, dữ liệu
khởi tạo Chuyên môn và đường dẫn deploy. Khi bấm **Lưu bộ cấu hình trường**, mọi
thiết lập được ghi vào cùng `instance.json`.

Mật khẩu database và JSON Service Account không được hiển thị lại trên giao
diện. Để trống các ô bí mật khi chỉnh sửa lần sau sẽ giữ nguyên giá trị đã lưu.

## 3. Quy trình nhân bản

1. Tạo database và người dùng database riêng cho trường mới.
2. Copy nguyên source CDS sang hosting/domain mới.
3. Đăng nhập bằng tài khoản quản trị ban đầu.
4. Mở **Cấu hình trường**.
5. Nhập thông tin nhà trường, cấp học và các module cần dùng.
6. Nhập database; CDS sẽ kiểm tra kết nối trước khi lưu.
7. Chọn `Trường mới – dữ liệu rỗng` cho phần khởi tạo Chuyên môn.
8. Nhập Drive nếu sử dụng.
9. Nhập đúng thư mục website trên hosting.
10. Lưu cấu hình và tải lại trang.

Từ lần triển khai tiếp theo, `tools/deploy_cds.php` tự đọc
`instance.json → deployment.target_path`; không cần sửa `.cpanel.yml`.

## 4. Dữ liệu và tài nguyên riêng

Mỗi trường cần dùng database và thư mục dữ liệu vận hành riêng. Việc thay cấu
hình nhận diện không tự động sao chép, xóa hoặc chuyển đổi dữ liệu của trường
khác.

Logo và icon có thể tiếp tục dùng file mặc định trong source hoặc thay bằng tài
nguyên riêng rồi khai báo đường dẫn trên trang cấu hình.

## 5. Tương thích bản cũ

Nếu chưa có `instance.json`, CDS vẫn đọc các cấu hình cũ để bảo đảm hệ thống Xín
Mần đang vận hành không bị thay đổi:

- `school.json`;
- `database.conf`;
- `deploy.json`;
- `google_drive_settings.json`.

Đây chỉ là cơ chế fallback. Khi lưu trang **Cấu hình trường**, các giá trị đang
dùng được gom vào `instance.json` và file hợp nhất được ưu tiên từ đó về sau.

## 6. Kiểm tra sau cấu hình

- Đăng nhập và kiểm tra đúng tên, logo, năm học của trường.
- Mở trang trạng thái database và xác nhận kết nối thành công.
- Kiểm tra các module đã bật/tắt.
- Nếu dùng Drive, thử quyền truy cập một thư mục trước khi tải tài liệu thật.
- Chạy deploy và xác nhận thông báo `CDS_DEPLOY_OK` trỏ đúng thư mục website.

Không xóa các file cấu hình cũ ngay trong lần chuyển đổi đầu tiên. Chỉ dọn chúng
sau khi đã xác nhận `instance.json` hoạt động ổn định và đã có bản sao lưu.
