# Trợ lý AI trong Xem & chỉnh TKB

Mở `tkb_editor.php`, chọn **Trợ lý AI** ở cột bên phải. Người đã có quyền
quản trị/chỉnh TKB nhập một yêu cầu (tối đa 500 ký tự) rồi chọn **Phân tích
và xem trước**. Ví dụ:

- Chuyển tiết 2 Toán lớp 10A sáng thứ 3 sang tiết 4 chiều thứ 5.
- Đổi hai tiết với lớp, môn, thứ/buổi/số tiết nguồn được ghi rõ.
- Chuyển toàn bộ buổi chiều thứ 4 sang chiều thứ 5 của toàn trường.
- Giảm tiết trống của cô [họ tên đầy đủ].
- Xếp các tiết còn thiếu, giữ nguyên tiết đã khóa.
- Kiểm tra lỗi TKB.

Bộ hiểu lệnh và bộ kiểm tra hiện hữu được dùng lại, không thay thuật toán.
Nếu tên/tiết không xác định duy nhất hoặc yêu cầu vượt schema hỗ trợ, hệ
thống yêu cầu bổ sung; không tự chọn đối tượng hay bỏ bớt điều kiện.
Muốn thêm ngày/buổi nghỉ, khai báo trong Ràng buộc trước.

Phân tích chỉ tạo đề xuất trong phiên đăng nhập. Các tiết thay đổi được
liệt kê đầy đủ trước/sau. Chỉ nút **Áp dụng ... thay đổi** mới lưu vào bản
nháp đang mở, kèm một mốc Hoàn tác. Không gọi chức năng công bố.

Đề xuất gắn với workspace, phương án và fingerprint của lịch, ràng buộc,
PCCM. Khi áp dụng: kiểm tra CSRF, token, hạn 15 phút, đọc lại dữ liệu,
kiểm tra lại ràng buộc và giữ số lượng tiết/tiết khóa. Lịch thay đổi hoặc
chuyển tuần/phương án thì phân tích lại. Dùng khóa tối ưu hiện hữu khi lưu;
các thao tác chỉnh tay khác vẫn theo cơ chế lưu hiện có của ứng dụng.

Dùng cấu hình dịch vụ AI TKB hiện tại; API key ở máy chủ. Lệnh chuyển buổi
đúng mẫu vẫn được parser cục bộ xử lý khi dịch vụ AI tạm ngừng. Không gửi
điểm/học sinh cho bộ hiểu lệnh; dữ liệu gửi giống mục AI hiện hữu: yêu cầu
và danh mục lớp, giáo viên, môn. Những lệnh khác cần AI được bật/cấu hình.

Kiểm thử dùng dữ liệu giả và bộ xếp/validator thật: xem trước không ghi,
chuyển lịch, đổi tiết, khóa/cố định/ô bận/phòng/buổi đóng/quy tắc môn,
CSRF, token hết hạn, lệch workspace/phương án/fingerprint/PCCM, lỗi lưu,
hoàn tác, HTML escaping và hành vi nút UI. Không gọi API AI trả phí hoặc
truy cập CSDL vận hành. Chưa đo phản hồi nhà cung cấp trên hosting thực tế.
