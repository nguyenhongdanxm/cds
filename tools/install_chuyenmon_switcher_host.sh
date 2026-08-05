#!/bin/bash
set -euo pipefail
DEPLOY_ROOT="${1:-/home/capnachi/cds.noitruxinman.edu.vn/}"
CM_INCLUDE="${DEPLOY_ROOT%/}/chuyenmon/includes"
CM_HEADER="$CM_INCLUDE/header.php"
SWITCHER_SOURCE="includes/chuyenmon_module_switcher.php"
LAYOUT_SOURCE="includes/chuyenmon_responsive_layout.php"

if [ ! -d "$CM_INCLUDE" ] || [ ! -f "$CM_HEADER" ]; then
  echo "Chuyên môn chưa được cài tại $CM_INCLUDE; bỏ qua bộ tích hợp giao diện."
  exit 0
fi

/bin/cp "$SWITCHER_SOURCE" "$CM_INCLUDE/cds_module_switcher.php"
/bin/cp "$LAYOUT_SOURCE" "$CM_INCLUDE/cds_responsive_layout.php"

if ! /usr/bin/grep -q "cds_module_switcher.php" "$CM_HEADER"; then
  /bin/sed -i "/<body>/a\\<?php require_once __DIR__ . '/cds_module_switcher.php'; ?>" "$CM_HEADER"
fi

if ! /usr/bin/grep -q "cds_responsive_layout.php" "$CM_HEADER"; then
  /bin/sed -i "/<body>/a\\<?php require_once __DIR__ . '/cds_responsive_layout.php'; ?>" "$CM_HEADER"
fi

echo "Đã gắn bộ chuyển module và bố cục responsive vào Chuyên môn."
