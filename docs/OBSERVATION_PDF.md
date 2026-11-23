# Phiếu dự giờ A4

Tải PDF và Lưu Drive dùng cùng một hàm dựng phiếu A4 dọc (210 × 297 mm), vùng nội dung 170 × 257 mm, lề tối thiểu 20 mm. Phiếu xuất từ bản sao ở chiều rộng cố định, không bị ảnh hưởng bởi màn hình điện thoại. Đợi font tải xong, thay ô điểm bằng chữ trước khi chụp PNG ở tỉ lệ 3 để giữ nét tiếng Việt và đường bảng. Không cắt tiêu chí hoặc chữ ký; nếu dữ liệu dài hơn khung thì thu đồng đều toàn phiếu để vẫn một trang.

Font Times New Roman (dự phòng Liberation Serif/serif), nội dung 13 pt, tiêu đề phiếu đậm 16 pt; tên Sở và trường chuyển thành chữ in hoa Unicode. Bố cục bảng có cột cố định, nội dung được xuống dòng. Chức năng In phiếu cũng áp dụng bố cục A4 và tự co vừa trang. Khi in nên tắt đầu/chân trang mặc định của trình duyệt để không thêm URL hoặc số trang.

Không sửa điểm, nội dung tiêu chí, tên giáo viên hoặc quyền truy cập. Kiểm tra PDF thực tế bằng Playwright với html2canvas/jsPDF và đọc bằng pdf-lib: một trang A4, đủ 12 tiêu chí/chữ ký, in hoa tiếng Việt, cỡ chữ, dữ liệu dài, kết quả đồng nhất trên màn hình 1440 và 390 px, dọn bản sao sau khi xuất. Không cần SQL.
