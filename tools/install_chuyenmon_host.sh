#!/bin/bash
# Cài module Chuyên môn (PCCM) vào CDS theo hướng A
# Chạy: bash tools/install_chuyenmon_host.sh
set -e
CDS_ROOT="${1:-/home/capnachi/cds.noitruxinman.edu.vn}"
cd "$CDS_ROOT"

echo "==> Thư mục CDS: $CDS_ROOT"

# 0. Cập nhật file CDS (menu, config)
curl -sL "https://raw.githubusercontent.com/nguyenhongdanxm/cds/main/includes/config.php" -o includes/config.php
curl -sL "https://raw.githubusercontent.com/nguyenhongdanxm/cds/main/includes/modules.php" -o includes/modules.php
curl -sL "https://raw.githubusercontent.com/nguyenhongdanxm/cds/main/includes/auth.php" -o includes/auth.php
curl -sL "https://raw.githubusercontent.com/nguyenhongdanxm/cds/main/chuyenmon.php" -o chuyenmon.php
mkdir -p tools
curl -sL "https://raw.githubusercontent.com/nguyenhongdanxm/cds/main/tools/install_chuyenmon_host.sh" -o tools/install_chuyenmon_host.sh

# 1. Code PCCM → chuyenmon/
if [ -d chuyenmon/.git ]; then
  echo "==> git pull chuyenmon..."
  git -C chuyenmon pull --ff-only || true
elif [ -f chuyenmon/index.php ]; then
  echo "==> Đã có chuyenmon/ — giữ code, chỉ vá cấu hình"
else
  echo "==> Clone PCCM → chuyenmon/"
  rm -rf chuyenmon.tmp
  git clone --depth 1 https://github.com/nguyenhongdanxm/pccm.git chuyenmon.tmp
  mkdir -p chuyenmon
  rsync -a --exclude '.git' chuyenmon.tmp/ chuyenmon/
  rm -rf chuyenmon.tmp
fi

# 2. BASE_URL
if [ -f chuyenmon/includes/config.php ]; then
  curl -sL "https://raw.githubusercontent.com/nguyenhongdanxm/pccm/main/includes/config.php" -o chuyenmon/includes/config.php
  echo "==> config.php PCCM (BASE_URL tự nhận /chuyenmon/)"
fi

# 3. Session cookie path=/ + chấp nhận session CDS
FN=chuyenmon/includes/functions.php
if [ -f "$FN" ]; then
  # Cookie path trước session_start
  if ! grep -q "session_set_cookie_params" "$FN"; then
    sed -i "s|if (session_status() === PHP_SESSION_NONE) session_start();|if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Lax']); session_start(); }|g" "$FN"
  fi
  # is_logged_in nhận cả cds_user
  sed -i "s|function is_logged_in() { return !empty(\$_SESSION\['pccm_admin'\]); }|function is_logged_in() { return !empty(\$_SESSION['pccm_admin']) || !empty(\$_SESSION['cds_user']); }|g" "$FN"
  # Đồng bộ cờ khi đã có cds_user
  if ! grep -q "cds_user.*pccm_admin" "$FN"; then
    sed -i "/init_data();/a\\nif (!empty(\$_SESSION['cds_user']) && empty(\$_SESSION['pccm_admin'])) { \$_SESSION['pccm_admin'] = true; }" "$FN" || true
  fi
  echo "==> Vá session / đăng nhập CDS"
fi

# 4. Data từ bản cũ
OLD_DATA=""
for p in \
  /home/capnachi/public_html/pccm/data \
  /home/capnachi/noitruxinman.edu.vn/pccm/data \
  "$CDS_ROOT/../public_html/pccm/data"
do
  if [ -d "$p" ] && [ -f "$p/teachers.json" ]; then OLD_DATA="$p"; break; fi
done
mkdir -p chuyenmon/data
if [ -n "$OLD_DATA" ]; then
  if [ ! -f chuyenmon/data/teachers.json ]; then
    echo "==> Copy data từ $OLD_DATA"
    cp -a "$OLD_DATA"/. chuyenmon/data/
  else
    echo "==> chuyenmon/data đã có dữ liệu — không ghi đè"
  fi
else
  echo "==> Không thấy data PCCM cũ — dùng mặc định khi mở app"
fi
chmod -R u+rwX chuyenmon/data 2>/dev/null || true

echo ""
echo "Xong."
echo "  • https://cds.noitruxinman.edu.vn/chuyenmon/"
echo "  • Đăng nhập CDS → Chuyên môn (cùng session)"
echo "  • Bản /pccm/ cũ có thể để redirect sau khi kiểm tra ổn"
