# Đợt 1: bảo vệ thao tác lưu đang vận hành

Bản sửa này giữ JSON là nguồn ghi của báo ăn và giữ cấu trúc bản ghi hiện có.
Không chạy migration, không sửa dữ liệu có sẵn, không đổi công thức suất ăn,
định mức gạo, giờ khóa, phân quyền hay quy tắc PPCT.

## Thay đổi

- Các đường ghi báo ăn, sinh ngày, khóa/mở ngày, báo cáo, trạng thái bữa và
  xóa nguồn lịch sử dùng chung một khóa. Khóa được giữ đến hết bước cập nhật
  bản sao MySQL. Thao tác lồng nhau không lấy khóa lần thứ hai.
- Dưới khóa, dữ liệu được lấy mới từ JSON thay vì bộ nhớ đệm hoặc bản SQL cũ.
- Tệp JSON đang có nhưng hỏng/không đọc được khiến thao tác cập nhật trả về
  thất bại; không biến tệp đó thành danh sách rỗng rồi ghi đè.
- Trang báo ăn dừng trước thông báo thành công/báo cáo dẫn xuất khi thao tác
  lưu trả về thất bại. Với nhập nhiều ngày, phần đã lưu trước lỗi được giữ lại.
- Khóa nhận xét tuần mới chứa năm học. Nhận xét có khóa cũ nhưng đã có
  `school_year_key` vẫn sửa được trong đúng năm, giữ ID và thông tin tạo.
  Đường đọc JSON được lọc theo năm học giống đường đọc SQL hiện có.

## Kiểm thử

Chạy trên PHP 8.3 CLI và dữ liệu tạm, không nạp cấu hình hoặc CSDL vận hành:

```sh
php tests/operational_writes_test.php
php tests/weekly_review_year_test.php
php tests/lesson_book_manual_test.php
php tests/dashboard_attendance_public_test.php
php tests/substitution_permissions_test.php
```

Kiểm thử mới bao gồm 6 tiến trình cùng ghi 48 học sinh và 48 báo cáo lớp;
giữ bản ghi lịch sử và các trường không sửa; quy tắc locked/force, phiếu nghỉ
phép, sinh ngày, mở khóa, xóa một bữa; nguồn hỏng; bộ nhớ đệm cũ; nhả khóa
khi thất bại; bản SQL lỗi; và nhận xét cùng tuần/lớp ở hai năm học.

Workflow PR chạy cú pháp và các hồi quy chính trên PHP 8.2 và 8.3.

## Kiểm thử bổ sung ngày 14/10/2026

Toàn bộ thao tác dùng tệp giả lập trong thư mục tạm. Không đọc hoặc kết nối
CSDL vận hành, Drive hay hosting của trường.

| Kiểm tra | Kết quả |
| --- | --- |
| 209 thao tác đối chiếu mã trước sửa và mã PR trên 1.000 bản ghi | Giá trị trả về và bản ghi giống nhau; chuẩn hóa riêng ID ngẫu nhiên của bản ghi tạo mới |
| 400 học sinh × 334 ngày, 01/08/2026–30/06/2027 | 133.600 bản ghi; sau 6 cập nhật, bản ghi và kết quả giống mã cũ |
| Cùng fixture, một thao tác ghi, PHP 128 MB | Cả mã cũ và mã PR đều hết bộ nhớ |
| Kiểm tra SHA-256 trước/sau lỗi hết bộ nhớ của PR | Nội dung tệp nguồn không thay đổi |
| Cùng fixture, một thao tác ghi, PHP 256 MB | Cả hai đạt; đỉnh bộ nhớ quan sát khoảng 148–152 MiB |
| Giả lập ghi tệp nguồn thất bại | Trả về thất bại, tệp cũ giữ nguyên và không ghi bản SQL |
| Giả lập ghi báo cáo thất bại | Lỗi truyền lên hàm gọi; tệp báo cáo cũ giữ nguyên |
| Kiểm thử cạnh tranh 6 tiến trình, lịch sử năm học và 3 hồi quy hiện có | Đạt |

Các phép đối chiếu dùng bản gốc `67f488361201f018e2ad544a2f2e4e454fb92830`
và mã PR `d9d99983d9468e4d43ead23633daedc77b7cf913`. Thời gian trên máy
kiểm thử không đại diện hosting; bộ nhớ trên fixture ít trường không phải
giới hạn trên cho dữ liệu thật. Không suy ra rằng chỉ tăng lên 256 MB là đủ.

**Chưa xác nhận đủ điều kiện triển khai:** đọc/ghi cả lịch sử JSON có giới hạn
bộ nhớ đã tồn tại từ mã cũ. Đợt tiếp theo cần giảm lượng dữ liệu đọc/ghi,
kiểm thử SQL thật trên CSDL thử riêng và xác nhận cấu hình hosting trước
khi kết luận vận hành an toàn đến hết năm học.

## Phạm vi chưa hoàn thành

- Chưa chuyển báo ăn sang ghi SQL chính hoặc lưu cả lớp trong một lần.
- Khóa này không biến việc ghi nhiều tệp thành một transaction có rollback.
- Hai người sửa cùng một ô vẫn theo quy tắc lần lưu sau cùng; chưa có kiểm
  tra phiên bản biểu mẫu. Payload bữa ăn từ biểu mẫu cũ vẫn cần cải thiện
  tiếp để tránh gửi lại các bữa không được chủ động sửa.
- Không tự suy đoán hoặc chuyển đổi các nhận xét rất cũ thiếu năm học;
  chúng giữ cơ chế tương thích xác định năm hiện hành của mã cũ.
- Kiểm thử bản sao MySQL dùng mô phỏng; chưa kiểm thử MySQL/hosting thật.
- Các phân hệ khác vẫn cần rà soát đường ghi và kiểm thử chịu tải riêng.

## Trước khi áp dụng lên hosting

1. Giữ pull request ở dạng nháp cho đến khi các kiểm thử đạt.
2. Sao lưu MySQL, `data/`, `chuyenmon/data/`, cấu hình riêng và tệp đính kèm.
3. Kiểm tra trên bản thử tách biệt có dữ liệu sao chép, bao gồm SQL bật/tắt
   và lỗi đồng bộ; không dùng cấu hình Drive thật để thử tải tệp.
4. Xác nhận tính toàn vẹn và các thao tác báo ăn thường dùng trước khi merge.
5. Đợt tiếp theo mới tối ưu lưu theo lô và truy vấn phạm vi, bằng kiểm thử
   đối chiếu đầu ra với thuật toán hiện tại.
