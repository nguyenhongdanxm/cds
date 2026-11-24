# Tốc độ Hồ sơ chuyên môn

## Thay đổi

- API đọc thư mục nhả khóa phiên PHP trước khi gọi nguồn lưu trữ. Những yêu cầu cùng tài khoản, kể cả điều hướng nhanh hoặc đóng trình xem, không còn phải xếp hàng sau một lượt đọc thư mục chậm. Khóa chỉ được mở lại ngắn để gộp resource key; không gửi lại cookie phiên, không phục hồi trạng thái của phiên đã đăng xuất, không ghi đè cấu hình đã thay đổi.
- Các lần đọc metadata trong cùng yêu cầu dùng lại kết nối cURL để giảm tạo kết nối mới khi lần theo nhiều thư mục cha.
- Danh sách con được nhớ 20 giây theo thư mục, bộ lọc, trang, phiên bản cấu hình và quyền kết nối. Nút Làm mới gửi `Cache-Control: no-cache` để lấy danh sách mới ngay.
- Nội dung PDF/ảnh được giữ tạm tối đa 5 phút, chỉ khi nguồn cung cấp version, modifiedTime hoặc checksum. Mỗi lần mở vẫn kiểm tra file, quyền tải và toàn bộ chuỗi thư mục cha bằng dữ liệu nguồn mới. Version/credential/root/config khác sẽ không dùng chung byte cache. Không dùng cache để bỏ qua kiểm tra vị trí hay quyền.
- Trình duyệt giữ tối đa 3 tài liệu và 16 MB trong bộ nhớ của trang. Mở lại gửi ETag; chỉ dùng blob cũ sau khi máy chủ đã kiểm tra lại và trả 304. Lỗi truy cập sẽ bỏ blob cũ; rời trang sẽ thu hồi URL. Không lưu tài liệu vào localStorage/IndexedDB.
- Cache máy chủ ở `chuyenmon/data/academic_drive_cache`, tạo quyền riêng tư và chặn truy cập web. Giới hạn 64 MB/160 tệp, dọn tệp cũ khi ghi; lỗi ghi cache sẽ đọc trực tiếp như trước. Không ghi access token dạng rõ vào cache.

## Sử dụng

Cập nhật main rồi tải lại Hồ sơ chuyên môn để nhận JS mới. Không cần SQL hoặc thiết lập thêm. Dùng nút Làm mới nếu vừa thêm/đổi tài liệu và muốn thấy thay đổi ngay; danh sách có thể cũ tối đa 20 giây. Bản nguồn đổi version sẽ được tải mới ở lần mở tiếp theo.

Lần mở đầu tiên và tài liệu lớn vẫn phụ thuộc vào tốc độ kết nối nguồn và thời gian chuyển đổi PDF. Thay đổi tập trung giảm việc tải lại và tránh các yêu cầu chặn nhau; không chuyển sang iframe bên ngoài hoặc bỏ kiểm tra quyền.

## Kiểm tra

Fixture riêng, không gọi kho vận hành: đếm request để chứng minh thư mục ấm giảm một lượt lấy danh sách, tài liệu ấm không tải lại byte; kiểm tra refresh, version/config/credential, di chuyển file/thư mục, quyền tải, hết hạn và ngân sách đĩa. Kiểm tra HTTP đa tiến trình cùng session mô phỏng một API nguồn chậm 2 giây; request thứ hai phải hoàn tất trước 1,3 giây. Chromium kiểm tra mở lại qua 304 dùng cùng blob, dialog/abort, tải xuống, phân trang, phân quyền và bố cục hiện có.
