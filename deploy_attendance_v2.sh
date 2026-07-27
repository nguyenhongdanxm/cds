#!/bin/bash
# Deploy điểm danh v2 (popup P/KP, xác nhận, xuất ảnh)
set -e
cd "${1:-.}"
BASE="https://raw.githubusercontent.com/nguyenhongdanxm/cds/main"

echo "==> Cấu hình buổi"
curl -fsSL "$BASE/includes/noitru_att_shifts.php" -o includes/noitru_att_shifts.php

echo "==> noitru_attendance.php (logic)"
curl -fsSL "$BASE/noitru_attendance.php" -o noitru_attendance.php

# Nếu file đang require view nhưng view chưa có → gộp tạm
if grep -q "noitru_att_view.php" noitru_attendance.php 2>/dev/null; then
  if [ ! -f includes/noitru_att_view.php ] || [ "$(wc -c < includes/noitru_att_view.php)" -lt 1000 ]; then
    echo "==> Đang chờ file UI đầy đủ trên GitHub..."
    echo "Chạy lại script sau khi admin đẩy includes/noitru_att_view.php"
  fi
fi

echo "==> Sửa menu noitru.php"
if [ -f noitru.php ]; then
  cp -a noitru.php "noitru.php.bak.$(date +%Y%m%d%H%M%S)"
  sed -i "s|BASE_URL . 'noitru.php?tab=attendance'|BASE_URL . 'noitru_attendance.php'|g" noitru.php
  if ! grep -q "tab === 'attendance'" noitru.php; then
    php -r '
    $f="noitru.php"; $t=file_get_contents($f);
    $n="if (\$tab === '\''boarders'"'"') {";
    $p=strpos($t,$n); if($p===false) exit;
    $e=strpos($t,"exit;",$p); $e=strpos($t,"\n",$e)+1;
    $ins="\nif (\$tab === '\''attendance'\'') {\n    header('\''Location: '\'' . BASE_URL . '\''noitru_attendance.php'\'');\n    exit;\n}\n";
    file_put_contents($f, substr($t,0,$e).$ins.substr($t,$e));
    echo "redirect OK\n";
    '
  fi
fi

echo "=== Xong logic. Cần thêm UI: includes/noitru_att_view.php ==="
ls -la noitru_attendance.php includes/noitru_att_shifts.php 2>/dev/null
