# Kho tài khoản iOffice trên cPanel

Phần này chỉ lưu tài khoản mã hóa. Chưa cài bộ tự đăng nhập, đọc văn bản hay gửi thông báo iOffice.
Không khởi động lại tác vụ ChatGPT dựa trên kho này: đó là hai môi trường riêng.

## 0. Cập nhật mã nguồn lên hosting

Trong cPanel → Git Version Control, chọn repository CDS → Manage → Pull or Deploy → Update from Remote, sau đó Deploy HEAD Commit. Quy trình triển khai chép `ioffice_settings.php`, `bin/ioffice-vault.php` và `includes/ioffice_vault.php` sang thư mục website; giữ nguyên dữ liệu hiện có. Nếu trang báo 404, kiểm tra ba tệp này đã có trong document root của tên miền CDS và nhật ký triển khai có `CDS_DEPLOY_OK`. Chỉ Pull mã nguồn chưa đủ nếu repository và thư mục website tách riêng.

## 1. Kiểm tra hosting

- Cần PHP 8+ và extension Sodium; bật trong PHP Selector hoặc nhờ nhà cung cấp.
- Cần HTTPS tại máy chủ gốc để mở `ioffice_settings.php`.
- Để chạy bộ thu thập sau này, hỏi nhà cung cấp có cho phép Node.js, Chromium/Playwright,
  tiến trình nền và Cron Jobs không. `proc_open` có mặt chưa chứng minh Chromium chạy được.
- Nếu không có Terminal/SSH, nhờ kỹ thuật hosting thực hiện khởi tạo kho bên dưới.
  Không tạo khóa bằng trang web công cộng và không gửi mật khẩu qua yêu cầu hỗ trợ.

## 2. Khởi tạo kho trên chính hosting

Ví dụ bên dưới cần thay `CPANEL_USER` và đường dẫn CDS cho đúng máy chủ.
Chọn `/home/CPANEL_USER/cds_private/ioffice`, bên ngoài **tất cả** document root/subdomain,
không nằm trong `public_html`, repo hoặc thư mục được chia sẻ qua web/FTP công cộng.

Trong Terminal cPanel:

```bash
mkdir -p /home/CPANEL_USER/cds_private/ioffice
chmod 700 /home/CPANEL_USER/cds_private /home/CPANEL_USER/cds_private/ioffice
export CDS_IOFFICE_VAULT_DIR=/home/CPANEL_USER/cds_private/ioffice
cd /DUONG_DAN_THUC_TE_TOI_CDS
php bin/ioffice-vault.php init
php bin/ioffice-vault.php check
```

Lệnh `init` tạo `master.key` ngẫu nhiên với quyền 0600 và không ghi đè khóa có sẵn.
Không truyền tài khoản/mật khẩu qua tham số lệnh, shell history hay biến môi trường.

Cấu hình `CDS_IOFFICE_VAULT_DIR` cho PHP phục vụ website (PHP-FPM/Passenger/Apache tùy hosting),
và cho tiến trình CLI/Cron sau này. `export` trong Terminal chỉ có tác dụng ở phiên Terminal đó.
Nhờ kỹ thuật hosting thiết lập nếu cPanel không có tùy chọn biến môi trường PHP; không đặt tên
đường dẫn trong form. Kiểm tra lại trạng thái ở **Văn bản → Tài khoản iOffice**.

## 3. Nhập tài khoản

Đăng nhập CDS bằng quản trị viên, mở **Văn bản → Tài khoản iOffice** qua HTTPS.
Nhập tài khoản iOffice vào biểu mẫu, xác nhận cho phép lưu rồi bấm **Lưu tài khoản bảo mật**.
Chỉ quản trị viên truy cập được; POST có CSRF và trang không tải tài nguyên của bên thứ ba.
Không lưu bản rõ trong JSON cấu hình, log, phiên CDS hoặc Git. Phản hồi không điền lại mật khẩu.
Thông báo “Đã lưu” chỉ xác nhận mã hóa và ghi tệp; không xác nhận đăng nhập iOffice thành công.
Muốn thay tài khoản, nhập lại cả tên và mật khẩu.

`account.enc` mã hóa bằng Sodium secretbox, nonce ngẫu nhiên mới mỗi lần ghi, kèm kiểm tra tính toàn vẹn.
Khóa nằm riêng trong `master.key`; thư mục 0700, tệp 0600. Không thay khóa thủ công khi đã có dữ liệu.
Đây là kho mã hóa cục bộ trong tài khoản cPanel, không phải dịch vụ KMS độc lập:
người chiếm được tài khoản cPanel hoặc thực thi PHP trong CDS có thể đọc cả khóa và dữ liệu.
Hạn chế người được quản trị hosting; sao lưu khóa và dữ liệu bằng bản sao lưu được bảo vệ,
không đưa bản sao vào repo/web root. Xác minh không có alias web trỏ vào kho.

## 4. Kết nối bộ thu thập (bước sau)

Tiến trình tin cậy có thể gọi `cds_ioffice_vault_read()` từ PHP nội bộ để dùng tài khoản.
Không tạo HTTP endpoint trả bản rõ, không ghi kết quả vào log hoặc truyền mật khẩu qua argv.
Chưa có tiến trình nào trong thay đổi này đọc kho để đăng nhập ra ngoài.
Khi hosting hỗ trợ bộ thu thập, mới cấu hình lịch 08:00, 11:00, 16:30 theo Asia/Ho_Chi_Minh,
lọc hôm nay và hai ngày trước, chống trùng theo ID và kết nối thông báo CDS.
Không vượt CAPTCHA/OTP; báo cần xác thực khi có yêu cầu.

## Kiểm thử

```bash
php tests/ioffice_vault_test.php
php tests/ioffice_settings_test.php
```

Kiểm thử dùng thư mục tạm và tài khoản giả; không cần dữ liệu thật.
