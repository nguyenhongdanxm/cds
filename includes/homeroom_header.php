<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? 'Tiện ích chủ nhiệm') ?> – <?= e(SCHOOL_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body{background:#f1f5f9;color:#1e293b;font-family:system-ui,-apple-system,'Segoe UI',sans-serif}
.home-header{background:linear-gradient(110deg,#123759,#205e91);color:#fff}
.home-header a{color:#fff;text-decoration:none}
.home-header a:hover{text-decoration:underline}
.home-header .container-fluid{min-height:68px;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.home-brand{display:flex;align-items:center;gap:.7rem;font-weight:750;font-size:1.1rem}
.home-brand i{display:grid;place-items:center;width:40px;height:40px;border-radius:50%;background:#ffffff30}
main{max-width:1500px;margin:auto;padding:12px clamp(8px,2vw,28px) 48px}
.card{border-color:#dbe5ee;box-shadow:0 2px 12px #18324b08}
@media print{.home-header,.cds-launcher{display:none!important}body{background:#fff}main{padding:0}}
</style>
</head>
<body>
<header class="home-header"><div class="container-fluid"><a class="home-brand" href="/tienich_chunhiem.php"><i class="bi bi-person-workspace"></i><span>Tiện ích chủ nhiệm</span></a><a href="/"><i class="bi bi-grid-3x3-gap-fill me-1"></i> Hệ sinh thái CDS</a></div></header>
<main>
