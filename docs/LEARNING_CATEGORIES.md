# Chuyên mục học liệu, kiểm tra và tiện ích

Trong từng tab **Học liệu**, **Kiểm tra và thi**, **Tiện ích**, mở **Quản lý chuyên mục** để tạo, đổi tên, mô tả, sắp thứ tự hoặc xóa chuyên mục. Chuyên mục của ba phần độc lập. Quản trị và người có quyền duyệt có thể quản lý chuyên mục học liệu/kiểm tra trong phần được phép xem; chuyên mục tiện ích do quản trị quản lý.

Khi nhập hoặc sửa học liệu/đề kiểm tra, chọn **Chuyên mục**. Khi đang xem một chuyên mục, nội dung mới mặc định thuộc chuyên mục đó. Trong Tiện ích, quản trị dùng **Xếp tiện ích vào chuyên mục** rồi lưu từng công cụ.

Mỗi chuyên mục hiện thành một ngăn: tối đa 6 học liệu/đề hoặc 8 tiện ích cùng nút **Xem tất cả**. Trang chuyên mục hiện đầy đủ nội dung, tìm kiếm và lọc khối trong phạm vi đã chọn. Tổng quan có liên kết đến các chuyên mục. Nội dung cũ hoặc nội dung thuộc chuyên mục đã xóa được giữ ở **Chưa phân chuyên mục**. Trước khi tạo chuyên mục, danh sách cũ vẫn hiện đầy đủ. Tiện ích bị ẩn và nội dung không có quyền xem tiếp tục bị ẩn.

Dữ liệu chuyên mục lưu trong `data/learning_categories.json`; nội dung lưu mã chuyên mục trong `learning_hub.json`. Không cần SQL. Ghi chuyên mục có khóa và kiểm tra phiên bản để tránh ghi đè khi nhiều người cùng sửa. Dữ liệu lỗi không bị tự ghi đè.

Kiểm tra: `php tests/learning_categories_test.php` và `node tests/learning_categories_browser.cjs` (Playwright, Bootstrap và Bootstrap Icons; PHP có mbstring/dom).
