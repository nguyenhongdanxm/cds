#!/bin/bash
# Triển khai CDS trên cPanel với nhật ký rõ ràng và không cho tiến trình treo vô hạn.
set -euo pipefail

SOURCE_ROOT="${1:-$PWD}"
DEPLOY_ROOT="${2:-/home/capnachi/cds.noitruxinman.edu.vn/}"

if [ ! -d "$SOURCE_ROOT" ] || [ "$DEPLOY_ROOT" != "/home/capnachi/cds.noitruxinman.edu.vn/" ]; then
  echo "CDS_DEPLOY_ERROR: đường dẫn triển khai không hợp lệ." >&2
  exit 2
fi

echo "CDS_DEPLOY_1/6: kiểm tra nguồn Chuyên môn"
/usr/bin/timeout 45 /bin/bash "$SOURCE_ROOT/tools/verify_chuyenmon_source.sh" "$SOURCE_ROOT"

echo "CDS_DEPLOY_2/6: sao chép tài nguyên và thư viện dùng chung"
/usr/bin/timeout 30 /bin/cp -R "$SOURCE_ROOT/assets" "$DEPLOY_ROOT"
/usr/bin/timeout 30 /bin/cp -R "$SOURCE_ROOT/includes" "$DEPLOY_ROOT"

echo "CDS_DEPLOY_3/6: cập nhật nhóm quyền quản trị viên"
/usr/bin/timeout 30 /usr/local/bin/php "$SOURCE_ROOT/tools/install_manager_permission_group.php" "$DEPLOY_ROOT/includes/permissions.php"

echo "CDS_DEPLOY_4/6: sao chép phân hệ Chuyên môn"
/usr/bin/timeout 30 /bin/cp -R "$SOURCE_ROOT/chuyenmon" "$DEPLOY_ROOT"
/usr/bin/timeout 30 /usr/local/bin/php "$SOURCE_ROOT/tools/install_manager_chuyenmon_scope.php" "$DEPLOY_ROOT"
/bin/sed -i 's|chuyenmon-unified.css?v=20260817-1|chuyenmon-unified.css?v=20260820-3|g' "$DEPLOY_ROOT/chuyenmon/includes/header.php"

echo "CDS_DEPLOY_5/6: tích hợp ảnh học sinh"
/usr/bin/timeout 30 /bin/bash "$SOURCE_ROOT/tools/install_student_photo.sh" "$DEPLOY_ROOT"

echo "CDS_DEPLOY_6/6: sao chép các trang chính"
cd "$SOURCE_ROOT"
/usr/bin/timeout 45 /bin/cp activity.php admin.php hoclieu.php hoclieu_file.php chuyenmon.php csdl.php csdl_preweeks.php csdl_export.php csdl_student_cards.php danhgia.php dashboard_settings.php database_admin.php index.php login.php logout.php manifest.webmanifest noitru.php noitru_attendance.php noitru_list.php noitru_assign.php noitru_assign_enhanced.php noitru_assign_sync.php noitru_room_template.php noitru_room_roles.php noitru_room_roles_data.php noitru_room_quick_save.php push_api.php student_card_students.php student_photo.php student_verify.php sw.js thoikhoabieu.php thuvien.php thuvien_book_supplement.php thuvien_bienban.php thietbi_phieu.php thidua.php thidua_baiviet.php users.php vanban.php "$DEPLOY_ROOT"

echo "CDS_DEPLOY_OK"
