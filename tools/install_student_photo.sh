#!/bin/bash
set -euo pipefail
DEPLOY_ROOT="${1:-/home/capnachi/cds.noitruxinman.edu.vn/}"
AUTH_FILE="${DEPLOY_ROOT%/}/includes/auth.php"
if [ ! -f "$AUTH_FILE" ]; then
  echo "Không tìm thấy $AUTH_FILE"
  exit 0
fi
if ! /usr/bin/grep -q "csdl_student_photo_hook.php" "$AUTH_FILE"; then
  /bin/sed -i "/require_once __DIR__ . '\/global_ui.php';/i\\require_once __DIR__ . '/csdl_student_photo_hook.php';\nrequire_once __DIR__ . '/csdl_student_photo_ui.php';" "$AUTH_FILE"
fi
mkdir -p "${DEPLOY_ROOT%/}/data/student_photos"
chmod 755 "${DEPLOY_ROOT%/}/data/student_photos" || true
echo "Đã tích hợp ảnh thẻ học sinh."
