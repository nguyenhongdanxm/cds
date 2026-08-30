# HƯỚNG DẪN NHÂN BẢN CDS SANG TRƯỜNG KHÁC

Tài liệu này dùng cho mô hình **mỗi trường một source + một database riêng**.

## 1. Thông tin nhận diện nhà trường

Từ giai đoạn 3, **không cần sửa trực tiếp source** để đổi thông tin trường. CDS hỗ trợ tệp cấu hình JSON riêng nằm ngoài web root.

Đường dẫn mặc định:

`/home/capnachi/cds_private/school.json`

Hoặc có thể đặt biến môi trường `CDS_SCHOOL_CONFIG` trỏ tới một tệp JSON khác.

Mẫu tham khảo trong repo:

`docs/school.example.json`

Các trường có thể cấu hình:

- `code`: mã trường nội bộ.
- `name`: tên trường đầy đủ.
- `short_name`: tên trường rút gọn.
- `department`: cơ quan chủ quản.
- `school_year`: năm học mặc định.
- `website`: website trường.
- `cds_title`, `cds_short_title`: tên hiển thị PWA/CDS.
- `address`, `phone`, `email`, `logo`: thông tin nhận diện.
- `levels`: trường có THCS/THPT hay không.
- `modules`: module dự kiến sử dụng.
- `pwa`: màu nền, màu giao diện và icon PWA.

Nếu `school.json` không tồn tại, CDS tự dùng cấu hình Xín Mần có sẵn trong source. Vì vậy việc cập nhật source hiện tại không làm thay đổi hệ thống đang chạy.

Không đặt mật khẩu database, OAuth secret hoặc thông tin bí mật trong `school.json`.

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

Dữ liệu mẫu lịch sử của Xín Mần đã được tách khỏi core Chuyên môn sang:

`chuyenmon/includes/defaults_xinman.php`

Tệp này chỉ chứa các giá trị fallback phục vụ khởi tạo ban đầu:

- giáo viên mẫu;
- lớp mẫu;
- tổ chuyên môn mẫu;
- môn học và số tiết mẫu;
- vai trò/kiêm nhiệm mẫu.

`chuyenmon/includes/config.php` chỉ nạp các giá trị này thành các biến `$DEFAULT_*`. Cơ chế này **không đọc, sửa, xóa hoặc ghi lại** các file dữ liệu đang vận hành trong `chuyenmon/data`.

Với trường mới nên đặt `chuyenmon.seed_profile = "empty"` trong `school.json`, hoặc cấu hình `chuyenmon.defaults_file` / biến môi trường `CDS_CHUYENMON_DEFAULTS` tới file JSON khởi tạo riêng.

## 5. PWA, icon và logo

PWA chính đã chuyển sang `manifest.php`, lấy tên trường và nhận diện từ cấu hình trường.

`manifest.webmanifest` cũ vẫn được giữ lại tạm thời để tương thích với các bản cũ, nhưng trang đăng nhập hiện sử dụng `manifest.php`.

Icon mặc định nằm trong `assets/icons/`. Trường mới có thể thay icon hoặc cấu hình đường dẫn icon trong `school.json`.

## 6. Google Drive và tích hợp ngoài

Cấu hình Google Drive có `private_key` của Service Account nên nên tách riêng cho từng trường và đặt ngoài web root.

CDS hiện chọn file cấu hình Drive theo thứ tự:

1. Biến môi trường `CDS_DRIVE_CONFIG` nếu có.
2. `school.json` → `integrations.drive_settings_file` nếu có.
3. Nếu không có hai cấu hình trên, tiếp tục dùng vị trí cũ `DATA_PATH/google_drive_settings.json` để giữ tương thích với hệ thống Xín Mần đang chạy.

Ví dụ cho trường mới:

`/home/USER/cds_private/TRUONG_MOI/google_drive_settings.json`

Không đưa file này lên GitHub vì có thể chứa:

- email Service Account;
- private key;
- ID các thư mục Google Drive;
- trạng thái bật/tắt lưu Drive.

Mỗi trường phải dùng Service Account/thư mục Drive riêng hoặc ít nhất phải được phân quyền rõ ràng trên Shared Drive riêng. Không sao chép file cấu hình Drive thật của Xín Mần sang trường khác.

Các thành phần liên quan:

- `drive_settings.php`
- `includes/google_drive_storage.php`
- `includes/drive_google_doc.php`

Nếu sau này có OAuth callback riêng, phải kiểm tra và đổi redirect URI/domain theo deployment của từng trường trước khi bật.

## 7. Kiểm tra chuỗi gắn cứng trước khi mở hệ thống

Trước khi đưa trường mới vào sử dụng, tìm toàn source các chuỗi:

- `Xín Mần`
- `noitruxinman.edu.vn`
- `/home/capnachi/`
- tên trường đầy đủ hiện tại

Các chuỗi còn lại có thể thuộc: fallback tương thích, dữ liệu khởi tạo cũ, script deploy, tài liệu hoặc đường dẫn máy chủ thật. Phải kiểm tra ngữ cảnh trước khi thay, không replace hàng loạt.

## 8. Quy trình triển khai an toàn đề xuất

1. Sao chép source sang môi trường mới.
2. Tạo database mới và `database.conf` riêng.
3. Tạo `school.json` từ `docs/school.example.json`.
4. Đặt `chuyenmon.seed_profile = "empty"` hoặc bộ defaults riêng.
5. Sửa đường dẫn deploy/domain của bản trường mới.
6. Thay logo/icon nếu cần.
7. Khởi tạo tài khoản quản trị mới.
8. Nhập dữ liệu lớp, học sinh, giáo viên, tổ, môn.
9. Thiết lập năm học/tuần học.
10. Thiết lập PCCM và TKB.
11. Tạo file Drive riêng ngoài web root và cấu hình Service Account/thư mục riêng nếu sử dụng.
12. Kiểm tra toàn bộ báo cáo/bản in trước khi vận hành chính thức.

## 9. Nguyên tắc an toàn khi cập nhật core

- Không dùng chung database dữ liệu thật giữa các trường.
- Không commit `school.json`, `database.conf`, `google_drive_settings.json`, token hoặc secret vào repo.
- Core có thể tiếp tục cập nhật từ GitHub mà không cần ghi đè thông tin nhận diện trường nếu thông tin riêng nằm ngoài source.
- Nếu `school.json` bị thiếu hoặc JSON lỗi, hệ thống quay về cấu hình mặc định trong source thay vì dừng toàn bộ CDS.
- Dữ liệu mẫu trong `defaults_xinman.php` chỉ là fallback khởi tạo, không phải dữ liệu vận hành.
- Cấu hình Drive ngoài source là opt-in; nếu không khai báo, hệ thống hiện tại vẫn dùng đúng đường dẫn cũ.
- Chưa bật cơ chế đa tenant dùng chung database trong giai đoạn này.

## Ghi chú kiến trúc

CDS hiện đã tách rõ bốn lớp:

- **Core/source dùng chung:** mã xử lý hệ thống.
- **Cấu hình nhận diện deployment:** `school.json` ngoài web root.
- **Cấu hình dữ liệu/kết nối riêng:** `database.conf`, `google_drive_settings.json` và các secret nằm ngoài web root.
- **Dữ liệu mẫu lịch sử Xín Mần:** `chuyenmon/includes/defaults_xinman.php`, chỉ phục vụ fallback khởi tạo.

Cách này giúp nhân bản CDS sang trường khác mà giảm nguy cơ dùng nhầm database, Google Drive hoặc dữ liệu mẫu của Xín Mần, đồng thời giữ nguyên cơ chế vận hành và dữ liệu thật hiện tại.
