# Đánh giá CBGVNV theo tháng — triển khai đầu tiên

## Nơi sử dụng

Thi đua → Đánh giá CBGVNV (`/thidua_danhgia.php`). Ba mục: Đánh giá tháng; Duyệt – Tổng hợp; Cấu hình (quản trị). Đường dẫn Chuyên môn chuyển đến cùng trang. Hồ sơ cũ vẫn ở `/danhgia.php`, không đổi công thức/dữ liệu cũ.

## Thiết lập lần đầu

1. Cập nhật mã nguồn từ main, mở Thi đua → Đánh giá CBGVNV bằng quản trị.
2. Trong Cấu hình, liên kết mỗi mã CBGVNV với một tài khoản đang hoạt động; phân công người thẩm định và người duyệt. Không ghép theo tên, không tự thẩm định/duyệt. Quản trị kỹ thuật không mặc nhiên có thẩm quyền đánh giá.
3. Chọn mẫu không quản lý/quản lý theo quyết định thực tế, ghi chức danh/kiêm nhiệm. Với hợp đồng lao động, nhập căn cứ áp dụng. Đánh dấu trình cấp ngoài trường cho hồ sơ thuộc thẩm quyền cấp trên, không duyệt nội bộ.
4. Hoàn thiện hướng dẫn chấm điểm của từng tiêu chí theo quy chế đơn vị và hướng dẫn ngành/địa phương. Các mức chia 10–15–5, 25–30–15 và mẫu quản lý là đề xuất ban đầu, không phải điểm chi tiết được Nghị định ấn định. Hệ thống yêu cầu tổng A=30, B=70 cho từng mẫu.
5. Nhập số/ngày quyết định/căn cứ thật, ngày hiệu lực; chỉ tích Kích hoạt khi quy chế đã được ban hành. Không kích hoạt thì lưu dự thảo, không gửi/duyệt chính thức.
6. Mỗi lần lưu tạo phiên bản mới. Hồ sơ đã tạo chụp lại phân công và tiêu chí. Nếu đã tạo nháp trước khi kích hoạt, quản trị mở hồ sơ nháp → Cập nhật cấu hình mới → ghi lý do. Điểm cũ được lưu trong lịch sử, bản nháp bắt đầu lại theo tiêu chí mới. Không cập nhật tiêu chí hồ sơ đã gửi/khóa.
7. Tài khoản cần được cấp quyền truy cập Thi đua theo thiết lập phân quyền hiện có để thấy menu; liên kết và phân công trong chức năng này quyết định phạm vi hồ sơ. Người chưa liên kết nhận thông báo rõ, không đoán danh tính từ họ tên.

## Quy trình thực tế

- Chọn tháng và nhân sự (cá nhân mặc định hồ sơ của mình).
- Người phụ trách giao nhiệm vụ; cá nhân cũng có thể đề xuất rồi được xác nhận. Khai sản phẩm, yêu cầu chất lượng, khối lượng và hạn/mốc trong tháng. Nhiệm vụ dài hạn khai theo mốc sản phẩm tháng, không nhân đôi toàn bộ khối lượng.
- Cá nhân ghi kết quả, ngày hoàn thành, liên kết sản phẩm. Người phụ trách xác nhận giao việc/nghiệm thu. Điều chỉnh sản phẩm/chất lượng/hạn hoặc hủy phải có lý do và lưu lịch sử. Sửa kết quả đã nghiệm thu sẽ bỏ trạng thái nghiệm thu cũ.
- Cập nhật minh chứng từ Dự giờ, Kiểm tra hồ sơ, Chấm công, Thành tích. Ưu tiên mã nhân sự; dữ liệu cũ thiếu mã chỉ ghép khi tên duy nhất trong danh mục và gắn nhãn cần đối chiếu. Có mã khác thì không ghép theo tên. Không tự quy đổi nguồn thành điểm.
- Cá nhân nhập điểm và căn cứ từng tiêu chí, ưu điểm, hạn chế/hướng khắc phục, giải trình rồi Lưu nháp/Gửi đánh giá. Để trống điểm là chưa đủ căn cứ, không phải 0. Không có nhiệm vụ phải giải trình trường hợp đặc biệt (ví dụ nghỉ theo chế độ).
- Người được phân công thẩm định → người được phân công phê duyệt và khóa. Có thể trả bổ sung với lý do. Điều chỉnh điểm cần nhận xét. Không tự phê duyệt, không cho admin dùng quyền kỹ thuật thay thẩm quyền nghiệp vụ.
- Kỳ khóa không cho sửa/đồng bộ. Người duyệt có thể mở lại với lý do, giữ bản chốt trước; gửi lại xóa các điểm thẩm định/duyệt hiện hành để xử lý lại.

## Tổng hợp năm

Chọn khoảng tháng trong năm công tác, xem tình trạng đủ/thiếu tháng; xuất CSV có cột trạng thái và phiên bản. Bản in dự thảo theo bố cục Phụ lục II có bốn phần và hai vị trí ký, sử dụng các ưu điểm/hạn chế từ tháng đã khóa. Bình quân hiển thị chỉ là tham khảo, không tự coi là điểm năm được quy chế ban hành. Phần điểm năm, tự xếp loại và kết quả của cấp có thẩm quyền để hoàn thiện; bản in ghi rõ DỰ THẢO CHƯA PHÊ DUYỆT.

Chưa thực hiện quyết định xếp loại năm, kiểm soát tỷ lệ xuất sắc, nhập quyết định cấp ngoài trường hoặc kết nối hệ thống cấp quản lý. Không tự lấy điểm tháng để cấp danh hiệu thi đua. Chưa có tải tệp trực tiếp: dùng liên kết tài liệu/sản phẩm được chia sẻ đúng quyền. Các nguồn nội trú/y tế/thư viện/thiết bị chưa tự trích xuất trách nhiệm cá nhân; khai nhiệm vụ và liên kết minh chứng trong cùng phiếu.

## Lưu trữ và vận hành

- Dữ liệu mới: `DATA_PATH/staff_evaluation/config.json` và `month-YYYY-MM.json`. Không đổi hồ sơ giáo viên cũ. Không cần sửa cấu trúc SQL.
- Ghi dưới khóa giao dịch, đọc lại trước khi cập nhật, kiểm tra revision để tránh ghi đè; ghi tệp lỗi phải giữ nguyên tệp cũ. Các tháng được đọc riêng; tổng hợp năm mới đọc các tháng trong khoảng chọn.
- Tạo `.htaccess` cấm truy cập HTTP trước khi lưu dữ liệu trong thư mục này (Apache/cPanel). Nếu dùng Nginx, phải cấu hình chặn `/data/staff_evaluation/` tương ứng đường dẫn DATA_PATH trong webroot trước khi nhập dữ liệu. Không chia sẻ thư mục dữ liệu qua web. Sao lưu cùng dữ liệu ứng dụng.
- Tài khoản trải nghiệm chỉ GET/HEAD các màn hình được phép, không cấu hình hoặc ghi. Hồ sơ xem được theo đúng quy tắc tài khoản trải nghiệm của ứng dụng.
- Chạy `php tests/staff_evaluation_test.php`, `php tests/viewer_access_test.php`; kiểm thử trình duyệt: `node tests/staff_evaluation_browser.cjs` (Playwright). Toàn bộ dùng dữ liệu giả ở thư mục tạm; không cần và không được trỏ đến dữ liệu thật.

## Căn cứ đã đối chiếu

- Nghị định 233/2026/NĐ-CP, Điều 10–15, Phụ lục I và Phụ lục II: https://datafiles.chinhphu.vn/cpp/files/vbpq/2026/6/233-ndcp.signed.pdf
- Khung kết quả thực hiện nhiệm vụ: https://xaydungchinhsach.chinhphu.vn/nguyen-tac-tieu-chi-danh-gia-vien-chuc-119260701152748166.htm
- Các mức xếp loại, điểm năm và tỷ lệ: https://xaydungchinhsach.chinhphu.vn/cac-muc-xep-loai-chat-luong-vien-chuc-119260701155446369.htm

Cần bổ sung hướng dẫn của cơ quan quản lý trường khi có; không coi cấu hình phần mềm là văn bản ban hành quy chế.
