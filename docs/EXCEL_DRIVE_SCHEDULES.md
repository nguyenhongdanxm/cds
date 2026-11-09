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

Có thể tạo lịch trực tiếp tại trang quản lý, chọn báo cáo và chỉnh bộ lọc trong phần thu gọn. Danh mục gồm các nhóm xuất hiện có: CSDL (giáo viên/lớp/học sinh/thống kê), sổ đầu bài/PPCT, phân công và giờ dạy, TKB, học online, báo ăn/gạo, điểm danh, xin ra/vào, y tế, văn bản, đánh giá, Quiz/Olympia và các mẫu Excel hiện có. CSV và các file `.xls` tương thích Excel giữ nguyên định dạng nguồn; không tự chuyển thành Google Sheets.

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
