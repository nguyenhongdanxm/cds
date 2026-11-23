# Ngày diễn ra và tự ẩn thông báo

Trong Chuyên môn → Thông báo, thêm/sửa thông báo có hai trường:

- Ngày diễn ra: dùng ngày này để đếm ngược. Để trống sẽ dùng hạn cụ thể, kỳ hàng tháng, hoặc ngày ban hành/sự kiện hiện có.
- Tự ẩn sau ngày diễn ra: 0–3650 ngày, mặc định 7. Giữ thông báo đến hết ngày thứ N sau sự kiện; nhập 0 thì giữ hết ngày diễn ra và ẩn từ ngày kế tiếp.

Trang chủ chỉ giữ tiêu đề “Thông báo”, bỏ dòng tiêu đề phụ. Nhãn tự tính theo ngày tại Asia/Ho_Chi_Minh: “Còn N ngày”, “Diễn ra hôm nay”, “Đã diễn ra N ngày”. Ngày sự kiện được ghi rõ thay vì hiển thị mốc 31/12/9999. Mốc tháng có ngày vượt số ngày trong tháng được giới hạn ở ngày cuối tháng.

Thông báo cũ chưa có cấu hình dùng mặc định 7 ngày. Ẩn là lọc khi đọc trang chủ, không xóa hoặc sửa bản ghi. Thông báo tắt/đã hoàn thành vẫn bị ẩn như trước. Quy trình công việc, hạn báo cáo riêng, mốc nhân sự và thông báo văn bản không bị đổi sang cơ chế sự kiện. Không cần cron hoặc SQL; chỉ cập nhật mã nguồn. Cấu hình lưu trong bản ghi thông báo chuyên môn hiện có (`event_date`, `hide_after_days`).

Kiểm tra: `php tests/notice_timing_test.php` kiểm tra ngày, biên ẩn, dữ liệu cũ và hàm tổng hợp/template thực; `python tests/notice_timing_http_test.py` chạy controller thật với dữ liệu/auth/thông báo đẩy giả, kiểm tra tạo/sửa/lưu và từ chối dữ liệu lỗi. Không gửi thông báo ra ngoài khi kiểm thử.
