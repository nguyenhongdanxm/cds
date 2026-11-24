# Sổ sử dụng thiết bị theo giáo viên và môn

Trang `thuvien.php?tab=equipment_loans` hiển thị một sổ cho mỗi bộ giáo viên + môn học + năm học. Mở sổ để xem các dòng mượn–trả theo thứ tự ngày mượn; nút In / Xem sổ in cả lịch sử của nhóm. Lượt mới cùng giáo viên/môn/năm được thêm vào sổ đang có, không tạo thêm một biểu độc lập. Môn dạy là bắt buộc khi thêm lượt mới.

Dữ liệu từng thiết bị mượn vẫn là dòng riêng có ID riêng để giữ số lượng, lịch sử, tồn kho và quyền trả/xóa theo từng dòng. Trả dòng nào chỉ cập nhật ngày/tình trạng của dòng đó; xóa dòng không xóa cả sổ. Sổ dùng mã ổn định `eqbook_…`; trường `batch_id`/`ledger_id` của lượt mới cùng nhóm dùng chung mã này.

Các lượt cũ có batch khác nhau tự được gom khi xem/in, không ghi đè hoặc xóa lịch sử. Link in cũ `thietbi_phieu.php?batch=…` tìm nhóm của batch rồi mở toàn bộ sổ tương ứng. Link mới dùng `?ledger=…`. Nếu batch cũ chứa nhiều nhóm khác nhau, không tự gộp nhầm. Mỗi trang in chứa 16 dòng và đánh số liên tục; nhiều trang vẫn thuộc cùng một sổ. Ngày mượn/trả, lớp, tiết PPCT, số lượng và ghi chú của từng dòng được giữ.

Tên giáo viên/môn được so sánh không phân biệt chữ hoa/thường và khoảng trắng thừa. Năm học có các kiểu dấu gạch được chuẩn hóa; dòng cũ không lưu năm dùng ngày mượn để suy ra năm học tháng 8–tháng 7. Dòng thiếu môn/giáo viên/năm không được đoán sang nhóm khác. Không cần SQL hoặc thao tác gộp dữ liệu thủ công.

Kiểm tra bằng `php tests/equipment_ledgers_test.php`, `python tests/equipment_ledgers_http_test.py`, `php tests/equipment_print_test.php` và Chromium `tests/equipment_print_browser.cjs`. HTTP test chạy controller thật với dữ liệu/auth giả, xác nhận thêm hai lần chung sổ, trả/xóa từng dòng, tồn kho, CSRF/quyền xem, nhóm tách môn/năm và trang in. Không dùng dữ liệu sản xuất.
