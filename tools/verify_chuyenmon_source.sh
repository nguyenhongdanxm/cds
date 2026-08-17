#!/bin/bash
# Dừng deploy trước khi chép tệp nếu nguồn Chuyên môn thiếu hoặc sai cú pháp.
set -euo pipefail

ROOT="${1:-$(pwd)}"
required=(
  chuyenmon/includes/config.php
  chuyenmon/includes/functions.php
  chuyenmon/includes/header.php
  chuyenmon/includes/footer.php
  chuyenmon/includes/cm_docs.php
  chuyenmon/includes/subject_meta.php
  chuyenmon/index.php
  chuyenmon/tracuu.php
  chuyenmon/them.php
  chuyenmon/danhsach.php
  chuyenmon/kehoach.php
  chuyenmon/baocao.php
  includes/json_store.php
  includes/session_user.php
  includes/chuyenmon_permission_runtime.php
)

for file in "${required[@]}"; do
  if [ ! -f "$ROOT/$file" ]; then
    echo "Thiếu tệp bắt buộc: $file" >&2
    exit 1
  fi
done

# Các thành phần tích hợp được giữ cả ở nguồn chung và bản chạy trong
# chuyenmon/includes. Nếu một bên được sửa mà quên đồng bộ, dừng deploy.
declare -A mirrored=(
  [includes/chuyenmon_auth_gate.php]=chuyenmon/includes/cds_auth_gate.php
  [includes/chuyenmon_module_switcher.php]=chuyenmon/includes/cds_module_switcher.php
  [includes/chuyenmon_responsive_layout.php]=chuyenmon/includes/cds_responsive_layout.php
  [includes/chuyenmon_ui_cleanup.php]=chuyenmon/includes/cds_ui_cleanup.php
  [includes/chuyenmon_teacher_compact.php]=chuyenmon/includes/cds_teacher_compact.php
  [includes/chuyenmon_fractional_periods.php]=chuyenmon/includes/cds_fractional_periods.php
  [includes/chuyenmon_manual_assignment.php]=chuyenmon/includes/cds_manual_assignment.php
  [includes/chuyenmon_manual_assignment_processor.php]=chuyenmon/includes/cds_manual_assignment_processor.php
  [includes/chuyenmon_manual_table_fix.php]=chuyenmon/includes/cds_manual_table_fix.php
  [includes/chuyenmon_manual_delete_fix.php]=chuyenmon/includes/cds_manual_delete_fix.php
  [includes/chuyenmon_manual_save_fix.php]=chuyenmon/includes/cds_manual_save_fix.php
  [includes/chuyenmon_manual_delete_endpoint.php]=chuyenmon/cds_manual_delete.php
  [includes/chuyenmon_manual_save_endpoint.php]=chuyenmon/cds_manual_save.php
)
for source in "${!mirrored[@]}"; do
  target="${mirrored[$source]}"
  if ! cmp -s "$ROOT/$source" "$ROOT/$target"; then
    echo "Bản tích hợp Chuyên môn bị lệch: $source <> $target" >&2
    exit 1
  fi
done

PHP_BIN=""
for candidate in /usr/local/bin/php /usr/bin/php; do
  if [ -x "$candidate" ]; then PHP_BIN="$candidate"; break; fi
done
if [ -z "$PHP_BIN" ]; then
  echo "Không tìm thấy PHP CLI để kiểm tra cú pháp." >&2
  exit 1
fi

# cPanel có thể chạy deployment trong môi trường không gắn /dev/fd.
# Dùng pipeline thay cho process substitution để vẫn kiểm tra tuần tự và
# giữ nguyên cơ chế dừng deploy ngay khi một tệp PHP sai cú pháp.
find "$ROOT/chuyenmon" -type f -name '*.php' -print | while IFS= read -r file; do
  "$PHP_BIN" -l "$file" >/dev/null
done

for file in \
  "$ROOT/includes/auth.php" \
  "$ROOT/includes/permissions.php" \
  "$ROOT/includes/json_store.php" \
  "$ROOT/includes/session_user.php" \
  "$ROOT/includes/chuyenmon_permission_runtime.php" \
  "$ROOT/includes/drive_action_registry.php"; do
  "$PHP_BIN" -l "$file" >/dev/null
done

echo "CHUYENMON_SOURCE_OK"
