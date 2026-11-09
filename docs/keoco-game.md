# Kéo co kiến thức

## Giáo viên

Mở Học liệu → Trò chơi → Kéo co. Đặt tên hai đội, tổng thời gian trận, số nấc cách biệt để thắng, thời gian mỗi câu (0 để tắt) và thời gian nghỉ sau khi sai. Câu chung: lượt trả lời đầu tiên chốt câu và chuyển câu cho cả hai đội. Câu riêng: hai đội tiến độc lập trên cùng một nguồn câu hỏi, bắt đầu khác câu. Đúng được một điểm và kéo một nấc; sai không được điểm. Hết trận hoặc hết câu: đội nhiều điểm thắng; bằng điểm hòa.

- **Chơi trên màn hình này:** chọn đáp án trực tiếp trên máy giáo viên/máy chiếu.
- **Tạo phòng · mở màn hình đội:** gửi đúng liên kết xanh/đỏ, mở trên điện thoại/máy tính khác rồi bấm **Bắt đầu trận trong phòng**. Hai đội không cần tài khoản; liên kết là quyền trả lời của đội đó. Đội có thể bấm A–D hoặc chạm đáp án. Giữ liên kết cho đúng đội. Máy giáo viên hiển thị sân kéo co và điểm đồng bộ.
- **Kết thúc trận:** dừng trận trên máy chủ và thông báo kết quả tới hai đội.
- Phòng tồn tại tối đa 2 giờ. Khi tải lại cùng tab, máy giáo viên thử nối lại phòng bằng khóa lưu trong sessionStorage. Phòng là dữ liệu tạm, có thể hết hiệu lực khi hosting dọn thư mục tạm; tạo phòng mới trong trường hợp này.

## Câu hỏi

Ngân hàng MySQL hiện có được giữ nguyên, gồm bộ riêng/chia sẻ và quyền sửa theo chủ sở hữu/quản trị. Bộ soạn có ô nội dung nhiều dòng, nhân bản, tìm kiếm, đếm câu và tô riêng lựa chọn đáp án đúng. Nút Lưu ngân hàng mới ghi thay đổi.

**Excel:** tải `assets/mau-cau-hoi-keoco.xlsx`, giữ dòng đầu 6 cột **Câu hỏi, A, B, C, D, Đáp án**; nhập đáp án A–D hoặc 1–4. Đọc trang tính đầu tiên; tối đa 5 MB và 500 câu. Chỉ nhập giá trị, không công thức. Đọc file → xem trước → chọn câu → thêm vào bộ → lưu. File sai không được nhập một phần; câu có nội dung trùng được bỏ qua khi thêm. Cần PHP ZipArchive và DOM trên hosting.

**AI:** dùng quyền `ai.dayhoc` (hoặc admin), cấu hình nhà cung cấp/API trong Trợ lý AI có sẵn. Chọn chủ đề, khối, độ khó, 1–20 câu và nội dung tham khảo nếu có. Xử lý theo đợt nhỏ tối đa 2 câu, có nút dừng. Các câu đã tạo vẫn được xem trước nếu lượt sau lỗi. AI không tự ghi vào ngân hàng; giáo viên kiểm tra, chọn câu rồi lưu. Khóa API không chuyển xuống trình duyệt. Chưa gọi nhà cung cấp thật trong kiểm thử tự động; cần cấu hình API hoạt động để sử dụng.

## Đồ họa và đồng bộ

Sân trường dùng tài nguyên có sẵn, sáu nhân vật SVG với cử động đầu/tay, nhún khi kéo, nhảy khi thắng và hụt sức khi thua. Dây/nơ dịch theo tỷ số; hiệu ứng giấy màu khi chiến thắng. Bố cục sân rộng phía trên, hai bảng câu hỏi phía dưới; tự xếp dọc trên điện thoại. Font Nunito nội bộ; reduced motion tắt chuyển động. Công thức dùng KaTeX giống trang cũ (CDN).

Phòng được chấm trên máy chủ, khóa file khi cập nhật; mỗi câu có serial chống gửi trùng/cũ. Token đội chỉ đọc câu của mình, không nhận đáp án đúng trước khi trả lời, không được bắt đầu/dừng hoặc trả lời thay đội khác. Trạng thái và đáp án gốc lưu ngoài web root trong thư mục tạm quyền 0700, file 0600. Token đi qua header; liên kết đội chứa token trong fragment. Poll 600–700 ms và lùi nhịp khi mất kết nối; không giữ khóa PHP session. Phù hợp hosting một máy chủ; nếu chạy nhiều máy chủ cần chuyển kho phòng sang MySQL/Redis chung.

## Kiểm tra và triển khai

- `php tests/keoco_match_test.php`: điểm, quyền token, shared/split, freeze, hạn giờ, hết câu, gửi đồng thời.
- `php tests/keoco_http_test.php`: các endpoint thật trong môi trường tạm, login/CSRF/quyền, POST mutation, chống replay, phạm vi đội.
- `php tests/keoco_excel_test.php`: đọc file mẫu, chặn công thức, header/đáp án sai, XML entity.
- `php tests/quiz_ai_test.php`: validator/dịch vụ AI dùng chung với phản hồi giả lập.
- `node tests/keoco_client_test.cjs` với jsdom: trang PHP thật và JS editor/chơi tại máy/link đội/trả lời/đóng băng. Đây là kiểm tra DOM, không phải xác nhận bố cục trực quan trong trình duyệt.
- `php tests/staff_evaluation_deploy_test.php`: triển khai vào thư mục tạm, so sánh byte các endpoint/CSS/JS/XLSX và bảo toàn dữ liệu hosting.

Chưa kiểm tra trực quan trực tiếp trong trình duyệt vì phiên trình duyệt đã hết lượt sử dụng. Chạy cPanel **Update from Remote → Deploy HEAD Commit**, sau đó Ctrl+F5. Danh sách triển khai đã bổ sung API ngân hàng, API phòng và trang đội. Dùng migration MySQL ngân hàng `20260907_012_game_question_bank` đã có nếu hosting chưa áp dụng; không có migration thay đổi dữ liệu mới trong nâng cấp này.
