#!/bin/bash
# Deploy noitru module from GitHub raw main
set -e
BASE="${1:-.}"
cd "$BASE"
RAW="https://raw.githubusercontent.com/nguyenhongdanxm/cds/main"
echo "Deploy from $RAW to $(pwd)"
for f in noitru_list.php noitru_attendance.php noitru_meals.php; do
  echo "→ $f"
  curl -fsSL "$RAW/$f" -o "$f" && echo "  OK $(wc -c < $f) bytes" || echo "  FAIL $f"
done
mkdir -p includes
for f in noitru_meal_store.php noitru_att_shifts.php nav_boot_noitru.php noitru_redirect_tabs.php noitru_shell.php noitru_layout.css noitru_tab_boarders.php noitru_store_boarders_helpers.php noitru_redirect_boarders.php; do
  echo "→ includes/$f"
  curl -fsSL "$RAW/includes/$f" -o "includes/$f" && echo "  OK" || echo "  FAIL"
done
echo "Xong. Nhớ thêm vào noitru.php: require __DIR__ . '/includes/noitru_redirect_tabs.php';"
