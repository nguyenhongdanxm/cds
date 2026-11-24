# Tải giảng theo ngày

Trang `chuyenmon/tkb_taigiang.php` giữ quyền xem tải giảng hiện có, chỉ đọc TKB và dạy thay đã duyệt.

Bảng mỗi giáo viên một hàng; cột nhóm Sáng/Chiều và Tiết 1–4 trở lên theo TKB thực tế của tuần (tối đa 12 tiết/buổi). Mỗi ô có lớp/môn hoặc Trống; nhiều lịch cùng ô được giữ đủ và đánh dấu trùng. Cột tên giữ bên trái, phần tiết cuộn ngang trong khung trên điện thoại. Ô dạy thay ghi giáo viên được thay, tổng tiết tính cho giáo viên thực dạy.

Lọc kết hợp ngày, buổi, môn, lớp, giáo viên và số tiết. Khi chọn môn/lớp, danh sách gồm giáo viên có phân công tương ứng trong tuần, kể cả người không có tiết hôm đó. Các tiết ngoài bộ lọc vẫn hiển thị “Lịch khác” và tính là bận; không biến tiết dạy lớp khác thành trống. Tổng tiết là lịch thực tế, số tiết phù hợp được thống kê riêng. Các số tổng hợp khớp bộ lọc đang dùng.

Các liên kết Sáng/Chiều/Cả ngày giữ môn/lớp/GV đang chọn; Bỏ lọc giữ ngày/buổi. Không lấy TKB đang hoạt động của tuần khác để kết luận ngày chưa có TKB. Nếu không tìm được tuần hoặc dữ liệu TKB, trang báo chưa thể kết luận có/trống tiết.

Không sửa lịch, phân công, dạy thay hoặc dữ liệu vận hành. Không cần SQL.
