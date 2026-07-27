#!/bin/bash
# Deploy điểm danh nội trú – chạy trên host CDS
# Cách dùng: bash deploy_attendance.sh
# hoặc: curl -sL https://raw.githubusercontent.com/nguyenhongdanxm/cds/main/deploy_attendance.sh | bash

set -e
ROOT="${1:-.}"
cd "$ROOT"

BASE="https://raw.githubusercontent.com/nguyenhongdanxm/cds/main"

echo "==> [1/3] includes/noitru_att_shifts.php"
curl -fsSL "$BASE/includes/noitru_att_shifts.php" -o includes/noitru_att_shifts.php

echo "==> [2/3] noitru_attendance.php"
curl -fsSL "$BASE/noitru_attendance.php" -o noitru_attendance.php

echo "==> [3/3] Cập nhật noitru.php (redirect + link menu)"
if [ ! -f noitru.php ]; then
  echo "LỖI: không thấy noitru.php trong $(pwd)"
  exit 1
fi

# Backup
cp -a noitru.php "noitru.php.bak.$(date +%Y%m%d%H%M%S)"

# Thêm redirect attendance nếu chưa có
if ! grep -q "noitru_attendance.php" noitru.php; then
  # Chèn redirect sau block boarders
  python3 - <<'PY'
from pathlib import Path
p = Path('noitru.php')
t = p.read_text(encoding='utf-8')
old = """if ($tab === 'boarders') {
    header('Location: ' . BASE_URL . 'noitru_list.php');
    exit;
}"""
new = """if ($tab === 'boarders') {
    header('Location: ' . BASE_URL . 'noitru_list.php');
    exit;
}
if ($tab === 'attendance') {
    header('Location: ' . BASE_URL . 'noitru_attendance.php');
    exit;
}"""
if old in t:
    t = t.replace(old, new, 1)
else:
    # fallback: chèn sau $tab boarders check
    needle = "if ($tab === 'boarders')"
    if needle in t and "noitru_attendance.php" not in t:
        idx = t.find(needle)
        # tìm hết block exit;
        end = t.find("exit;", idx)
        end = t.find("\n", end) + 1
        insert = "\nif ($tab === 'attendance') {\n    header('Location: ' . BASE_URL . 'noitru_attendance.php');\n    exit;\n}\n"
        t = t[:end] + insert + t[end:]

# Đổi link menu attendance
t = t.replace(
    "'attendance' => ['Điểm danh', 'bi-clipboard-check', BASE_URL . 'noitru.php?tab=attendance']",
    "'attendance' => ['Điểm danh', 'bi-clipboard-check', BASE_URL . 'noitru_attendance.php']",
)
p.write_text(t, encoding='utf-8')
print('noitru.php updated')
PY
else
  echo "noitru.php đã có link attendance – bỏ qua patch redirect"
  # Vẫn đảm bảo URL menu đúng
  python3 - <<'PY'
from pathlib import Path
p = Path('noitru.php')
t = p.read_text(encoding='utf-8')
t2 = t.replace(
    "'attendance' => ['Điểm danh', 'bi-clipboard-check', BASE_URL . 'noitru.php?tab=attendance']",
    "'attendance' => ['Điểm danh', 'bi-clipboard-check', BASE_URL . 'noitru_attendance.php']",
)
if t2 != t:
    p.write_text(t2, encoding='utf-8')
    print('updated menu link')
else:
    print('menu link ok')
PY
fi

echo ""
echo "=== HOÀN TẤT ==="
echo "Kiểm tra:"
echo "  https://cds.noitruxinman.edu.vn/noitru_attendance.php"
echo "  Menu Nội trú → Điểm danh"
echo "  Tab Cài đặt buổi để bật/tắt buổi điểm danh"
ls -la includes/noitru_att_shifts.php noitru_attendance.php
