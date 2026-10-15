# Tạo câu hỏi Olympia bằng AI — quản trị

Vào **Olympia → Quản trị → chọn tuần → AI tạo câu hỏi Olympia**.
Tạo/lưu tuần trước nếu đang ở màn hình tạo tuần mới.

1. Bật và cấu hình nhà cung cấp/API trong **Trợ lý AI** nếu chưa có.
2. Nhập chủ đề; có thể dán tối đa 4.000 ký tự tài liệu tham khảo.
3. Chọn khối 6–12 hoặc tất cả khối, vòng thi, độ khó, số câu 1–25 và điểm/câu.
4. Bấm **Tạo bản nháp**. Hệ thống tạo từng lượt tối đa hai câu để giảm nguy cơ
   hết thời gian; có thể dừng và giữ lại các câu đã tạo.
5. Sửa câu hỏi/đáp án, bỏ chọn câu không dùng. Đọc giải thích AI và kiểm chứng
   đáp án; kiểm tra câu khác cách diễn đạt nhưng trùng kiến thức với kho cũ.
6. Xác nhận đã kiểm tra rồi bấm **Thêm các câu đã chọn vào tuần này**.

Chức năng chỉ dành cho tài khoản có vai trò `admin`, gồm cả kiểm tra phía
máy chủ. API key không được gửi về trình duyệt. Chủ đề, tài liệu và một phần
câu hỏi chính thức cũ được gửi tới nhà cung cấp AI đã cấu hình; không gửi
danh sách học sinh, điểm thi hoặc câu hỏi trong bộ riêng của giáo viên.

Tạo bản nháp không ghi ngân hàng. Khi lưu, máy chủ kiểm tra lại nội dung,
đáp án, khối/vòng/điểm và trùng nội dung trong toàn kho chính thức. Kiểm tra
trùng chuẩn hóa chữ hoa/thường, khoảng trắng và dấu câu; không phải kiểm
chứng tự động hoàn toàn về trùng kiến thức hoặc tính đúng của đáp án.

Lưu bổ sung trong transaction, không sửa/xóa câu cũ, bộ cá nhân hoặc điểm
thi. Phiên duyệt gắn với tuần và chỉ dùng một lần để ngăn gửi lặp. Nếu lưu
lỗi, giữ bản nháp trên trang để sửa/thử lại; không tải lại khi chưa lưu.
Không cần migration hoặc thay đổi cấu hình AI đang hoạt động.

Kiểm thử dùng phản hồi AI giả lập, không gọi API tính phí. Workflow
`Olympia AI safety` chạy PHP 8.2/8.3 và MySQL/MariaDB dùng một lần,
kiểm tra quyền, CSRF, dữ liệu sai cấu trúc, trùng câu, duyệt trước lưu,
chống gửi lặp, lưu đúng kho/tuần và rollback cả lô khi lỗi SQL.
