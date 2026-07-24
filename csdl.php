<?php
require_once 'includes/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cơ sở dữ liệu dùng chung – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>body{background:#f0f4f8}.navbar{background:#1F4E79!important}</style>
</head>
<body>
<nav class="navbar navbar-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="<?= BASE_URL ?>"><i class="bi bi-database"></i> Cơ sở dữ liệu</a>
    <a href="<?= BASE_URL ?>admin.php" class="btn btn-outline-light btn-sm">Quản trị</a>
  </div>
</nav>
<div class="container pb-5">
  <h3>Cơ sở dữ liệu dùng chung</h3>
  <p class="text-muted">“Trái tim” hệ sinh thái: giáo viên, lớp, năm học, tài khoản — các module khác đọc từ đây.</p>

  <div class="row g-3">
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <h5><i class="bi bi-people text-primary"></i> Giáo viên</h5>
          <p class="small text-muted mb-2">Nguồn chuẩn cho Chuyên môn, Nội trú, Thi đua…</p>
          <span class="badge bg-warning text-dark">Đồng bộ từ PCCM (bước sau)</span>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <h5><i class="bi bi-building text-success"></i> Lớp / khối</h5>
          <p class="small text-muted mb-2">6A–12B, cấp THCS/THPT</p>
          <span class="badge bg-warning text-dark">Đồng bộ từ PCCM (bước sau)</span>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-body">
          <h5><i class="bi bi-person-badge text-info"></i> Tài khoản SSO</h5>
          <p class="small text-muted mb-2">Một đăng nhập cho mọi nhánh</p>
          <span class="badge bg-success">Đang dùng (users.json)</span>
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-light border mt-4">
    <strong>Kế hoạch:</strong> chuyển teachers/classes của PCCM thành nguồn lõi tại CDS;
    PCCM đọc/ghi qua path dùng chung hoặc API trên cùng server.
  </div>
</div>
</body>
</html>
