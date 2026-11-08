# Số đo hiệu năng sổ đầu bài

Các phép đo chỉ đọc đồng hồ, không ghi vào sổ học hoặc sửa quy tắc ký/PPCT.

- `rows`: đọc kho dữ liệu và truy cập dữ liệu tạm trong yêu cầu.
- `range`: đọc bản ghi cho khoảng ngày.
- `slots`: dựng tiết TKB và ghép dữ liệu đã lưu.
- `statistics`: dựng dữ liệu thống kê, gồm kiểm tra phạm vi và bộ lọc.
- `write`: ghi dữ liệu và đồng bộ bản sao.
- `save`: kiểm tra và lưu nội dung tiết học.
- `sign`: toàn bộ thao tác lưu/ký qua `lb_commit_record`.

Các thời gian có lồng nhau, không cộng các mục để suy ra tổng. Số lượt gọi và thời gian được cộng dồn trong mỗi yêu cầu, không dùng chung giữa người dùng.

## Đo trên hosting

Trong DevTools → Network → yêu cầu trang/API → Response Headers, xem `Server-Timing`. Header chỉ phản ánh công việc đã hoàn thành trước lúc máy chủ gửi header; thao tác dựng HTML hoặc xuất file sau thời điểm đó có thể chưa nằm trong header.

Để thu đầy đủ số đo cuối yêu cầu vào PHP error log, tạm bật biến môi trường `CDS_LB_PERF_LOG=1`, hoặc khai báo `define('CDS_LB_PERF_LOG', true);` trong cấu hình máy chủ được nạp trước sổ đầu bài. Tắt lại sau khi đo. Không đặt file cấu hình/log vào thư mục công khai.

Dòng log `[CDS lesson book performance]` gồm thời gian toàn yêu cầu, bộ nhớ đỉnh PHP, HTTP status và các giai đoạn. Không ghi tên giáo viên, lớp, bộ lọc, nội dung sổ hay đường dẫn chữ ký. Thời gian toàn yêu cầu không bao gồm thời gian tải trên mạng và dựng giao diện trong trình duyệt. Xoay vòng log theo cấu hình hosting.

So sánh cùng tài khoản, tuần, bộ lọc và lượng dữ liệu; chạy nhiều lượt để phân biệt lần tải đầu với các lần sau. Đo riêng mở sổ ngày/tuần, lưu nháp, ký, thống kê tuần/cả năm và xuất Excel. Chưa có cam kết số giây tải trang khi chưa đo hosting.

## Phạm vi tối ưu hiện tại

Sau ghi thành công, bộ nhớ tạm của yêu cầu giữ đúng tập dữ liệu được tạo trong lần ghi nguyên tử. Bước nhật ký không cần đọc lại toàn bộ kho. Ghi lỗi không thay đổi bộ nhớ tạm; lỗi bản sao SQL vẫn giữ JSON làm nguồn hiện hành.

Thống kê có tham số lọc dùng cùng hàm lọc cũ để loại dòng trước khi tra PPCT/nhóm giáo viên; thứ tự dòng và dữ liệu đầu ra được đối chiếu với hàm cũ. Chưa đẩy điều kiện lọc xuống SQL và chưa chuyển ghi chính từ JSON sang MySQL.
