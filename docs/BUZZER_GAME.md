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

## Nhạc nền và chuông riêng

Trong bảng điều khiển, mở **Cài nhạc nền & tiếng chuông**. Chọn riêng file
nhạc nền và file chuông (MP3/WAV/OGG/M4A, tối đa 10 MB/file, còn phụ thuộc
PHP upload_max_filesize/post_max_size của hosting). Chỉ cài khi câu chưa
bắt đầu hoặc đã kết thúc. Âm thanh lưu theo phòng, không chỉ trên thiết bị
chọn file. Trình chiếu trên máy khác có cùng liên kết vẫn nhận cấu hình.

Trên trình chiếu chọn **Bật âm thanh**, điều chỉnh âm lượng Nhạc nền và
Chuông riêng. Nhạc nền lặp khi đồng hồ chạy, dừng khi có người giành quyền
trả lời/hết giờ/dừng câu/mất kết nối. Chọn Sai thì nhạc chạy tiếp khi mở
lại chuông; câu mới phát lại từ đầu. Chỉ người đầu tiên của mỗi lượt phát
chuông riêng, người thứ 2/3 vẫn không phát thêm âm thanh. Khi chưa chọn
chuông hoặc không giải mã được file, dùng chuông mặc định. Có nút bỏ nhạc
nền và trở về chuông mặc định.

File được kiểm tra nội dung bằng fileinfo, lưu tên ngẫu nhiên đuôi .bin
trong data/buzzer_audio được bảo vệ; API đọc kiểm tra quyền host hoặc token
trình chiếu và phòng còn hạn. Thí sinh không tải được âm thanh. Thay file
thì dọn file cũ; upload mới dọn tối đa 100 file riêng quá 48 giờ. Không
nhận URL ngoài, không thực thi file và không thay state xếp thứ tự chuông.

Khi có đội giành chuông, tên/ô đội nhấp nháy đổi màu liên tục và hiện biểu
tượng chuông rung. Hiệu ứng dừng khi giáo viên quyết định Đúng/Sai/Dừng
câu; người đứng sau không kích hoạt thêm chuông. Tôn trọng cài đặt giảm
chuyển động của thiết bị trình chiếu.

## Cập nhật: âm thanh dùng chung và upload từng phần

Mục **Âm thanh dùng chung cho tất cả các phòng** luôn có trên trang điều
khiển, kể cả trước khi tạo phòng hoặc khi câu đang chạy. Quản trị viên
chọn file, bấm **Tải lên & áp dụng mọi phòng**, chờ tiến trình báo **Đã
lưu và áp dụng cho tất cả các phòng**. Giáo viên khác dùng cấu hình chung
và không tự thay âm thanh toàn trường. Sau deploy tải lại trang để nhận
script mới.

Upload nay dùng JSON từng phần 128KB, có retry idempotent và kiểm tra đủ
file/MIME trước khi áp dụng. Không phụ thuộc upload_max_filesize của PHP
cho multipart. Mỗi request vẫn cần được hosting chấp nhận khoảng 180KB;
file tối đa 10MB. Thử nghiệm HTTP thực tế với upload_max_filesize=1KB và
post_max_size=256KB chứng minh file WAV 300KB vẫn tải/đọc lại đúng byte.

Cấu hình ở bảng riêng cds_buzzer_audio_settings, được áp dụng khi đọc
trạng thái/âm thanh cho mọi phòng cũ và mới, kể cả phòng của giáo viên
khác. Không ghi lại JSON phòng, không đổi thứ tự/người thắng/thời gian.
Các khóa cài chung (kể cả null khi bỏ nhạc/trả chuông mặc định) ưu tiên hơn
cấu hình riêng cũ; khi chưa có khóa chung vẫn đọc cấu hình phòng cũ.
Cài chung có hiệu lực khi trình chiếu nhận cập nhật, kể cả câu đang chạy.

File đang được cài chung được giữ lâu dài, không bị xóa theo tuổi 48 giờ
của phòng. File đã thay và upload dở được dọn có giới hạn; file chung
hiện hành luôn được loại khỏi danh sách dọn. Phần mô tả cài riêng theo
phòng/multipart ở trên chỉ áp dụng phiên bản cũ.
