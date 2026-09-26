# Chương trình Olympia (bản sân khấu)

Module sân khấu độc lập với Olympia tuần/lớp. Truy cập `olympia_stage.php?view=host` bằng tài khoản quản trị, hoặc từ mục **Olympia sân khấu** trên trang quản trị Olympia.

1. Nhập tên bốn thí sinh, chọn **Tạo phiên và mã ghế**. Ghi lại mã phiên và bốn mã ghế; mã ghế chỉ được hiển thị lúc tạo.
2. Mở **Màn trình chiếu** trên máy chiếu. Nhấn **Bật âm thanh trình chiếu** một lần để trình duyệt cho phép phát nhạc. Mỗi thí sinh mở **Đường dẫn máy thí sinh**, chọn ghế và nhập mã ghế riêng.
3. Máy điều khiển mở vòng, chọn cảnh, nạp câu hỏi và đáp án chuẩn, bấm **Tính thời gian** sau khi MC đọc câu hỏi. Sau câu hỏi, bấm **Kết thúc câu hỏi → Công bố đáp án → Đúng/Sai**. Các thiết bị tự lấy trạng thái chung mỗi 500 ms.
4. Tăng tốc có bốn câu 20, 20, 30, 30 giây. Máy chủ lưu thời điểm nộp; thí sinh được giám khảo xác nhận đúng nhận 40, 30, 20, 10 điểm theo thứ tự thời gian; bằng thời điểm nhận được cùng mức điểm. Xác nhận theo thứ tự bất kỳ vẫn tính lại thứ hạng.
5. Về đích: chọn ba câu 20/30, chọn câu đang thi, đặt Ngôi sao khi đang ở cảnh gói **trước khi hiện câu hỏi**. Câu thực hành có nút riêng để chuyển từ suy nghĩ sang 30/60 giây thực hiện. Sau khi chấm người đang thi sai, cửa sổ giành quyền 5 giây mở. Nút **Kết thúc lượt · thí sinh tiếp** chọn người tiếp theo theo điểm hiện tại, hòa thì ưu tiên vị trí ghế thấp hơn. Màn máy chiếu thể hiện gói, ngôi sao, chuông, đáp án và điểm.
6. Chướng ngại vật: nhập bốn hàng ngang, tải ảnh, điều khiển năm mảnh ghép; thí sinh có thể bấm chuông khi bảng ghép đang hiện. Giám khảo xác nhận đáp án; máy chủ không tự so khớp chính tả.

Ảnh đại diện, logo, ảnh câu hỏi và nhạc hiệu được tải lên riêng cho phiên sân khấu. Tệp được kiểm tra MIME và kích thước; ảnh tối đa 8 MB, âm thanh tối đa 25 MB. Trạng thái, điểm và lịch sử ngắn lưu trong bảng `cds_olympia_stage_rooms`; các bảng Olympia tuần không bị chỉnh sửa.

**Mạng LAN:** Mỗi thiết bị phải truy cập được cùng một máy chủ CDS. Nếu CDS chạy ở tên miền công khai, các thiết bị vẫn đi qua Internet; dùng LAN thực sự cần đặt một bản CDS và cơ sở dữ liệu trên máy chủ nội bộ, rồi mở địa chỉ IP/tên nội bộ của máy chủ đó. Chuông và thời điểm nộp được xử lý theo thời gian máy chủ trong giao dịch cơ sở dữ liệu, không dựa vào đồng hồ thiết bị.

**Giới hạn phiên này:** Điều khiển câu hỏi và xác nhận đáp án do người điều khiển nhập trực tiếp; chưa có ngân hàng đề nhập hàng loạt, bộ câu hỏi mẫu hoàn chỉnh, camera trực tiếp, phát video, hoặc trình thiết kế hoạt cảnh theo ảnh VTV. Câu thực hành cần giám khảo xác nhận kết quả; luật phân định hòa sau ba câu phụ cần tổ chức bốc thăm ngoài hệ thống. Trước khi dùng trong cuộc thi thực, kiểm thử trên môi trường CDS có PHP/MySQL và bốn thiết bị LAN.
