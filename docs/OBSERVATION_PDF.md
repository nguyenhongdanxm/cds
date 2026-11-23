# Phiếu dự giờ A4

Tải PDF và Lưu Drive dùng cùng một hàm dựng phiếu A4 dọc (210 × 297 mm), vùng nội dung 170 × 257 mm, lề tối thiểu 20 mm. Phiếu xuất từ bản sao ở chiều rộng cố định, không bị ảnh hưởng bởi màn hình điện thoại. Đợi font tải xong, thay ô điểm bằng chữ trước khi chụp PNG ở tỉ lệ 3 để giữ nét tiếng Việt và đường bảng. Không cắt tiêu chí hoặc chữ ký; nếu dữ liệu dài hơn khung thì thu đồng đều toàn phiếu để vẫn một trang.

Font Times New Roman được nạp rõ ba kiểu thường/đậm/nghiêng; nội dung 13 pt, tiêu chí và nhóm tiêu chí 12 pt, tiêu đề phiếu đậm 16 pt; tên Sở và trường chuyển thành chữ in hoa Unicode. Bố cục bảng có cột cố định, nội dung được xuống dòng. Chức năng In phiếu cũng áp dụng bố cục A4 và tự co vừa trang. Khi in nên tắt đầu/chân trang mặc định của trình duyệt để không thêm URL hoặc số trang.

Không sửa điểm, nội dung tiêu chí, tên giáo viên hoặc quyền truy cập. Kiểm tra PDF thực tế bằng Playwright với html2canvas/jsPDF và đọc bằng pdf-lib: một trang A4, đủ 12 tiêu chí/chữ ký, in hoa tiếng Việt, cỡ chữ, dữ liệu dài, kết quả đồng nhất trên màn hình 1440 và 390 px, dọn bản sao sau khi xuất. Không cần SQL.

## Bảo vệ font và phần ký

Bản xuất nằm trong một tài liệu iframe độc lập, chỉ nhận CSS của phiếu; không kế thừa font giao diện/Bootstrap. FontFace CSS dùng Times New Roman/TimesNewRomanPS bản cục bộ của thiết bị, đợi nạp đủ thường/đậm/nghiêng trước khi chụp. Nếu thiếu font, xuất PDF/Lưu Drive báo rõ và dừng, không âm thầm thay bằng Arial hoặc một font khác. Nút In phiếu cũng kiểm tra font trước khi in. Không phân phối lại font có bản quyền trong mã nguồn.

Phần ký dùng bảng hai cột, có khoảng riêng cho chữ ký và họ tên, thêm khoảng an toàn dưới tên và khi chụp canvas. Kiểm tra mới không chỉ đo khung DOM: đếm nét chữ thực tế trong ảnh xuất ở cả hai vùng tên, áp dụng CSS giao diện cố tình gây xung đột, kiểm tra lỗi thiếu font và dọn iframe. CI dùng Liberation Serif làm dữ liệu giả cho font cục bộ để kiểm tra pipeline, không thay đổi bộ phân giải Times New Roman ở bản triển khai.
