#!/bin/bash
# Deploy điểm danh nội trú – không cần python3
# curl -sL https://raw.githubusercontent.com/nguyenhongdanxm/cds/main/deploy_attendance.sh | bash

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

cp -a noitru.php "noitru.php.bak.$(date +%Y%m%d%H%M%S)"

# Đổi link menu attendance (sed)
sed -i "s|BASE_URL . 'noitru.php?tab=attendance'|BASE_URL . 'noitru_attendance.php'|g" noitru.php

# Thêm redirect attendance nếu chưa có
if ! grep -q "noitru_attendance.php');" noitru.php 2>/dev/null; then
  # Chèn sau block boarders exit bằng PHP (có sẵn trên host)
  php -r '
  $f = "noitru.php";
  $t = file_get_contents($f);
  if (strpos($t, "noitru_attendance.php") !== false && strpos($t, "tab === '\''attendance'\''") !== false) {
    echo "redirect already present\n";
    exit(0);
  }
  $needle = "if (\$tab === '\''boarders'\'') {";
  $pos = strpos($t, $needle);
  if ($pos === false) { echo "WARN: boarders block not found\n"; exit(0); }
  $end = strpos($t, "exit;", $pos);
  $end = strpos($t, "\n", $end) + 1;
  $insert = "\nif (\$tab === '\''attendance'\'') {\n    header('\''Location: '\'' . BASE_URL . '\''noitru_attendance.php'\'');\n    exit;\n}\n";
  $t = substr($t, 0, $end) . $insert . substr($t, $end);
  file_put_contents($f, $t);
  echo "redirect inserted\n";
  '
else
  echo "redirect / menu đã có noitru_attendance.php"
fi

echo ""
echo "=== HOÀN TẤT ==="
ls -la includes/noitru_att_shifts.php noitru_attendance.php
echo "Mở: https://cds.noitruxinman.edu.vn/noitru_attendance.php"
