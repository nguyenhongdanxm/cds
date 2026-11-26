# Bảng thành tích

Entry: `hoclieu_thanhtich.php`, in Tiện ích → Công cụ.

Setup: grant `hl.thanhtich` in Tài khoản và quyền (Xem / Sửa / Xóa), then an administrator assigns the exact account, class and subject in the tool’s Phân quyền tab. Central class limits still apply. Sửa permits awards, criterion management and undoing own events. Xóa also permits undoing other teachers’ events in the assigned board. Viewer accounts stay read-only.

Each academic year / class / subject has independent criteria and an append-only event ledger in school-local `data/achievement_boards/<board-hash>.json` files, with grants in `data/achievement_board.json`. Award snapshots retain student/criterion labels and actor/time. Undo marks an event void without erasing it. Atomic JSON updates and request IDs avoid lost updates and duplicate award retries. Reports aggregate the selected week, month or custom range; the latest 200 history events are shown while totals include the complete range. CSV exports use UTF-8 BOM and spreadsheet-formula protection.

No student information is sent to an external service. Student mascots are original CSS artwork; sound is optional procedural Web Audio after user interaction and motion respects reduced-motion preferences.

Cover: `assets/game-covers/illustrated-v3/achievement.webp`, 720×405, generated with the built-in image tool. Prompt: original friendly classroom mascots celebrating with a golden trophy, stars, badges and plus-point tokens; pastel lavender/turquoise/gold; large Vietnamese title “BẢNG THÀNH TÍCH”, no brand characters, no logos or other text.
