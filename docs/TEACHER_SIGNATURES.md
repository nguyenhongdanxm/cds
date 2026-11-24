# Chữ ký giáo viên dùng chung

## Sử dụng

- Quản trị: CSDL → Giáo viên → Sửa → Ảnh chữ ký giáo viên. Có xem trước, tải ảnh và gỡ ảnh dùng cho lần ký mới.
- Giáo viên: trang tổng quan → Thông tin cá nhân → Ảnh chữ ký dùng chung. Chỉ đổi ảnh của chính hồ sơ đã liên kết.
- PNG, JPG, WebP tối đa 5 MB; ảnh được căn giữ tỷ lệ vào khung PNG 480 × 160 px. Trình duyệt và máy chủ kiểm tra ảnh; máy chủ có GD sẽ tự chuẩn hóa lại. Nếu không có GD, trình duyệt chuẩn hóa trước.
- Sổ đầu bài → Chữ ký cũng cập nhật cùng nguồn CSDL. Khi xác nhận ký tiết mới sẽ lấy phiên bản hiện tại.
- Mượn–trả thiết bị → mở sổ → Ký mượn / Ký trả từng dòng. Chỉ chính giáo viên mượn được xác nhận bằng ảnh của mình; ký trả cần đã trả thiết bị.
- Phiếu dự giờ đã hoàn thành: người dạy và người dự xác nhận phần của mình. Sửa lại điểm đánh giá sẽ xóa xác nhận cũ để ký lại.

## Chuyển chữ ký sổ đầu bài đã có

Sau khi cập nhật main, quản trị mở CSDL → Giáo viên. Hệ thống tự chuyển metadata chữ ký mới nhất từ `chuyenmon/data/lesson_book_signatures.json` về `data/teacher_signatures.json` theo mã giáo viên. Cũng đọc tương thích nguồn cũ `data/lesson_book_signatures.json` nếu có. Không cần chép lại ảnh, không sửa các tiết hay lịch sử đã ký.

Ghép theo `user.teacher_id` hợp lệ; tài khoản cũ chưa có mã thì chỉ ghép tên chuẩn hóa nếu tên duy nhất. Không đoán giáo viên khi liên kết sai hoặc trùng tên. Trường hợp không ghép được cần quản trị sửa liên kết tài khoản–giáo viên; lần mở sau sẽ thử lại. Chữ ký đã có trong nguồn CSDL, kể cả đã gỡ, không bị dữ liệu cũ ghi đè hoặc khôi phục.

## Nguồn dùng chung và tích hợp biểu mẫu

Registry luôn ở thư mục data của CDS gốc, không phụ thuộc `DATA_PATH` của Chuyên môn. Ảnh mới ở `data/teacher_signature_images`; phiên bản cũ và ảnh sổ đầu bài được giữ lại. Lưu registry theo khóa JSON nguyên tử; thao tác ký dòng thiết bị kiểm tra bản ghi chưa thay đổi trước khi ghi.

`includes/teacher_signatures.php` cung cấp `cds_ts_for_user`, `cds_ts_meta`, `cds_ts_snapshot`, `cds_ts_data_uri`. Biểu mẫu khác có thể dùng cùng API, chỉ gọi snapshot sau khi chính người ký xác nhận và kiểm tra quyền/nghiệp vụ của biểu mẫu. Các khung ký chưa có luồng xác nhận không tự chèn chữ ký người khác. Việc tải ảnh lên không tự xác nhận một tài liệu. Ảnh chữ ký không phải chứng thư ký số.

Ảnh xem trước đi qua `teacher_signature.php` yêu cầu đăng nhập và quyền xem CSDL giáo viên hoặc đúng chủ ảnh; `no-store`, `nosniff`. Bản đã ký dùng ảnh snapshot nhúng khi in/PDF, không lấy URL ảnh hiện tại. Gỡ hoặc đổi ảnh không làm đổi bản cũ. Không nhập đường dẫn hay URL ảnh do trình duyệt cung cấp; chỉ đọc ảnh thật trong các thư mục chữ ký cho phép.

Không có tệp dữ liệu vận hành trong PR. Không cần SQL. Trước khi chuyển hosting cần sao lưu cả `data` và `chuyenmon/data` như quy trình hiện có.
