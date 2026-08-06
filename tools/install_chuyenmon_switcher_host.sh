#!/bin/bash
set -euo pipefail
DEPLOY_ROOT="${1:-/home/capnachi/cds.noitruxinman.edu.vn/}"
CM_ROOT="${DEPLOY_ROOT%/}/chuyenmon"
CM_INCLUDE="$CM_ROOT/includes"
CM_HEADER="$CM_INCLUDE/header.php"
SWITCHER_SOURCE="includes/chuyenmon_module_switcher.php"
LAYOUT_SOURCE="includes/chuyenmon_responsive_layout.php"
CLEANUP_SOURCE="includes/chuyenmon_ui_cleanup.php"
AUTH_GATE_SOURCE="includes/chuyenmon_auth_gate.php"
FRACTIONAL_SOURCE="includes/chuyenmon_fractional_periods.php"
MANUAL_SOURCE="includes/chuyenmon_manual_assignment.php"
FRACTIONAL_PATCHER="tools/patch_chuyenmon_fractional.php"
GLOBAL_UI_SOURCE="assets/cds-global-ui.css"

if [ ! -d "$CM_INCLUDE" ] || [ ! -f "$CM_HEADER" ]; then
  echo "Chuyên môn chưa được cài tại $CM_INCLUDE; bỏ qua bộ tích hợp giao diện."
  exit 0
fi

/bin/cp "$SWITCHER_SOURCE" "$CM_INCLUDE/cds_module_switcher.php"
/bin/cp "$LAYOUT_SOURCE" "$CM_INCLUDE/cds_responsive_layout.php"
/bin/cp "$CLEANUP_SOURCE" "$CM_INCLUDE/cds_ui_cleanup.php"
/bin/cp "$AUTH_GATE_SOURCE" "$CM_INCLUDE/cds_auth_gate.php"
/bin/cp "$FRACTIONAL_SOURCE" "$CM_INCLUDE/cds_fractional_periods.php"
/bin/cp "$MANUAL_SOURCE" "$CM_INCLUDE/cds_manual_assignment.php"

# Dùng một lớp giao diện nền tảng chung, không ghi đè mật độ riêng của Chuyên môn.
if [ -f "$GLOBAL_UI_SOURCE" ] && ! /usr/bin/grep -q "cds-global-ui.css" "$CM_HEADER"; then
  /bin/sed -i "/<\/head>/i\\<link rel=\"stylesheet\" href=\"/assets/cds-global-ui.css?v=20260806-2\">" "$CM_HEADER"
fi

# Kiểm tra đăng nhập CDS trước khi xuất HTML.
if ! /usr/bin/grep -q "cds_auth_gate.php" "$CM_HEADER"; then
  /bin/sed -i "/require_once __DIR__ . '\/functions.php';/a\\require_once __DIR__ . '/cds_auth_gate.php';" "$CM_HEADER"
fi

# Giao diện chung được nạp ngay sau body.
if ! /usr/bin/grep -q "cds_module_switcher.php" "$CM_HEADER"; then
  /bin/sed -i "/<body>/a\\<?php require_once __DIR__ . '/cds_module_switcher.php'; ?>" "$CM_HEADER"
fi
if ! /usr/bin/grep -q "cds_responsive_layout.php" "$CM_HEADER"; then
  /bin/sed -i "/<body>/a\\<?php require_once __DIR__ . '/cds_responsive_layout.php'; ?>" "$CM_HEADER"
fi
if ! /usr/bin/grep -q "cds_ui_cleanup.php" "$CM_HEADER"; then
  /bin/sed -i "/<body>/a\\<?php require_once __DIR__ . '/cds_ui_cleanup.php'; ?>" "$CM_HEADER"
fi
if ! /usr/bin/grep -q "cds_fractional_periods.php" "$CM_HEADER"; then
  /bin/sed -i "/<body>/a\\<?php require_once __DIR__ . '/cds_fractional_periods.php'; ?>" "$CM_HEADER"
fi
if ! /usr/bin/grep -q "cds_manual_assignment.php" "$CM_HEADER"; then
  /bin/sed -i "/<body>/a\\<?php require_once __DIR__ . '/cds_manual_assignment.php'; ?>" "$CM_HEADER"
fi

# Không ép các trường số tiết về số nguyên khi xử lý POST/lưu dữ liệu.
if [ -f "$FRACTIONAL_PATCHER" ]; then
  /usr/local/bin/php "$FRACTIONAL_PATCHER" "$CM_ROOT" 2>/dev/null || /usr/bin/php "$FRACTIONAL_PATCHER" "$CM_ROOT"
fi

# Không còn màn hình đăng nhập riêng trong Chuyên môn.
cat > "$CM_ROOT/login.php" <<'PHP'
<?php
$next = (string)($_GET['next'] ?? '/chuyenmon/');
header('Location: /login.php?next=' . urlencode($next));
exit;
PHP

echo "Đã gắn bố cục CDS, phân công thủ công, đăng nhập chung và hỗ trợ số tiết lẻ 0,1 cho Chuyên môn."
