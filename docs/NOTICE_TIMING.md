# Ba mốc ngày của thông báo

Form Thêm/Sửa thông báo trong Chuyên môn có ba trường độc lập:

- **Ngày ban hành**: ngày bắt đầu hiện thông báo trên trang chủ.
- **Ngày diễn ra / chính thức áp dụng**: bắt buộc; là mốc duy nhất để tính đếm ngược/đã diễn ra. Không lấy ngày ban hành hoặc hạn báo cáo để thay thế ở bản ghi mới.
- **Ngày ẩn**: tùy chọn; ẩn từ 00:00 ngày đã chọn theo Asia/Ho_Chi_Minh. Để trống là không tự ẩn. Ngày ẩn không được trước ngày ban hành.

Ví dụ ban hành 20/11/2026, áp dụng 23/11/2026, ẩn 30/11/2026:

| Ngày xem | Hiển thị |
|---|---|
| 19/11 | Chưa hiển thị |
| 20/11 | Còn 3 ngày |
| 23/11 | Diễn ra hôm nay |
| 24/11 | Đã diễn ra 1 ngày |
| 29/11 | Đã diễn ra 6 ngày |
| Từ 30/11 | Ẩn khỏi trang chủ |

Danh sách quản lý và khung xem hiển thị đủ ba ngày. Trang chủ phân biệt ngày ban hành và ngày diễn ra/áp dụng. Thông báo ẩn vẫn được giữ trong kho để xem/sửa; không xóa dữ liệu. Các mốc đều tính theo ngày lịch Việt Nam.

Bản ghi mới lưu `date`, `event_date`, `hide_date` và `notice_timing_version=2`. Các trường hạn báo cáo/lặp tháng cũ được ẩn trong form thông báo để tránh nhầm với ba ngày. Bản ghi cũ vẫn được đọc theo cách cũ đến khi sửa; khi mở sửa, hệ thống điền ngày áp dụng/ngày ẩn tương ứng cấu hình cũ để người dùng kiểm tra và lưu lại. Biểu mẫu cũ không gửi các trường mới vẫn giữ cấu hình đã lưu.

Không cần cron hoặc SQL. Kiểm tra bằng `php tests/notice_timing_test.php` và `python tests/notice_timing_http_test.py`: ví dụ cụ thể của người dùng, ngày ẩn/không ẩn, dữ liệu cũ, tổng hợp dashboard/template thật, tạo/sửa/lưu ba ngày và từ chối dữ liệu lỗi. Kiểm thử dùng nguồn dữ liệu/auth/thông báo đẩy giả, không gửi thông báo ra ngoài.

## Màn hình Thông báo chung

Cùng ba mốc ngày cũng có trực tiếp tại `/notices.php` (form Thêm / cập nhật thông báo), không chỉ ở Chuyên môn. Nhấn nút bút chì để sửa thông báo đang có. Dòng cũ chưa có ngày áp dụng/ngày ẩn sẽ hiện “Chưa cài đặt” để người quản lý chủ động nhập đúng ngày; không đoán ngày chính thức từ nội dung hoặc hạn cũ. Form bắt buộc ngày diễn ra; để trống ngày ẩn thì không tự ẩn.

Trang xem công khai hiện các ngày đã cài. Luồng đồng bộ từ `cm_docs.json` sang `dashboard_notices.json` giữ nguyên ba trường ngày và trạng thái, và bộ tổng hợp trang chủ áp dụng cùng quy tắc cho `central_notice`. Kiểm tra HTTP mới chạy chính `notices.php`, qua lưu AJAX, đọc lại danh sách, trang công khai và bản sao đồng bộ; không dùng Google Drive thật hoặc gửi thông báo ra ngoài.
