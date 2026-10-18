# Bấm chuông

Trong **Học liệu → Trò chơi → Bấm chuông**, giáo viên tạo phòng, nhận mã 8
chữ số và mở **Mở trình chiếu** trên máy có loa. Trên trang trình chiếu,
chọn **Bật âm thanh** một lần (quy định phát âm thanh của trình duyệt) và
có thể chọn Toàn màn hình.

Thí sinh mở `hoclieu_game_buzzer.php?view=play` trên điện thoại/máy tính,
nhập mã và tên/tên đội. Không cần tài khoản CDS. Tên trong phòng không
trùng nhau; thêm lớp/số thứ tự nếu cần. Mỗi thiết bị lưu token riêng để
vào lại đúng thí sinh khi tải lại trang. Đổi trình duyệt/xóa bộ nhớ thì cần
vào lại; giáo viên có thể bỏ tên cũ lúc câu chưa bắt đầu/đã kết thúc.

1. Chọn thời gian 5–300 giây rồi **Chuẩn bị câu mới**.
2. Đọc câu hỏi, chọn **Bắt đầu** để mở chuông và chạy đồng hồ.
3. Người bấm đầu được quyền trả lời: đồng hồ dừng, trình chiếu phát một
   tiếng chuông và làm nổi/nhấp nháy tên. Người bấm tiếp hiện #2, #3… nhưng
   không phát tiếng chuông. Thí sinh đã bấm không được bấm lặp trong lượt.
4. Chọn **Sai**: khóa người vừa trả lời đến hết câu, xóa hàng đợi của lượt
   cũ và mở lượt bấm mới cho những người còn lại. Đồng hồ chạy tiếp thời
   gian còn lại. Người từng ở #2/#3 phải bấm lại; không tự chuyển quyền.
5. Chọn **Đúng** để kết thúc câu; hoặc **Dừng câu** khi cần.
6. Chuẩn bị câu mới để xóa thứ tự và mở lại quyền cho tất cả. **Đóng phòng**
   dừng toàn bộ phòng. Đây là tiện ích giành quyền trả lời, chưa cộng điểm
   hay lưu ngân hàng câu hỏi.

## Dữ liệu và thứ tự

Tính năng chỉ tạo/ghi bảng riêng `cds_buzzer_rooms` (InnoDB) khi giáo viên
mở danh sách/tạo phòng, không migration hay sửa bảng/dữ liệu trò chơi cũ.
MySQL/MariaDB phải hoạt động; không dự phòng bằng thứ tự tự bầu trên trình
duyệt. Mỗi phòng tối đa 100 tên; tối đa 10 phòng/tài khoản trong 24 giờ.
Phòng hết hạn sau 24 giờ; lúc tạo phòng mới chỉ dọn tối đa 100 phòng hết
hạn trong bảng này. Không lưu học sinh/điểm/PCCM hoặc khóa API AI.

Mỗi lần bấm dùng transaction + khóa dòng phòng `SELECT ... FOR UPDATE`;
kiểm tra lại token, câu, lượt, quyền bấm và hạn giờ dưới khóa. Vì vậy chỉ
một người đứng đầu, không trùng số thứ tự, yêu cầu mạng cũ không lọt sang
lượt/câu mới. Thứ tự tính theo máy chủ xử lý yêu cầu, chịu ảnh hưởng đường
truyền; không khẳng định phân biệt được chính xác thời điểm bấm vật lý
trên các thiết bị. Nên dùng cùng mạng Wi-Fi ổn định.

Đọc trạng thái không cập nhật dữ liệu hay chạy DDL. Trình chiếu/giáo viên
cập nhật khoảng 300 ms khi đang chơi; thí sinh khoảng 1 giây, gửi chuông
ngay khi bấm (không chờ lượt poll). Trang nền/lỗi mạng giảm tần suất. Đồng
hồ vẽ ở thiết bị từ thời gian máy chủ, nhưng hạn nhận chuông do máy chủ
quyết định. Chưa đo tải/độ trễ trên hosting thực tế.

Giáo viên chỉ điều khiển phòng của mình, cần quyền học liệu; ẩn trò chơi
ngăn giáo viên mở/tạo mới, còn quyền điều khiển phòng đang chạy vẫn được
kiểm tra. Thí sinh dùng token riêng (hash lưu trong state), trình chiếu
chỉ có token đọc (trong fragment liên kết, không gửi vào URL HTTP/referrer).
Các snapshot không lộ token, hash hoặc tên các bạn khác cho thí sinh.
POST kiểm tra CSRF/JSON; giao diện dùng textContent cho tên. Không giữ khóa
PHP session khi chờ DB. Khi mất kết nối nút chuông bị vô hiệu, không giả
lập báo thắng. Chỉ trình chiếu phát âm thanh; không phát lại chuông cũ khi
mở/tải lại trình chiếu.

## Kiểm thử

- State machine với 100 tên giả: thứ tự, bấm lặp, chuông duy nhất, khóa sai,
  mở lại, hết giờ, câu mới, tất cả sai, quyền host và dữ liệu xuất ra.
- CI MySQL 8/MariaDB 10.11 dùng CSDL dùng một lần: 32 người đồng thời, 31
  người bấm lại, 12 yêu cầu trùng, quyền truy cập và bảng dữ liệu giả khác
  giữ nguyên. Test từ chối chạy ngoài dịch vụ disposable được đánh dấu.
- Chromium với chính template/CSS/JS và state machine PHP trong fixture
  không đọc cấu hình/DB trường: ba màn hình, âm thanh opt-in/một lần,
  thứ tự #2/#3 im lặng, sai/đúng/câu mới, nối lại, offline và bố cục mobile.

Deploy toàn bộ main qua cPanel như hiện tại. Manifest đã bổ sung hai file
root, assets/includes được sao chép theo cơ chế có sẵn. Không nạp lại CSDL
đang chạy hoặc thực hiện lệnh xóa dữ liệu.
