# Nguồn ghi chính MySQL báo ăn

Mặc định tắt. Không tự đổi nguồn khi mở trang. Chỉ quản trị hệ thống dùng biểu mẫu có CSRF trong Quản trị CSDL mới được bật hoặc quay về JSON.

## Trước khi bật

Triển khai đủ mã nguồn, sao lưu CSDL và thư mục dữ liệu. Cập nhật bản sao báo ăn, đối chiếu khớp, bật ghi bản sao và đọc MySQL. Bấm **Báo ăn – nguồn ghi chính → Bật nguồn ghi chính MySQL**. Hệ thống giữ cùng khóa với người đang lưu, từ chối nếu còn lần đồng bộ/ghi chưa hoàn tất, đối chiếu nội dung và thứ tự trước khi công bố. Nguồn có khóa trùng phải xử lý rõ ràng trước; không tự gộp/xóa dòng để chuyển nguồn.

## Phạm vi

Bảng `cds_meal_primary_rows` lưu từng dòng cá nhân và phiếu, có chỉ mục theo loại/ngày, khóa định danh và vị trí nguồn. Bảng `cds_meal_primary_meta` lưu trạng thái bữa, cài đặt, thuộc tính bổ sung, nhật ký và hai danh sách đánh dấu xóa. Giữ nguyên toàn bộ payload và thứ tự phiếu, không dựng lại ảnh chụp học sinh theo CSDL hiện tại. Các bảng bản sao cũ được giữ lại và không còn là nguồn đọc trong chế độ này.

- Lưu thường ngày chỉ tra các khóa học sinh/ngày hoặc phiếu liên quan, dùng chính quy tắc PHP về khóa, giá trị mặc định, hợp nhất nội dung và dấu xóa.
- Sinh/khóa một ngày chỉ đọc các dòng của ngày đó.
- Bổ sung nhật ký cũ chỉ đọc phần tóm tắt phiếu, không tải ảnh chụp học sinh. Giữ nguyên quy tắc bổ sung và giới hạn 3.000 lượt của chức năng hiện có.
- Tổng hợp khoảng ngày lọc dữ liệu ngay tại nguồn, không đổi cách tính suất ăn, học sinh hợp lệ, bữa nghỉ hoặc làm tròn gạo.
- Các biến đổi toàn danh sách ít dùng (ví dụ xóa lịch sử) vẫn duyệt nguồn đầy đủ, nhưng phân trang giới hạn bộ nhớ và công bố trong cùng giao dịch. Bảng tạm SQL chỉ cập nhật payload thay đổi.
- Mọi ghi liên quan trong callback báo ăn dùng một giao dịch; lỗi lồng nhau hủy cả thao tác. Không quay sang JSON cũ khi SQL lỗi.
- JSON trước chuyển không bị cập nhật sau mỗi lần lưu. Bản sao MySQL, dấu hiệu chế độ và toàn bộ dữ liệu phải được sao lưu theo chính sách hosting.

## Quay về

Dùng **Xuất mới nhất và quay về JSON**. Chuẩn bị đủ năm tệp từ SQL hiện hành, đối chiếu thứ tự/nội dung, công bố dưới khóa rồi mới bỏ dấu hiệu chế độ. Nếu xuất hoặc đối chiếu lỗi, MySQL tiếp tục là nguồn chính. Sau khi quay về, bản sao cũ bị đánh dấu chờ đồng bộ để không đọc nhầm dữ liệu; cập nhật/đối chiếu bản sao trước khi bật đọc MySQL lại.

Không xóa thủ công `meal_sql_primary.php`, không nhập JSON cũ đè nguồn chính, không để trình triển khai xóa thư mục dữ liệu. Chỉ hỗ trợ một hosting hoặc các tiến trình dùng chung thư mục/khóa; chưa hỗ trợ nhiều máy chủ có ổ dữ liệu độc lập.

## Giới hạn và đo thực tế

MySQL không bảo đảm hết mọi độ trễ. Lập phiếu vẫn phải dựng ảnh chụp học sinh và kiểm tra quyền; xuất Excel vẫn có chi phí tính toán/mã hóa. Các file cấu hình nhỏ và những phân hệ khác không bị chuyển trong thay đổi này. Chưa có số đo tốc độ trên hosting thực tế; kiểm thử tải dùng dữ liệu giả lập, cần so sánh cùng tài khoản/ngày/khoảng lọc trước và sau kích hoạt.
