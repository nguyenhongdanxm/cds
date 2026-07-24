# CDS – Cổng dữ liệu số Xín Mần

Hệ sinh thái số **Trường PTDTNT THCS&THPT Xín Mần**.

- Repo cổng: https://github.com/nguyenhongdanxm/cds  
- PCCM (nhánh Chuyên môn): https://github.com/nguyenhongdanxm/pccm

## Module

| # | Tên | Trạng thái |
|---|-----|------------|
| 1 | Tin tức | Link website trường |
| 2 | Chuyên môn | PCCM `/pccm/` |
| 3 | Văn bản | Đang xây |
| 4 | Tuyên truyền | Đang xây |
| 5 | Cơ sở dữ liệu | Khung nội bộ |
| 6 | Học liệu & thi | Đang xây |
| 7 | Quản lý nội trú | Đang xây |
| 8 | Thi đua | Đang xây |

## Deploy cPanel

1. Tạo subdomain `cds.noitruxinman.edu.vn` → document root = thư mục repo này.
2. Clone / upload code.
3. `chmod -R 777 data`
4. Đăng nhập: `admin` / `Xinman@2021` — **đổi ngay**.

Nếu host trong thư mục con, sửa `BASE_URL` trong `includes/config.php`.

## Cấu trúc

```
index.php       ← Trang hệ sinh thái (vòng 8 module)
login.php       ← SSO
admin.php       ← Dashboard quản trị tập trung
csdl.php        ← Cơ sở dữ liệu dùng chung (khung)
includes/       ← config, auth, modules
data/           ← users.json (tự tạo khi chạy)
```

## Hướng phát triển

1. SSO chung domain với PCCM  
2. Dữ liệu lõi GV–lớp dùng chung  
3. Lần lượt: Văn bản → Tuyên truyền → Thi đua → Nội trú  
