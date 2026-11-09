# Rà soát bộ khung CDS để triển khai cho trường khác

Ngày: 09/11/2026. Mã nguồn đối chiếu: `caa314c008e1e29f1abf843bcb7c2df8a9a2e03f`.

## Kết luận

CDS có nền tảng để tái sử dụng, nhưng **chưa nên lấy nguyên thư mục của trường đang chạy để làm bộ cài cho trường mới**. Cần chuẩn hóa đường dẫn cấu hình, khởi tạo quản trị, đóng gói và kiểm tra cách ly trước khi phát hành bộ khung.

Mô hình triển khai đề nghị hiện tại: **mỗi trường một website/domain, một database, một kho dữ liệu và một bộ cấu hình riêng**, cùng dùng một phiên bản mã nguồn. Chưa coi đây là hệ thống nhiều trường dùng chung database. Cài dưới thư mục con của cùng domain cần kiểm chứng riêng; mã hiện tại có URL gốc `/` và cookie đường dẫn `/`.

Đây là rà soát mã nguồn và một số kiểm tra tái hiện cô lập. Không phải chứng nhận an toàn thông tin, kiểm toán đầy đủ mọi API, kiểm thử tải toàn hệ thống hoặc đối chiếu toàn bộ dữ liệu production trước/sau MySQL. Không chạy migration, sửa dữ liệu, đổi quyền hoặc thay thuật toán vận hành trong lần rà soát này.

## Những phần có thể giữ làm nền

- Có cấu hình hợp nhất `instance.json`, giao diện `instance_settings.php`, mẫu cấu hình và trang hướng dẫn khởi tạo `initial_setup.php`.
- Cấu hình hợp nhất có thể đặt ngoài web root; hàm lưu dùng file tạm rồi đổi tên, giới hạn quyền thư mục/tệp.
- Có lựa chọn khởi tạo Chuyên môn rỗng và bộ danh mục riêng; cấu hình cấp THCS/THPT.
- Không có file dữ liệu vận hành được Git theo dõi trong `data/` và `chuyenmon/data/` tại phiên bản được kiểm tra.
- Có cơ chế migration, phân quyền và các bộ kiểm thử. Ghi nhận 167 tệp trong `tests/`, 44 tệp workflow; số lượng không đồng nghĩa tất cả chức năng đã được kiểm chứng.
- SQL primary của sổ đầu bài và báo ăn có kiểm tra đúng phạm vi kho dữ liệu, không trả về JSON cũ khi database lỗi. Hai kiểm tra bảo vệ này đã chạy đạt trong lần rà soát.

## Các điểm cần xử lý trước khi phát hành

| Mức | Phát hiện và bằng chứng | Hệ quả khi nhân bản | Cách chuẩn hóa / điều kiện đạt |
| --- | --- | --- | --- |
| Chặn phát hành | `includes/instance_config.php:9`, `includes/school_config.php:28`, `includes/database.php:11`: đường dẫn mặc định neo vào thư mục cha của bản cài. Hai bản cài cạnh nhau có thể trỏ cùng `cds_private/instance.json`, `school.json`, `database.conf`. | Nhận diện hoặc kết nối database có thể bị dùng lẫn nếu thiếu cấu hình riêng. | Bắt buộc đường dẫn cấu hình tuyệt đối riêng cho từng trường, ngoài web root; không đọc fallback của trường khác. Web và CLI phải đọc cùng một cấu hình. Kiểm tra với hai bản cài cạnh nhau. |
| Chặn phát hành | `tools/deploy_cds.php:5,18,64`: deploy tìm cấu hình theo HOME, khác cách tìm của website; thiếu cấu hình vẫn có đích dự phòng riêng của hosting Xín Mần. | Chọn nhầm đích hoặc dùng nhầm cấu hình; có thể triển khai sang website cũ nếu tài khoản có quyền ghi. | Chế độ bộ cài mới phải dừng khi thiếu/sai đích; có xem trước danh sách tệp và đích triển khai. Chỉ giữ tương thích cũ bằng chế độ rõ ràng. |
| Chặn phát hành | `tools/deploy_cds.php:44,69`: sao chép đệ quy `includes`, `chuyenmon`, không lọc dữ liệu runtime/cấu hình cục bộ bên trong. | Nếu nguồn là thư mục đã vận hành, dữ liệu, uploads hoặc cấu hình có thể bị chép sang trường mới/ghi đè đích. | Đóng gói từ nguồn Git sạch theo danh sách cho phép; loại data, uploads riêng, secrets, cache, logs, marker SQL và file cấu hình máy chủ. Cập nhật mã phải giữ nguyên dữ liệu đích. |
| Chặn phát hành | `includes/config.php`, `includes/auth.php:120,199`: lần đầu tạo quản trị bằng mật khẩu mặc định cố định trong mã. `initial_setup.php:61` có cảnh báo nhưng chưa phải cổng bắt buộc đổi mật khẩu. | Nhiều bản cài có thể cùng thông tin đăng nhập ban đầu. | Tạo bí mật ngẫu nhiên theo bản cài hoặc lệnh khởi tạo một lần; bắt buộc đặt mật khẩu riêng trước khi mở sử dụng. Không đổi tài khoản của trường đang chạy khi nâng cấp. |
| Chặn nghiệm thu hosting | Dữ liệu gốc ở `data/`, `chuyenmon/data/` trong web root; không có bộ quy tắc chặn HTTP chung được Git theo dõi. Một số kho riêng có tự tạo `.htaccess`, nhưng không bảo vệ đồng nghĩa mọi kho. | `.gitignore` không ngăn tải dữ liệu qua HTTP. Mức bảo vệ thực tế phụ thuộc Apache/Nginx/hosting. Chưa kết luận hosting hiện tại bị lộ. | Ưu tiên dữ liệu nhạy cảm ngoài web root hoặc deny toàn bộ ở web server. Dùng file giả trên staging kiểm tra URL dữ liệu, backup, secrets trả 403/404; chỉ tải qua endpoint có kiểm tra quyền. |
| Cao | `chuyenmon/includes/seed_defaults.php:59`: thiếu cấu hình trả profile `xinman`. `includes/school_config.php:62` còn nhận diện/năm học dự phòng riêng. `includes/config.php:55` dò các kho PCCM cũ, kể cả đường dẫn hosting riêng. | Trường mới có thể nhận danh mục mẫu/nhận diện sai hoặc đọc kho PCCM khác nếu có trên máy chủ. | Chế độ trường mới mặc định rỗng và phải khai báo trường/năm học; bỏ dò kho ngoài bản cài trong chế độ này. Dữ liệu Xín Mần chỉ giữ trong profile tương thích rõ ràng. |
| Cao | `includes/config.php:27` cố định BASE_URL `/`; `includes/auth.php:68–93` dùng cấu hình cookie hiện hành và đường dẫn `/`, không đặt tên phiên theo trường trong đoạn khởi tạo này. | Chưa bảo đảm hai trường chạy dưới các thư mục con cùng domain độc lập về URL/phiên. Cấu hình PHP/hosting có thể thay đổi kết quả thực tế. | Giai đoạn đầu dùng domain riêng; nếu hỗ trợ thư mục con phải cấu hình base URL, tên/phạm vi cookie và kiểm thử đăng nhập cách ly. |
| Cao | Chưa thấy quy trình sao lưu/phục hồi toàn bộ trường có kiểm tra thực thi trong bộ công cụ được rà soát. SQL primary và JSON cùng tồn tại; số migration không phản ánh toàn bộ bảng tạo khi bật tính năng. | Chỉ sao chép source hoặc chỉ xuất database chưa đủ để phục hồi; JSON có thể là bản cũ khi SQL primary đã bật. | Sao lưu nhất quán database + JSON còn dùng + uploads + cấu hình/khóa riêng + trạng thái nguồn dữ liệu. Phục hồi vào môi trường cách ly và đối chiếu trước nghiệm thu. |

## Những điểm cần hiểu đúng để tránh mâu thuẫn

1. **Bật/tắt module và phân quyền là hai việc khác nhau.** Mẫu `docs/school.example.json` ghi rõ `modules=false` chỉ ẩn module, không thay thế quyền truy cập. Không quảng bá tính năng này là khóa truy cập; cần ma trận quyền và kiểm thử truy cập URL trực tiếp cho từng vai trò.
2. **MySQL không có nghĩa mọi đường xử lý đều đã tối ưu.** Cần kiểm kê theo kho và thao tác: nguồn ghi chính, nguồn đọc, chỉ mục, lọc/phân trang và xuất file. Đo tải lớn trên staging; không suy từ hai kiểm tra bảo vệ SQL ra hiệu năng toàn hệ sinh thái.
3. **Migration hoàn tất không đồng nghĩa khởi tạo xong.** Trang khởi tạo còn cần kiểm tra tài khoản, trường, năm học, seed, quyền, kho dữ liệu và tích hợp. Bảng báo ăn primary được tạo khi bật tính năng trong `includes/meal_primary.php`.
4. **Đổi tên trường không chuyển dữ liệu giữa trường.** Không dùng thao tác đổi nhận diện để biến database cũ thành database trường mới.
5. **Cấp học hiện có không phải mọi loại hình trường.** Bộ khởi tạo đã thấy hỗ trợ THCS/THPT. Trường tiểu học hoặc mô hình khác phải kiểm tra nghiệp vụ và biểu mẫu riêng, không chỉ thêm tên cấp học.
6. **Không tự xóa các fallback hiện tại.** Cần tách chế độ nâng cấp trường đang chạy với chế độ cài mới để tránh làm mất khả năng đọc dữ liệu cũ.

## Kiểm tra đã thực hiện

Các phép thử dưới đây chạy trên tệp giả/thư mục tạm, không dùng dữ liệu thật:

| Kiểm tra | Kết quả |
| --- | --- |
| Cú pháp toàn bộ PHP được Git theo dõi | 567/567 tệp đạt trên PHP 8.3 |
| Hai bản cài cạnh nhau, không đặt CDS_INSTANCE_CONFIG | Tái hiện cùng đường dẫn cấu hình mặc định |
| Hai bản cài đặt CDS_INSTANCE_CONFIG riêng | Đường dẫn được cách ly |
| Hàm deploy đệ quy với `data/records.json` giả | Tái hiện tệp runtime bị sao chép |
| Loader danh mục không cấu hình | Trả 32 giáo viên, 16 lớp, 7 tổ, 28 môn, 10 kiêm nhiệm của bộ mẫu; đây không phải số liệu trường đang chạy |
| Loader với profile empty | Cả 5 nhóm đều rỗng |
| `tests/meal_primary_guard_test.php` | Đạt; lỗi database giả được chặn đúng dự kiến |
| `tests/lesson_book_primary_guard_test.php` | Đạt; dữ liệu ký giả và JSON dự phòng được giữ nguyên |

Không chạy lại toàn bộ 167 tệp kiểm thử, không benchmark tải đồng thời, không thử phục hồi database thật và không quét xâm nhập production trong lần rà soát này. Kiểm tra cú pháp không phát hiện được mọi lỗi chức năng hoặc lỗi quyền.

## Bộ khung nên được đóng gói như thế nào

Giữ một nhánh mã nguồn dùng chung và một bộ cấu hình ngoài source cho từng trường. Không sao chép thư mục đang chạy làm template.

| Thành phần | Dùng chung | Riêng từng trường |
| --- | --- | --- |
| Mã nguồn, giao diện, migration, công cụ kiểm tra | Có phiên bản phát hành và checksum | Không sửa trực tiếp để đổi tên trường |
| Nhận diện, cấp học, năm học, module, đường dẫn | Cấu trúc/schema cấu hình | Giá trị cấu hình và mã trường |
| Tài khoản, nhóm, quyền | Cơ chế và danh mục quyền | Người dùng và quyền đã gán |
| Giáo viên, học sinh, lớp, phân công, TKB, sổ đầu bài | Thuật toán/định dạng dữ liệu | Database, JSON, uploads và chữ ký |
| Drive, AI, iOffice và các tích hợp | Adapter và giao diện cài đặt | Khóa, token, OAuth redirect, thư mục, hạn mức |
| Sao lưu, nhật ký, cache, trạng thái SQL primary | Công cụ và quy tắc vận hành | Nội dung, thời điểm, nguồn dữ liệu đang bật |

## Thứ tự thực hiện và nghiệm thu

### 1. Chuẩn hóa bộ cài, giữ nguyên nghiệp vụ

- Có chế độ cài mới không nhận cấu hình/danh mục/kho dữ liệu của Xín Mần.
- Web và CLI dùng chung bộ phân giải đường dẫn; thiếu cấu hình thì dừng với hướng dẫn rõ ràng.
- Tạo quản trị riêng một lần, khóa lại bước setup sau hoàn tất.
- Đóng gói từ Git sạch; deploy có manifest, xem trước và không ghi đè runtime.
- Chặn HTTP vào kho dữ liệu; kiểm tra môi trường, quyền tệp và PHP extension.

### 2. Cài thử hai trường giả độc lập

- Trường A chỉ THCS, trường B THCS+THPT; database và cấu hình riêng; tên/năm học/danh mục không lẫn.
- Đăng nhập, vai trò và URL trực tiếp đúng quyền; cookie/phiên không lẫn.
- Tạo giáo viên/lớp/học sinh giả; nhập Excel; phân công, khóa/công bố, chuyển TKB; ký/lọc/thống kê/xuất sổ đầu bài.
- Thử báo ăn, điểm danh, văn bản, thư viện, đánh giá và tích hợp được bật; module tắt không bị nhầm là đã thu hồi quyền.
- Dữ liệu mới của A không xuất hiện trong B. Cập nhật source A không làm thay đổi dữ liệu, quyền, chữ ký hay cấu hình B.

### 3. Nâng cấp, tải lớn và phục hồi

- Nâng cấp bản sao trường hiện tại, đối chiếu số lượng và nội dung/chữ ký, kết quả phân công, TKB, báo ăn và quyền trước/sau; không chỉ so số dòng.
- Đo thời gian và bộ nhớ của ký/lưu/lọc/thống kê/xuất ở quy mô một năm và nhiều năm, có người dùng đồng thời; ghi số đo trước/sau và ngưỡng nghiệm thu theo hosting thực tế.
- Sao lưu/phục hồi vào môi trường riêng; bảo đảm job nền và tích hợp không ghi sang Drive hoặc database production.
- Chỉ phát hành `CDS Framework` khi các điểm chặn đã được đóng và hai bản cài sạch cùng bài kiểm tra nâng cấp/phục hồi đạt. Gắn tag, changelog, phiên bản schema và hướng dẫn rollback đã thử.

## Phạm vi thay đổi của lần rà soát

Chỉ bổ sung báo cáo và liên kết cảnh báo trong tài liệu cài mới. Các mục khắc phục trên là công việc còn phải triển khai/kiểm chứng, không phải các sửa lỗi đã hoàn tất. Chưa thay đổi mã chạy, tài khoản, dữ liệu đã ký, công thức thống kê hay cơ chế phân quyền hiện tại.
