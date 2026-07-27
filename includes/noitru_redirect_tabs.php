<?php
/** Redirect tab sang trang standalone — include sớm trong noitru.php sau $tab = ... */
$tab = $tab ?? ($_GET['tab'] ?? 'overview');
$map = [
    'meals'      => 'noitru_meals.php',
    'attendance' => 'noitru_attendance.php',
    'boarders'   => 'noitru_list.php',
    // aliases
    'baoan'      => 'noitru_meals.php',
    'diemdanh'   => 'noitru_attendance.php',
    'danhsach'   => 'noitru_list.php',
];
if (isset($map[$tab])) {
    header('Location: ' . BASE_URL . $map[$tab]);
    exit;
}
