#!/bin/bash
set -euo pipefail
DEPLOY_ROOT="${1:-/home/capnachi/cds.noitruxinman.edu.vn/}"
CM_INCLUDE="${DEPLOY_ROOT%/}/chuyenmon/includes"
CM_HEADER="$CM_INCLUDE/header.php"
SOURCE_FILE="includes/chuyenmon_module_switcher.php"

if [ ! -d "$CM_INCLUDE" ] || [ ! -f "$CM_HEADER" ]; then
  echo "Chuyên môn chưa được cài tại $CM_INCLUDE; bỏ qua bộ chuyển module."
  exit 0
fi

/bin/cp "$SOURCE_FILE" "$CM_INCLUDE/cds_module_switcher.php"
if ! /usr/bin/grep -q "cds_module_switcher.php" "$CM_HEADER"; then
  /bin/sed -i "/<body>/a\\<?php require_once __DIR__ . '/cds_module_switcher.php'; ?>" "$CM_HEADER"
fi
echo "Đã gắn bộ chuyển module vào Chuyên môn."

