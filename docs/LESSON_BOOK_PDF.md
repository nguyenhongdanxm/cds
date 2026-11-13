# Tự động lưu PDF sổ đầu bài trên cPanel có Node.js

Bộ tạo PDF dùng **chính `chuyenmon/sodaubai_print.php`** (mẫu của nút “In / Lưu PDF”), không chuyển Excel thành PDF. Mỗi lớp/tuần có một tệp `.pdf`. Bố cục A3 ngang hoặc hai trang A4, buổi học, bìa và tùy chọn chỉ tiết có dữ liệu được lưu cùng lịch. Tên GVCN, chữ ký, nhận xét GVCN/BGH, người ký lãnh đạo và lề chẵn/lẻ lấy theo dữ liệu/cấu hình bản in hiện tại ở mỗi lượt chạy.

## Cài một lần

Có mục Setup Node.js App mới chỉ xác nhận có môi trường Node. Cần chạy thử Chromium trên hosting trước khi bật lịch.

1. Tạo môi trường Node.js **22.12 trở lên hoặc 24** trong cPanel. Dùng một thư mục ứng dụng riêng, ví dụ `/home/USER/cds_private/lesson-book-pdf`, nằm ngoài `public_html`. Không cần bật dịch vụ web công khai; bộ này chạy bằng CLI từ cron.
2. Đưa ba tệp trong `tools/lesson-book-pdf/` của bản sửa (`package.json`, `package-lock.json`, `render.cjs`) vào thư mục ứng dụng này. Mã renderer khi xuất thật vẫn chạy từ bản triển khai website; thư mục ứng dụng cung cấp thư viện và trình duyệt.
3. Trong Terminal cPanel, dùng lệnh kích hoạt môi trường mà cPanel hiển thị, chuyển vào thư mục ứng dụng rồi chạy:

   ```bash
   PUPPETEER_SKIP_DOWNLOAD=true npm ci
   npx puppeteer browsers install chrome-headless-shell
   node -p 'process.execPath'
   npm run check
   ```

   Lưu đường dẫn Node được in ra. Chromium tải vào cache của tài khoản hosting; dùng cùng tài khoản và HOME khi chạy cron. Không đưa `node_modules` hoặc bộ Chromium lên GitHub. Nếu hosting thiếu thư viện hệ thống/không cho chạy binary Chromium, gửi thông báo kiểm tra cho nhà cung cấp hosting để họ bật hỗ trợ. Không báo cấu hình thành công chỉ vì npm cài xong.
4. Thêm khóa `pdf` vào **cấu hình instance hiện tại ngoài web root**, giữ nguyên các phần database/drive/school đã có:

   ```json
   "pdf": {
     "node_binary": "/home/USER/nodevenv/lesson-book-pdf/22/bin/node",
     "node_modules": "/home/USER/cds_private/lesson-book-pdf/node_modules",
     "chromium_path": "",
     "no_sandbox": false
   }
   ```

   Thay bằng đường dẫn thực tế từ bước 3. Nếu hosting cung cấp Chrome/Chromium riêng, điền đường dẫn executable vào `chromium_path`; để trống để dùng chrome-headless-shell đã tải bằng Puppeteer. Một số cPanel không hỗ trợ Chromium sandbox: chỉ khi gặp lỗi sandbox và nhà cung cấp yêu cầu, đặt `no_sandbox: true`. Không cần mở quyền truy cập dữ liệu từ Internet hoặc chia sẻ tài khoản Google.
5. Sau khi triển khai bản sửa lên website, chạy bằng **PHP CLI 8.2/8.3** của hosting:

   ```bash
   /opt/alt/php83/usr/bin/php /home/USER/public_html/bin/lesson-book-pdf-check.php
   ```

   Thành công trả JSON `{"ok":true,...}` sau khi đã mở Chromium và thực sự tạo một PDF thử trong bộ nhớ. Nếu báo `PDF_DEPENDENCIES_MISSING`, kiểm tra `node_modules`; `PDF_CHROMIUM_UNAVAILABLE` là lỗi trình duyệt/cache/thư viện hệ thống. Nếu không có `proc_open`, cần nhà cung cấp bật cho PHP CLI. Bản PDF dùng font có trên hosting (Times New Roman nếu có, hoặc font serif thay thế hỗ trợ tiếng Việt); thiếu font tiếng Việt cần cài font cho tài khoản/máy chủ trước.

## Bật và chạy thử

- Trong **Sổ đầu bài → Tự động lưu sổ hằng tuần**, chọn thư mục Drive, lớp và tùy chọn bản in, rồi bấm bật. Mặc định A3 ngang, tất cả buổi, có bìa, mỗi Chủ nhật 23:00–23:30 giờ Việt Nam.
- Cron PHP hiện có `bin/excel-drive-cron.php` vẫn chạy **mỗi 5 phút**. Không cần cron Node riêng. Node chạy theo yêu cầu của PHP rồi kết thúc.
- Bấm **Chạy thử** ở một lớp, chờ lượt cron tiếp theo và mở PDF từ lịch sử. Đối chiếu bản in thủ công của cùng lớp, cùng tuần, cùng tùy chọn. Sau đó mới kiểm tra các lớp còn lại.
- Lưu theo `Năm học …/Tuần …/Sổ đầu bài_Lớp 6A_Tuần 10.pdf`. Chạy lại cập nhật cùng tệp; các tuần cũ được giữ. Nếu đổi tùy chọn trên lịch có sẵn, dùng Sửa; nút tạo nhiều lớp giữ nguyên lịch đã có để tránh tạo trùng.

## Cách xuất

PHP worker dùng tài khoản quản trị của lịch, tự chọn đúng lớp/tuần và chạy trang in tại máy chủ để lấy HTML. Chữ ký được nhúng vào HTML. Chromium nhận HTML cục bộ, tắt JavaScript, chặn tài nguyên mạng/file bên ngoài và in theo CSS `@page`/`@media print` của trang. Không gửi HTML hoặc dữ liệu học sinh đến dịch vụ PDF bên thứ ba; trình duyệt cũng không cần cookie đăng nhập website. Chỉ PDF hợp lệ mới được tải lên Drive. HTML/PDF tạm và phiên worker được xóa khi hoàn tất hoặc thất bại, tệp tồn đọng do tiến trình bị dừng được dọn ở lượt cron sau.

Không tự chuyển các lịch Excel khác sang PDF. Không đổi dữ liệu hoặc chữ ký gốc. Không dùng tuần ISO hoặc âm thầm chuyển sang tuần khác khi lịch tuần/năm học không khớp. Không tạo PDF “thành công” từ trang đăng nhập, trang lỗi hoặc phạm vi không có dữ liệu.
