<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/noitru_store.php';
require_login();
require_module('noitru','view');
require_perm('nt.yte');
$healthView='history';
require __DIR__.'/includes/noitru_health_history_filter.php';
require __DIR__.'/includes/noitru_health_excel_export.php';
nt_health_export_xlsx($filteredHealth,$historyFrom,$historyTo);
