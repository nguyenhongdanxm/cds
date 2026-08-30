# TRIỂN KHAI CDS SANG TRƯỜNG KHÁC KHÔNG SỬA CODE

Mục tiêu: sao chép nguyên bộ source CDS sang môi trường khác và **chỉ cấu hình**, không chỉnh PHP/JS/YAML trong source.

## 1. Cấu hình đường dẫn deploy

CDS dùng `tools/deploy_cds.php`. `.cpanel.yml` chỉ gọi script này và không còn chứa trực tiếp đường dẫn host của từng trường.

Thứ tự xác định thư mục deploy:

1. Biến môi trường `CDS_DEPLOY_PATH`.
2. File JSON do `CDS_DEPLOY_CONFIG` chỉ định.
3. `/home/capnachi/cds_private/deploy.json`.
4. Nếu không có cấu hình nào, hệ thống dùng đường dẫn Xín Mần cũ để giữ tương thích với hệ thống hiện tại.

Mẫu `deploy.json`:

```json
{
  "target_path": "/home/USER/example.edu.vn"
}
```

Mẫu có sẵn trong repo: `docs/deploy.example.json`.

## 2. Cấu hình nhận diện trường

Dùng file `school.json` ngoài web root hoặc biến môi trường `CDS_SCHOOL_CONFIG`.

Mẫu: `docs/school.example.json`.

Không cần sửa `includes/config.php`, `manifest.php`, `login.php` hay các file core để đổi tên trường, Sở, năm học, website, logo, module, cấp học, PWA.

## 3. Database riêng

Dùng `database.conf` riêng và cấu hình qua `CDS_DB_CONFIG` nếu không dùng vị trí mặc định.

Không sửa thông tin database trực tiếp trong source.

## 4. Google Drive riêng

Dùng `CDS_DRIVE_CONFIG` hoặc `integrations.drive_settings_file` trong `school.json` để trỏ đến file cấu hình Drive riêng ngoài web root.

Không sửa `includes/google_drive_storage.php`.

## 5. Dữ liệu khởi tạo Chuyên môn

Trường mới nên đặt:

```json
"chuyenmon": {
  "seed_profile": "empty",
  "defaults_file": ""
}
```

Hoặc dùng `CDS_CHUYENMON_DEFAULTS` để trỏ đến file JSON dữ liệu mẫu riêng.

Không sửa `chuyenmon/includes/config.php`.

## 6. Bộ cấu hình tối thiểu cho một trường mới

Một deployment mới chỉ cần chuẩn bị các file cấu hình ngoài source:

- `deploy.json`
- `school.json`
- `database.conf`
- `google_drive_settings.json` nếu dùng Drive
- file defaults Chuyên môn nếu muốn khởi tạo sẵn dữ liệu

Sau đó copy cùng một source CDS và deploy. Không cần thay chuỗi tên trường, domain, đường dẫn host hay dữ liệu mẫu trong mã nguồn.

## 7. An toàn với bản Xín Mần hiện tại

Nếu chưa tạo `deploy.json` và chưa đặt `CDS_DEPLOY_PATH`, script deploy vẫn dùng đường dẫn cũ:

`/home/capnachi/cds.noitruxinman.edu.vn`

Do đó thay đổi kiến trúc deploy không làm đổi đích triển khai hiện tại của Xín Mần.
