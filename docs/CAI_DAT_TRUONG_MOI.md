# Cài CDS cho trường mới

Dùng **một website/domain và một database riêng cho mỗi trường**. Không copy thư mục trường đang chạy. Quy trình này không yêu cầu sửa PHP; quản trị hosting cần quyền chạy PHP CLI/cPanel Terminal cho bước khởi tạo một lần.

## 1. Chuẩn bị bộ mã sạch

Dùng bản Git đã kiểm tra hoặc tạo ZIP từ một checkout sạch, đã commit:

```bash
php tools/build_school_release.php --output=/DUONG_DAN_RIENG/cds-release.zip
```

Gói có `release-manifest.json` và checksum cho các tệp. Công cụ chỉ lấy mã nguồn được Git theo dõi, loại data, uploads riêng, cấu hình máy chủ, khóa, nhật ký và binding của trường. Cần PHP zip và quyền chạy Git. ZIP này không phải bản sao lưu dữ liệu trường đang dùng.

Giải nén vào document root của domain mới. Không dùng chung database, thư mục Drive, tài khoản dịch vụ hoặc thư mục dữ liệu giữa các trường. Không mặc định hỗ trợ nhiều trường dưới các thư mục con cùng domain.

## 2. Khởi tạo trường và quản trị

Chọn một đường dẫn cấu hình riêng **ngoài mọi document root** trên hosting. Thay các giá trị trong lệnh dưới đây:

```bash
php tools/setup_school.php \
  --config=/home/USER/cds_private/TRUONG_A/instance.json \
  --code=TRUONG_A \
  --name='Trường THCS A' \
  --short='THCS A' \
  --year=2026-2027 \
  --department='Cơ quan quản lý' \
  --place='Địa danh'
```

Lệnh tạo một tài khoản quản trị với mật khẩu ngẫu nhiên, in mật khẩu **một lần** tại terminal. Lưu riêng và đổi theo nhu cầu tại quản lý tài khoản. Có thể cấp mật khẩu riêng qua biến môi trường `CDS_SETUP_PASSWORD` (12–72 byte); không ghi mật khẩu trong câu lệnh hoặc commit vào Git. Khi cấp qua môi trường, công cụ không in lại mật khẩu.

Công cụ không ghi đè cấu hình/tài khoản/dữ liệu đã có. Nếu là bản nâng cấp, **không chạy setup**. Truy cập website không tự tạo lại tài khoản với mật khẩu chung khi kho tài khoản thiếu.

Binding cục bộ `includes/instance_binding.php` chỉ chứa đường dẫn tới cấu hình riêng, bị loại khỏi Git và gói triển khai. Web và CLI cùng dùng binding này. Không chuyển binding sang trường khác. Nếu hosting đã đặt `CDS_INSTANCE_CONFIG`, biến đó được ưu tiên; phải đặt đúng cho cả web và CLI hoặc bỏ biến để dùng binding. Không xóa file binding sau khi cài.

## 3. Cấu hình trên giao diện

Đăng nhập, mở **Cấu hình trường** (`/instance_settings.php`):

1. Kiểm tra tên, logo, mã trường, năm học và cấp THCS/THPT.
2. Nhập database riêng; chỉ lưu sau khi kết nối kiểm tra thành công.
3. Mở **Biểu mẫu, bản in và file xuất**: tên trường/cơ quan trên biểu mẫu (để trống để theo thông tin trường), địa danh, gợi ý người ký/chức danh và chân trang Chuyên môn.
4. Giữ **Trường mới – dữ liệu rỗng**. Chỉ bật module cần sử dụng.
5. Kết nối Drive/AI/iOffice riêng nếu dùng. OAuth cần khai báo domain chuyển hướng của trường mới ở nhà cung cấp.
6. Mở **Khởi tạo dữ liệu** (`/initial_setup.php`): migration, năm học, giáo viên, lớp, học sinh, môn học, kiêm nhiệm và tài khoản/quyền.

Bật/tắt module chỉ thay hiển thị, không thay phân quyền. Cấp quyền trên trang tài khoản theo đúng người/nhóm. Các người ký đã nhập trong hồ sơ, mẫu thẻ đã thiết kế và báo cáo đã lưu không bị thay thế tự động bằng gợi ý mới. Kiểm tra lại nếu nhập lại mẫu cũ từ trường khác.

## 4. Biểu mẫu đã dùng nhận diện chung

Đã chuyển các tên/địa danh cố định được phát hiện trong bìa sổ đầu bài, phiếu dự giờ, bản in phân phòng/mâm, báo cáo trực, lịch trực Word, Excel ăn tháng, bản xuất ăn/ngày/gạo, danh mục thư viện, TKB, mặc định thẻ HS/CBGV và ảnh thống kê sang cấu hình/nguồn dữ liệu hiện hành. Metadata tác giả XLSX và liên kết vào trò chơi không còn gắn domain trường gốc.

Đây là thay thông tin trình bày; không thay công thức, tiêu chí, dữ liệu ký hoặc nội dung biểu mẫu theo nghiệp vụ. Hồ sơ lịch sử có tên trường/người ký/năm học lưu riêng vẫn giữ nội dung đó. Không tự thay tên trong nội dung Word/PDF/ảnh do người dùng tải lên. Logo, ảnh cổng trường, hình nền/ảnh minh họa có chữ được vẽ sẵn phải thay tài nguyên phù hợp nếu cần; chữ đã nằm trong điểm ảnh không đổi theo cấu hình văn bản.

Tên trường/cơ quan dài được xuống dòng trong vùng tiêu đề hiện hành. Khi nghiệm thu cần in thử với tên trường thực tế; không thể bảo đảm mọi độ dài tên trên mọi cỡ giấy chỉ bằng kiểm tra mã nguồn.

## 5. Bảo vệ dữ liệu và kiểm tra trước khi dùng

Apache cần cho phép `.htaccess`; mã có quy tắc chặn `/data`, `/chuyenmon/data` và các file cấu hình/backup. Setup cũng tạo deny trong hai kho dữ liệu. Với Nginx, quản trị hosting phải đặt quy tắc tương đương, ví dụ:

```nginx
# Keep older saved game/attachment URLs working through validated endpoints.
rewrite ^/data/game_assets/(wheel_music|duck_race_music|olympia_stage)/([^/]+)$ /game_asset.php?group=$1&name=$2 last;
rewrite ^/chuyenmon/data/uploads/([^/]+)$ /chuyenmon/cm_file.php?path=uploads/$1 last;
rewrite ^/data/uploads/([^/]+)$ /notice_file.php?path=uploads/$1 last;
location ~* ^/(data|chuyenmon/data|cds_private)(/|$) { deny all; }
location ~* \.(conf|sql|bak|log|pem|key)$ { deny all; }
location = /release-manifest.json { deny all; }
```

Dùng file giả kiểm tra HTTP trả **403/404** trước khi nhập dữ liệu thật. Không kết luận đã bảo vệ chỉ vì file bị Git bỏ qua. Cấu hình PHP, database và thư mục dữ liệu phải có quyền ghi đúng cho tài khoản chạy web; CLI và web nên chạy cùng chủ sở hữu hoặc được quản trị hosting cấp quyền an toàn.

Nghiệm thu: đăng nhập, quyền URL trực tiếp, nhập Excel, phân công → công bố → TKB → ký sổ → lọc/thống kê/xuất; thử các module được bật và in các biểu mẫu sử dụng. Kiểm tra tên trường/cơ quan, địa danh, năm học, người ký, logo, phân trang và tiếng Việt.

## 6. Cập nhật và sao lưu

Nếu triển khai từ checkout quản lý mã riêng với website đích, đặt đường dẫn cấu hình của trường đích bằng `CDS_INSTANCE_CONFIG` hoặc `CDS_DEPLOY_PATH` rõ ràng. Xem kế hoạch trước:

```bash
CDS_INSTANCE_CONFIG=/home/USER/cds_private/TRUONG_A/instance.json php tools/deploy_cds.php --dry-run
CDS_INSTANCE_CONFIG=/home/USER/cds_private/TRUONG_A/instance.json php tools/deploy_cds.php
```

Không còn đích Xín Mần tự động khi thiếu cấu hình. Deploy không sao chép runtime hoặc binding đích; chỉ cập nhật khối quy tắc CDS trong `.htaccess`, giữ các quy tắc riêng của hosting. Các tệp đính kèm Chuyên môn đi qua kiểm tra quyền/hồ sơ; nhạc/ảnh trò chơi công khai đi qua danh sách thư mục và loại tệp cho phép. Không lấy file cấu hình/database từ máy xây dựng để ghi đè website. Không giải nén gói vào thư mục chứa dữ liệu thật theo kiểu xóa sạch rồi chép lại.

Sao lưu cả database, JSON còn dùng, uploads, cấu hình ngoài web root, binding, khóa/tích hợp và trạng thái SQL primary; thử phục hồi riêng. SQL primary đang bật thì JSON có thể chỉ là bản dự phòng cũ. Không tắt marker hoặc bật SQL primary ở trường mới chỉ vì thấy database đã kết nối. Chỉ kích hoạt bằng quy trình kiểm tra/đối chiếu của chức năng tương ứng.

Tự động sao lưu/phục hồi toàn hệ thống và kiểm thử tải nhiều năm vẫn cần quy trình vận hành riêng theo hosting. Bộ cài này không tuyên bố đã thay thế các bước nghiệm thu đó.
