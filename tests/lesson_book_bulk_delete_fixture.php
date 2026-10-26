<?php
function lb_is_admin(){return true;}function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}define('BASE_URL','/chuyenmon/');$csrf='fixture';$weekKey='6';ob_start();require __DIR__.'/../chuyenmon/includes/lesson_book_bulk_delete_view.php';$html=ob_get_clean();
$html.='<input type="checkbox" id="lbDeleteSelectAll"><input type="checkbox" class="lb-delete-select" data-id="a" data-version="fixture-version" data-signed-at="signed" data-updated-at="updated"><input type="checkbox" class="lb-delete-select" data-id="b" data-version="fixture-version" data-signed-at="signed" data-updated-at="updated"><button id="lbBulkSign">Ký</button>';
file_put_contents('/tmp/lb-bulk-delete-fixture.html',$html);
