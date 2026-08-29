# HƯỚNG DẪN NHÂN BẢN CDS SANG TRƯỜNG KHÁC

Tài liệu này dùng cho mô hình **mỗi trường một source + một database riêng**.

## 1. Thông tin nhận diện nhà trường

Chỉnh tại `includes/school_config.php`:

- `code`: mã trường nội bộ.
- `name`: tên trường đầy đủ.
- `short_name`: tên trường rút gọn.
- `department`: cơ quan chủ quản.
- `school_year`: năm học mặc định.
- `website`: website trường.
- `cds_title`, `cds_short_title`: tên hiển thị PWA/CDS.
- `address`, `phone`, `email`, `logo`: bổ sung khi cần.
- `levels`: trường có THCS/THPT hay không.
- `modules`: các module được dự kiến sử dụng.

Không đặt mật khẩu database, OAuth secret hoặc thông tin bí mật trong tệp này.

## 2. Database riêng

CDS đọc cấu hình MySQL từ tệp ngoài web root. Mặc định:

`/home/capnachi/cds_private/database.conf`

Có thể chỉ định đường dẫn khác qua biến môi trường `CDS_DB_CONFIG`.

Khi triển khai trường mới:

1. Tạo database mới.
2. Tạo user database mới.
3. Tạo file `database.conf` riêng cho trường mới.
4. Không dùng chung database dữ liệu thật giữa hai trường.

## 3. Domain và đường dẫn deploy

`.cpanel.yml` hiện gắn với đường dẫn host của bản Xín Mần. Khi tạo repo/bản triển khai cho trường khác phải thay toàn bộ đích deploy sang thư mục host mới.

Kiểm tra thêm các script trong `tools/` và các shell script deploy cũ trước khi sử dụng trên host mới.

## 4. Dữ liệu Chuyên môn ban đầu

`chuyenmon/includes/config.php` vẫn chứa các bộ dữ liệu mặc định phục vụ khởi tạo cũ gồm:

- `$DEFAULT_TEACHERS`
- `$DEFAULT_CLASSES`
- `$DEFAULT_GROUPS`
- `$DEFAULT_SUBJECTS`
- `$DEFAULT_ROLES`

Các giá trị này **không phải cấu hình nhận diện trường** và chưa được tự động thay khi đổi `school_config.php`.

Khi tạo trường mới, ưu tiên khởi tạo database/dữ liệu rỗng rồi nhập giáo viên, lớp, tổ, môn theo trường mới. Không mang dữ liệu thật của Xín Mần sang trường khác.

## 5. PWA, icon và logo

`manifest.webmanifest` hiện còn tên nhận diện Xín Mần ở dạng tĩnh. Khi nhân bản cần đổi:

- `name`
- `short_name`
- icon trong `assets/icons/` nếu trường muốn dùng nhận diện riêng.

## 6. Google Drive và tích hợp ngoài

Mỗi trường nên dùng cấu hình Drive/OAuth riêng. Kiểm tra các thành phần:

- `drive_settings.php`
- `includes/google_drive_storage.php`
- `includes/drive_google_doc.php`
- các callback/redirect URI trong Google Cloud.

Không sao chép token, client secret hoặc thư mục Drive dùng thật của trường cũ sang trường mới.

## 7. Kiểm tra chuỗi gắn cứng trước khi mở hệ thống

Trước khi đưa trường mới vào sử dụng, tìm toàn source các chuỗi:

- `Xín Mần`
- `noitruxinman.edu.vn`
- `/home/capnachi/`
- tên trường đầy đủ hiện tại

Các chuỗi còn lại có thể thuộc: footer, bản in, script deploy cũ, fallback tương thích hoặc tài liệu. Phải kiểm tra ngữ cảnh trước khi thay, không replace hàng loạt mù quáng.

## 8. Quy trình triển khai an toàn đề xuất

1. Sao chép repo/bản source sang môi trường mới.
2. Tạo database và file cấu hình database riêng.
3. Sửa `includes/school_config.php`.
4. Sửa đường dẫn deploy/domain.
5. Sửa PWA/logo/icon nếu cần.
6. Khởi tạo tài khoản quản trị mới.
7. Nhập dữ liệu lớp, học sinh, giáo viên, tổ, môn.
8. Thiết lập năm học/tuần học.
9. Thiết lập PCCM và TKB.
10. Cấu hình Drive nếu sử dụng.
11. Kiểm tra toàn bộ báo cáo/bản in trước khi vận hành chính thức.

## Ghi chú kiến trúc

Từ bản chuẩn hóa này, `includes/config.php` và `chuyenmon/includes/config.php` đều lấy **tên trường và cơ quan chủ quản** từ `includes/school_config.php`. Các giá trị Xín Mần hiện tại được giữ nguyên để việc triển khai bản đang chạy không thay đổi hành vi.
