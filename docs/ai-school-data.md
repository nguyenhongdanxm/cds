# Trợ lý dữ liệu CDS

Mở `trolyai.php?assistant=dulieu`, dùng nhà cung cấp, mô hình và API key đã lưu trong cấu hình Trợ lý AI. Không cần nhập khóa mới hay di trú dữ liệu.

- Chọn Đánh giá tổng thể hoặc Hỏi đáp số liệu; chọn nguồn, lớp và khoảng ngày (tối đa 93 ngày, không có ngày tương lai).
- Quản trị có quyền mặc định. Để cấp cho tài khoản khác, cấp quyền `ai.dulieu` trong nhóm Trợ lý AI cùng quyền nguồn tương ứng và lớp được phép xem. BGH không khai báo hạn chế lớp được xem toàn trường; BGH có danh sách lớp chỉ xem các lớp đó. Tài khoản khác không có lớp được cấp sẽ không lấy số liệu.
- Nguồn: học sinh, cán bộ/giáo viên, nội trú, phiếu báo ăn, gạo đã chốt, lượt y tế, bản ghi sổ đầu bài. Nguồn thiếu quyền không được đọc hoặc gửi cho AI.
- Kết quả kèm thời điểm truy xuất, khoảng ngày và liên kết nguồn. API ghi nhật ký thông tin bộ lọc và lượng sử dụng, không ghi khóa API hoặc nội dung hồ sơ.

## Giới hạn cần hiểu đúng

Hồ sơ học sinh/giáo viên là dữ liệu hiện có. Ngày nhập/rời trường hỗ trợ tính học sinh theo quy tắc nội trú (tính cả ngày rời trường); đây không phải bản chốt lịch sử lớp, nội trú hay nhân sự. Báo cáo không khôi phục được thông tin đã sửa/xóa trước đó.

Suất ăn dùng hàm đối chiếu phiếu với hồ sơ và báo ăn đang được CDS sử dụng; bữa nghỉ không tính. Gạo chỉ tính bữa khóa/chốt với định mức hiện có; không phải tồn kho. Tổng suất ăn không phải số học sinh duy nhất. Thống kê y tế chỉ gửi tổng lượt, số học sinh duy nhất, ngày và lớp; không gửi tên bệnh, bệnh án, tên hoặc mã học sinh.

Sổ đầu bài dùng nguồn SQL khi chế độ đọc an toàn có hiệu lực, nếu không dùng JSON. Chỉ tính bản ghi đã lưu trong khoảng ngày và lớp; không gồm tiết TKB chưa ghi. Chưa ký không đồng nghĩa chưa dạy. Chưa tính tiến độ PPCT, bồi dưỡng, thu chi chủ nhiệm và các phân hệ khác. Trợ lý phải nêu thiếu dữ liệu khi câu hỏi vượt nguồn.

Dữ liệu tổng hợp được gửi tới nhà cung cấp đã cấu hình khi người dùng bấm xử lý. Không có SQL do AI sinh ra, không có hành động sửa dữ liệu. Bộ đọc lõi không tự tạo danh sách mẫu hoặc phục hồi tệp khi AI truy vấn. Nếu gói số liệu vượt giới hạn cấu hình, người dùng cần thu hẹp nguồn, lớp hoặc thời gian.

## Kiểm tra

```sh
php -l includes/ai_school_data.php
php -l ai_api.php
php -l trolyai.php
php tests/ai-school-data.test.php
node --check assets/ai-assistant.js
```

Kiểm thử bao gồm quyền nguồn/lớp, tài khoản chưa có lớp, hạn chế BGH, ngày nhập/rời trường, loại CCCD khỏi gói tổng hợp và chữ ký/snapshot sổ đầu bài. Cần kiểm tra trên hosting với dữ liệu thật và API hiện có sau khi mã được cập nhật.
