# Tiến độ chương trình: khối và tải theo tuần

Trang thống kê có bộ lọc **Khối** kết hợp tuần, môn, lớp, giáo viên và trạng
thái. Lớp trong danh sách tương ứng khối đã chọn. Nếu lớp cũ thuộc khối khác,
bộ lọc lớp được xóa khi đổi khối để tránh kết quả trống gây nhầm lẫn.

Không có `week` thì chọn tuần chứa ngày hiện tại; trong khoảng nghỉ hoặc
sau năm học, chọn tuần gần nhất trước ngày hiện tại. Chọn tuần khác bằng
bộ lọc thì chỉ hiển thị tuần đó. Không tải trước tuần tương lai.

Nguyên nhân xử lý nặng trong mã cũ: tiến độ lũy kế duyệt các tuần từ đầu năm
đến tuần chọn và gọi `lb_slots(..., [])`. Dù không ghép bản ghi đã lưu, hàm
vẫn sắp xếp và suy ra PPCT/tên bài cho từng tiết, trong khi thống kê chỉ cần
đếm ngày–lớp–môn.

Bản sửa cho phép đường chỉ lấy TKB dừng trước phần suy ra PPCT từng tiết.
Đường Sổ đầu bài mặc định giữ nguyên. Tổng hợp TKB theo ngày/lớp/môn được
lưu ở thư mục tạm riêng theo bản cài đặt, tối đa khoảng 128 tệp. Cache có
checksum và khóa dựa trên nội dung TKB, ánh xạ, dạy thay/bù, phòng học,
lịch tuần và mã xử lý. Đổi nguồn thì tính lại; hỏng/không ghi được cache thì
tính trực tiếp. Cache không chứa tên giáo viên, học sinh hoặc điểm.

Lần mở đầu có thể cần tạo tổng hợp lịch sử phục vụ lũy kế. Những lần sau
dùng lại tổng hợp; chọn tuần chưa có tổng hợp chỉ dựng thêm phần còn thiếu.
Không thay cách tính tiến độ thành số tiết của riêng một tuần: vẫn giữ số
PPCT đã ký, trạng thái tiết hợp lệ, chuẩn lũy kế và giới hạn PPCT như trước.
PPCT đã ký và PPCT môn học luôn được đọc mới, không lấy từ cache TKB.

GET đọc lịch sử nhập tiến độ theo luồng thay vì giữ toàn bộ trong bộ nhớ.
POST lưu vẫn giữ đầy đủ bản ghi lịch sử như cơ chế cũ. Không migration hoặc
sửa dữ liệu vận hành; chưa đo thời gian hosting thực tế.

Kiểm thử: 40 bộ đối chiếu nguyên thuật toán lũy kế; 40 bộ đối chiếu đầu ra
ngày/lớp/môn của `lb_slots` thật (thường, thay, lấp tiết, bù); tuần hiện hành,
chọn tuần cũ, không dựng tuần tương lai, cache nóng, đổi nguồn cùng mtime và
kích thước, cache hỏng, chữ ký mới, lọc khối và phân quyền giáo viên.
