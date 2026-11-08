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

## Chế độ ghi chính MySQL (mặc định tắt)

Triển khai đủ mã nguồn trước khi đổi nguồn. Trong **Quản trị CSDL → Sổ đầu bài**, cập nhật bản sao, đối chiếu JSON/MySQL, bật ghi bản sao và đọc MySQL. Sau đó mới có thể bấm **Bật nguồn ghi chính MySQL**. Chỉ quản trị hệ thống được đổi nguồn qua biểu mẫu có CSRF. Bật thất bại nếu còn đồng bộ chờ hoặc dữ liệu chưa khớp.

Khi bật, giữ file JSON trước chuyển nguyên trạng. Chữ ký và payload của từng tiết được giữ đúng nội dung gốc. Một dấu hiệu chế độ dạng PHP không công khai được tạo cùng thư mục dữ liệu. Các đường đọc/lưu JSON dùng chung của đúng kho sổ đầu bài chuyển sang MySQL; dữ liệu JSON khác không đổi. Ghi chỉ cập nhật/xóa các bản ghi thay đổi trong giao dịch, dùng cùng khóa kho dữ liệu để giữ nguyên callback kiểm tra hiện có. Các lỗi SQL và PPCT hủy toàn bộ giao dịch. Không tự đọc bản JSON cũ khi SQL lỗi.

Chế độ này loại bỏ việc viết lại toàn bộ JSON mỗi lần ký/lưu. Nó **vẫn đọc các bản ghi trong giao dịch để chạy đúng các kiểm tra PHP hiện có**, nên chưa phải thao tác có chi phí cố định. Không tự đổi thuật toán PPCT để giảm số dòng đọc. Phạm vi vận hành hiện tại là cùng một hosting/thư mục dữ liệu chia sẻ; không triển khai các máy chủ có ổ dữ liệu độc lập cùng ghi kho này.

Để quay lại, dùng **Xuất mới nhất và quay về JSON**. Hệ thống giữ khóa, đọc dữ liệu SQL mới nhất, xuất JSON nguyên tử, đối chiếu chính xác rồi mới tắt định tuyến SQL. Khi DB chưa sẵn sàng, không thể quay lại bằng bản cũ. Không tự xóa file dấu hiệu chế độ hoặc nhập JSON trước chuyển đè lên SQL đang dùng.

Vẫn cần sao lưu MySQL theo chính sách hosting. JSON trước chuyển chỉ là bản lưu tại thời điểm đổi nguồn, không phải bản sao cập nhật liên tục.

Bảng kê giờ dạy có thêm truy vấn SQL lọc ngày, trạng thái thực dạy và dấu hiệu đã ký trước khi giải mã dữ liệu; các quy tắc PHP vẫn kiểm tra lại. Các giá trị chữ ký kiểu cũ được giữ trong tập ứng viên. Không áp dụng lọc chỉ tiết đã ký cho thống kê tổng số tiết TKB, vì mẫu số phải bao gồm cả tiết chưa ký. Khi bật nguồn chính, hệ thống cũng bổ sung chỉ mục ngày cho truy vấn khoảng thời gian.
