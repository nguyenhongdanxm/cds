#!/bin/bash
# Cài module Chuyên môn (PCCM) vào CDS theo hướng A
# Chạy trên host: bash tools/install_chuyenmon_host.sh
set -e
CDS_ROOT="${1:-/home/capnachi/cds.noitruxinman.edu.vn}"
cd "$CDS_ROOT"

echo "==> Thư mục CDS: $CDS_ROOT"

# 1. Lấy code PCCM vào /chuyenmon
if [ -d chuyenmon/.git ]; then
  echo "==> Cập nhật repo chuyenmon..."
  git -C chuyenmon pull --ff-only || true
elif [ -d chuyenmon ] && [ -f chuyenmon/index.php ]; then
  echo "==> Đã có thư mục chuyenmon (không phải git) — giữ nguyên code"
else
  echo "==> Clone PCCM → chuyenmon/"
  rm -rf chuyenmon.tmp
  git clone --depth 1 https://github.com/nguyenhongdanxm/pccm.git chuyenmon.tmp
  mkdir -p chuyenmon
  rsync -a --exclude '.git' chuyenmon.tmp/ chuyenmon/
  rm -rf chuyenmon.tmp
fi

# 2. BASE_URL trong config PCCM
if [ -f chuyenmon/includes/config.php ]; then
  sed -i "s|define('BASE_URL', '/pccm/');|define('BASE_URL', '/chuyenmon/');|g" chuyenmon/includes/config.php
  sed -i "s|define('BASE_URL', \"/pccm/\");|define('BASE_URL', '/chuyenmon/');|g" chuyenmon/includes/config.php
  echo "==> BASE_URL → /chuyenmon/"
fi

# 3. Copy data từ bản PCCM cũ nếu chuyenmon/data còn trống
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
    echo "==> chuyenmon/data đã có dữ liệu — không ghi đè (backup tay nếu cần)"
  fi
else
  echo "==> Không tìm thấy data PCCM cũ — app sẽ tạo mặc định"
fi

chmod -R u+rwX chuyenmon/data 2>/dev/null || true

echo ""
echo "Xong. Mở: https://cds.noitruxinman.edu.vn/chuyenmon/"
echo "Đăng nhập CDS trước → vào Chuyên môn (session dùng chung)."
