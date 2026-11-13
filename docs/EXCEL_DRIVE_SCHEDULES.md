# Đặt lịch Excel → Google Drive

Tính năng dùng lại các chức năng xuất hiện có; không đổi công thức tính, dữ liệu đã ký hay quyền người dùng. Các lịch và nhật ký được lưu ở MySQL riêng với dữ liệu nghiệp vụ. Sau khi cập nhật mã, tính năng **mặc định tắt**, chưa có lịch và chưa tự tải bất kỳ báo cáo nào lên Drive.

## Thiết lập một lần

1. Cập nhật mã trên hosting, gồm `excel_schedules.php`, `bin/excel-drive-cron.php`, `tools/excel_export_worker.php`, các helper `includes/excel_schedule_*` và JavaScript tương ứng. Công cụ deploy đã có các tệp này.
2. Mở **Quản trị MySQL**, chạy migration `20261109_019_excel_drive_schedules`. Migration chỉ thêm bảng lịch, trạng thái cron và lịch sử; không chuyển đổi bảng ký sổ, báo ăn hay hồ sơ hiện tại.
3. Kết nối **Kho Google Drive** hiện có bằng OAuth hoặc Service Account. Tài khoản kết nối cần quyền thêm tệp vào thư mục đích. Không cần giữ phiên đăng nhập trình duyệt để cron chạy: OAuth dùng refresh token đã cấu hình.
4. Mở **Lịch Excel → Drive** từ quản trị hoặc `/excel_schedules.php`. Trang này cung cấp đúng câu lệnh Cron Jobs theo đường dẫn bản cài. Thêm vào hosting để chạy **mỗi 5 phút**. PHP CLI 8.2/8.3 cần `pdo_mysql`, `mbstring`, `zip`, `curl`, `openssl`, `tokenizer` và quyền `proc_open`.
5. Bật **Cho phép chạy tự động**, thêm các lịch mong muốn. Kiểm tra **Cron gần nhất** và dùng **Chạy thử** với một báo cáo trước.

Ví dụ khung Cron Jobs:

```cron
*/5 * * * * /DUONG_DAN_PHP_CLI/php /DUONG_DAN_WEBSITE/bin/excel-drive-cron.php > /DUONG_DAN_RIENG/excel-cron.log 2>&1
```

Lấy đường dẫn thực tế trên trang quản lý lịch, không copy nguyên các chữ ví dụ. Nếu dùng `CDS_INSTANCE_CONFIG` qua môi trường, cron phải đọc cùng cấu hình với website; binding cục bộ của bộ cài trường mới được dùng tự động. CLI và web phải được cấp quyền đọc/ghi dữ liệu phù hợp cho cùng bản cài.

Kiểm tra cấu hình mà không xuất/tải báo cáo:

```bash
php bin/excel-drive-cron.php --check
```

Không dùng URL công khai chứa mật khẩu hoặc token để chạy cron. Lệnh cron chỉ chạy bằng PHP CLI.

## Đặt lịch thuận tiện từ các trang

Tài khoản quản trị có nút đồng hồ nhỏ bên cạnh các nút/link xuất được đăng ký. Bấm đồng hồ để mở mẫu lịch và giữ bộ lọc hiện tại. Các thao tác xuất thủ công vẫn giữ nguyên. CSDL “Xuất đã chọn” lấy đúng các mã đang tích; chưa chọn bản ghi sẽ không tự chuyển thành xuất tất cả.

Có thể tạo lịch trực tiếp tại trang quản lý, chọn báo cáo và chỉnh bộ lọc trong phần thu gọn. Danh mục gồm các nhóm xuất hiện có: CSDL (giáo viên/lớp/học sinh/thống kê), sổ đầu bài/PPCT, phân công và giờ dạy, TKB, học online, bồi dưỡng học sinh, chủ nhiệm (bữa ăn/gạo và thu chi), báo ăn/gạo, điểm danh, xin ra/vào, y tế, văn bản, đánh giá, Quiz/Olympia và các mẫu Excel hiện có. CSV và các file `.xls` tương thích Excel giữ nguyên định dạng nguồn; không tự chuyển thành Google Sheets.

Mỗi lịch chọn:

- Báo cáo và bộ lọc: lớp, giáo viên, phạm vi ngày/tuần/tháng, bản ghi/cột đã chọn… theo chức năng xuất gốc.
- Thư mục Drive: dán URL hoặc ID; có gợi ý các thư mục đã cấu hình trong Kho Drive.
- Ngày lặp: hàng ngày, hàng tuần hoặc hàng tháng. Ngày 29–31 ở tháng ngắn được lấy ngày cuối tháng.
- Khung giờ: mặc định **00:00–05:00, giờ Việt Nam**. Có thể chọn khung khác hoặc qua nửa đêm; tối thiểu 15 phút.
- Khoảng dữ liệu động: hôm qua, ngày chạy, tháng/tuần hiện tại hoặc tháng/tuần trước, nếu loại báo cáo hỗ trợ. **Giữ bộ lọc** giữ nguyên ngày/tuần/tháng đã nhập; dữ liệu trong phạm vi đó vẫn được lấy mới mỗi lần.
- Cách lưu: **Cập nhật một file của lịch** hoặc **Lưu bản riêng theo ngày/lượt**. Nên đặt tên khác nhau cho các lớp hoặc nhóm báo cáo để dễ tìm.

Ví dụ báo ăn: chọn báo cáo ngày, khoảng dữ liệu **Hôm qua**, lặp hàng ngày, 00:00–05:00. Khi chạy sáng 10/11, hệ thống xuất dữ liệu ngày 09/11. Ví dụ thống kê ký sổ: chọn phạm vi và lớp/giáo viên ở trang thống kê rồi bấm đồng hồ, chọn khoảng dữ liệu động nếu muốn ngày tự thay đổi.

Sổ đầu bài theo tuần vẫn chọn tuần học từ lịch tuần của trường; không lấy số tuần làm số tiết chương trình. Điểm danh, y tế và TKB dùng đúng kiểu kỳ/bộ lọc của từng chức năng. Thiếu tuần/phạm vi cần thiết sẽ báo lỗi, không tự lấy tuần khác để thay.

## Cách chạy và theo dõi

Cron không phụ thuộc việc có người mở website. Các báo cáo được xuất tuần tự, không chạy chồng hai tiến trình. Mỗi lượt cron xử lý tối đa 10 lịch trong ngân sách khoảng 4 phút, mỗi file tối đa 128 MB. Hệ thống phân bổ thời điểm đủ điều kiện ở đầu khung và tiếp tục xử lý trong khung; không hứa một giờ chính xác cho từng lịch.

Mỗi lịch định kỳ chỉ hoàn tất một lần cho mỗi ngày được chọn. Lỗi sẽ thử lại tối đa 3 lần, cách ít nhất 5 phút, trong khung giờ. Khi hết khung, hệ thống không chạy bù ban ngày; lượt chưa được xử lý được ghi là hết khung nếu cron còn hoạt động. Nhiều báo cáo quá nặng có thể cần khung dài hơn hoặc chia lịch. Có thể xem kết quả gần nhất ở từng lịch và lịch sử 90 ngày.

**Chạy thử** là yêu cầu thủ công: cron xử lý ở lượt kế tiếp, kể cả ngoài khung giờ. Cần bật cả đồng bộ chung và lịch đó. Thao tác trên web chỉ xếp hàng, không giữ trình duyệt chờ một file lớn. Đổi bộ lọc sau khi một kỳ đã thành công không tự xuất lại kỳ đó; dùng Chạy thử nếu cần.

Tạm dừng/bỏ lịch không xóa file trên Drive. Chủ lịch là tài khoản quản trị đã lưu lịch; hệ thống kiểm tra lại tài khoản còn hoạt động và còn quyền quản trị ở mỗi lần chạy. Quản trị khác có thể sửa/lưu để tiếp nhận lịch. Thu hồi tài khoản/quyền hoặc dừng lịch sẽ chặn việc xuất/đồng bộ tiếp theo.

## Bảo toàn dữ liệu và file Drive

- Mỗi lượt tạo file từ nguồn dữ liệu hiện hành bằng chính chức năng xuất cũ. Không tải lại một file tạm cũ đã lỗi.
- Phát hiện file rỗng, lỗi xuất, trang đăng nhập/giao diện HTML thay vì Excel; không coi chúng là file thành công.
- Chỉ cập nhật file do lịch đó quản lý, kiểm tra mã lịch, bản cài và thư mục trên Drive. Không ghi đè một file bất kỳ do người dùng nhập ID.
- Dùng mã file được cấp trước và giữ lại khi thử lại; tránh tạo nhiều bản trùng sau lỗi kết nối. Bản theo ngày không cập nhật nhầm bản của ngày khác.
- Tải theo khối 4 MiB; file tạm được giữ ngoài web root, dọn sau lượt chạy và dọn tệp mồ côi cũ sau 24 giờ. Các file dữ liệu nguồn không bị xóa bởi cơ chế dọn này.
- Nếu file bị xóa/chuyển thư mục hoặc đổi kết nối Drive, hệ thống có thể dừng với lỗi quyền/nhận diện; kiểm tra lại thư mục hoặc tạo lịch mới, không tự ghi đè file khác.

Khi sao lưu, giữ cả các bảng `cds_excel_schedules`, `cds_excel_schedule_runs`, `cds_excel_schedule_state`, cấu hình Drive và binding bản cài. Với trường khác, tạo lịch/thư mục riêng, không sao chép lịch đang trỏ vào Drive trường cũ.

Tham khảo API chính thức: [Upload file data](https://developers.google.com/workspace/drive/api/guides/manage-uploads), [Custom file properties](https://developers.google.com/workspace/drive/api/guides/properties).

## Word/PDF và hồ sơ (bổ sung)

Trang giữ URL cũ `excel_schedules.php`, tên mới **Đồng bộ báo cáo & hồ sơ → Drive**. Lịch Excel cũ và cron giữ nguyên; không cần migration mới. Các lịch mới dùng cùng cơ chế quyền quản trị, cửa sổ giờ Việt Nam, chống chạy trùng và lịch sử.

- **Phân công trực tháng**: DOCX từ chức năng xuất Word gốc; chọn tháng cố định hoặc tháng đang chạy/tháng trước.
- **Biên bản trực theo ngày**: ZIP chứa Word `.doc`, mỗi ngày một tệp, dùng chức năng xuất hiện có; lọc từ/đến ngày hoặc hôm qua/tuần/tháng. Nội dung lấy theo báo cáo gốc, không tự thêm chữ ký. Nếu điểm danh cũ chưa có bản tổng hợp, lịch báo cần đối chiếu trên trang điểm danh thay vì tự chuyển đổi dữ liệu trong nền.
- **Báo cáo định kỳ đã lưu**: DOCX; chọn đúng mã báo cáo từ nút đồng hồ cạnh Tải Word. Không tự tạo hoặc sửa báo cáo.
- **Word/PDF gốc trên hosting**: ZIP chứa tệp nguyên bản cùng `CDS-DANH-MUC.json` ghi đường dẫn, kích thước và SHA-256 để đối chiếu. Không chuyển đổi nội dung hay thêm dấu xác nhận. Giữ cấu trúc thư mục và tên lưu trên máy chủ; tên này có thể khác tên tải lên ban đầu.

Gói tệp gốc chỉ đọc `data/uploads` và `chuyenmon/data/uploads`. Nhóm `all` = cả hai; `documents` = `data/uploads/vanban`; `academic` = Chuyên môn. Định dạng `all`, `pdf`, `doc`, `docx`. Để trống nhóm/định dạng = all. Từ/đến ngày lọc theo **ngày sửa tệp trên hosting**, không phải ngày ký hay ngày ban hành. Chọn Giữ bộ lọc và để trống ngày để sao lưu toàn bộ Word/PDF thuộc nhóm mỗi lượt. Tệp gốc không bị xóa.

Giới hạn mỗi gói: 5.000 tệp, tổng dung lượng gốc 64 MB, mỗi tệp 25 MB; vượt giới hạn báo lỗi rõ ràng, không coi gói thiếu là thành công. Không theo symlink, không nhận đường dẫn thư mục tùy ý. Gói rỗng báo chưa có dữ liệu. Các tệp đã lưu thẳng trên Drive không có bản gốc tại hosting sẽ không nằm trong ZIP; chúng vốn đã ở Drive.

**Giới hạn PDF:** các màn hình chỉ có “In / Lưu PDF” vẫn cần trình duyệt tạo PDF. Chức năng này không giả dạng trang HTML thành PDF và không tự dựng PDF của các màn hình in. PDF đã có tệp gốc trên hosting được giữ nguyên trong gói sao lưu.

## Phân loại, định vị nguồn và tên tệp tự động

- Chọn **Loại tệp** để lọc nguồn: Excel, Word/gói Word, PDF gốc (trong ZIP), ZIP hỗn hợp hoặc CSV. Danh sách lịch cũng có bộ lọc loại tệp và cột định dạng đầu ra. PDF gốc và Word gốc có nguồn sao lưu riêng; định dạng tải lên Drive vẫn là ZIP chứa các tệp đó, không giả dạng PDF/DOCX.
- Mỗi nguồn hiển thị đúng đường dẫn xuất, phương thức và định dạng đầu ra. **Mở đúng tệp xuất** dùng các tham số xuất cố định cùng bộ lọc hiện tại, không mang CSRF/thông tin đăng nhập. Thiếu bộ lọc bắt buộc hoặc nguồn xuất bằng POST thì không hiện liên kết tải GET. Ngày động được xác định ở lượt cron. Muốn tải xem trước, chọn Giữ bộ lọc và điền các ngày cụ thể; liên kết xem trước ẩn khi đang dùng ngày động để tránh tải nhầm phạm vi.
- **Tự đặt tên** mặc định bật cho lịch mới. Tên gồm tiền tố nội dung, lớp/khối/GV/môn nếu nguồn có bộ lọc đó, tuần hoặc kỳ/ngày dữ liệu thực tế, rồi thời điểm đồng bộ theo giờ Việt Nam. Ví dụ: `Sổ đầu bài - Lớp 6A - Tuần 10 - Đồng bộ 2026-11-12_003005.xlsx`. Không lấy số tuần hiện tại thay cho tuần/bài đã chọn. Với nguồn dùng mã lớp/báo cáo, tên có mã tương ứng để phân biệt.
- Tên được tạo sau khi giải bộ lọc ngày động, vì vậy lịch “tuần trước” mang khoảng dữ liệu tuần trước cùng ngày giờ chạy hiện tại. Cập nhật một file vẫn giữ ID Drive, chỉ cập nhật tên/nội dung; bản riêng giữ file riêng theo kỳ/lượt.
- Lịch cũ tiếp tục dùng cách đặt tên cũ. Vào Sửa và bật **Tự đặt tên** nếu muốn áp dụng mẫu mới. Có thể tắt để dùng tên tự nhập.
- **Chu kỳ đồng bộ** và **Khoảng dữ liệu** là hai lựa chọn riêng: hàng ngày/tuần/tháng quy định lúc chạy; hôm qua/tuần trước/tháng trước hoặc giữ bộ lọc quy định dữ liệu được xuất. Chọn thứ hoặc ngày lặp và khung giờ, mặc định 00:00–05:00. Không cần migration hay thay cron.


## Tự động lưu PDF sổ đầu bài theo tuần

Mở **Sổ đầu bài → Tự động lưu sổ hằng tuần**, chọn nhiều/tất cả lớp, thư mục Drive và tùy chọn bản in. Chủ nhật 23:00–23:30, hệ thống xuất tuần hiện tại sắp kết thúc bằng **chính trang In sổ**, tạo mỗi lớp một PDF. Tệp lưu theo năm học → tuần, giữ tuần cũ và không tạo trùng khi chạy lại. Mẫu A3 ngang/hai trang A4, chữ ký và nhận xét được giữ theo bản in; không chuyển từ Excel.

Cần cài Node.js/Chromium một lần và chạy kiểm tra trước khi bật lịch. Xem **[Hướng dẫn cPanel PDF](LESSON_BOOK_PDF.md)**. Dùng lại schema và cron PHP mỗi 5 phút hiện có, không cần cron Node riêng. Chưa có bộ tạo PDF hoạt động thì lịch báo lỗi, không tải HTML hoặc PDF rỗng lên Drive.

Nút tạo nhiều lớp không nhân đôi lớp đã có lịch cùng năm học, kể cả lịch đang tắt; dùng Sửa/Bật để điều chỉnh lịch đó. Nút tạo bật đồng bộ chung nên các lịch khác đang bật cũng được phép chạy. Khi đổi năm học, tạo lịch cho danh sách lớp mới và tắt lịch năm cũ; lịch cũ bị chặn xuất sang năm mới. Dữ liệu sổ và chữ ký không bị thay đổi.
