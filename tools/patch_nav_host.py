#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Chạy trên host trong thư mục CDS để gắn menu dropdown vào csdl.php / noitru.php."""
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent

CSDL_OLD = """<nav class=\"navbar navbar-dark mb-4\">
  <div class=\"container-fluid px-3 px-lg-4\">
    <a class=\"navbar-brand fw-bold\" href=\"<?= BASE_URL ?>csdl.php\"><i class=\"bi bi-database\"></i> Cơ sở dữ liệu</a>
    <div class=\"d-flex gap-2\">
      <a href=\"<?= BASE_URL ?>\" class=\"btn btn-outline-light btn-sm\">Hệ sinh thái</a>
      <a href=\"<?= BASE_URL ?>admin.php\" class=\"btn btn-outline-light btn-sm\">Quản trị</a>
      <a href=\"<?= BASE_URL ?>logout.php\" class=\"btn btn-warning btn-sm text-dark\">Thoát</a>
    </div>
  </div>
</nav>"""

CSDL_NEW = "<?php include __DIR__ . '/includes/nav_boot_csdl.php'; ?>"

NOITRU_OLD = """<nav class=\"navbar navbar-dark mb-4\">
  <div class=\"container-fluid px-3 px-lg-4\">
    <a class=\"navbar-brand fw-bold\" href=\"<?= BASE_URL ?>noitru.php\"><i class=\"bi bi-building\"></i> Quản lý nội trú</a>
    <div class=\"d-flex gap-2\">
      <a href=\"<?= BASE_URL ?>\" class=\"btn btn-outline-light btn-sm\">Hệ sinh thái</a>
      <a href=\"<?= BASE_URL ?>csdl.php\" class=\"btn btn-outline-light btn-sm\">CSDL</a>
      <a href=\"<?= BASE_URL ?>logout.php\" class=\"btn btn-warning btn-sm text-dark\">Thoát</a>
    </div>
  </div>
</nav>"""

NOITRU_NEW = "<?php include __DIR__ . '/includes/nav_boot_noitru.php'; ?>"

def patch(path: Path, old: str, new: str):
    if not path.exists():
        print(f"SKIP missing {path}")
        return
    t = path.read_text(encoding="utf-8")
    if "nav_boot_" in t and new in t:
        print(f"OK already {path.name}")
        return
    if old not in t:
        # fallback: chèn ngay sau <body>
        if "<body>" in t and "nav_boot_" not in t:
            t = t.replace("<body>", "<body>\n" + new, 1)
            path.write_text(t, encoding="utf-8")
            print(f"OK injected after body {path.name}")
            return
        print(f"WARN pattern not found {path.name}")
        return
    path.write_text(t.replace(old, new, 1), encoding="utf-8")
    print(f"OK replaced {path.name}")

def main():
    patch(ROOT / "csdl.php", CSDL_OLD, CSDL_NEW)
    patch(ROOT / "noitru.php", NOITRU_OLD, NOITRU_NEW)

if __name__ == "__main__":
    main()
