<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/vanban_store.php';
require_once __DIR__ . '/includes/vanban_engagement.php';
require_once __DIR__ . '/includes/push_notifications.php';
require_login();

$user = current_user();
$isAdmin = ($user['role'] ?? '') === 'admin';
$canManage = can_perm_level('vb.quanly', 'edit') || can_module('vanban', 'edit');
$canNumber = can_perm_level('vb.layso', 'edit') || $canManage;
$canArchive = can_perm_level('vb.hosoluutru', 'edit') || $canManage;
$canEngagementManage = $isAdmin;
$tab = (string)($_GET['tab'] ?? 'overview');
if ($tab === 'polls' || $tab === 'surveys') {
    $_GET['engagement_tab'] = $tab;
    $tab = 'engagement';
}
if (!in_array($tab, ['overview','documents','numbers','archives','engagement','feedback'], true)) $tab = 'overview';
if ($tab === 'numbers' && !$canNumber) $tab = 'overview';
if (empty($_SESSION['vb_csrf'])) $_SESSION['vb_csrf'] = bin2hex(random_bytes(24));
$csrf = (string)$_SESSION['vb_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnTab = (string)($_POST['return_tab'] ?? $tab);
    try {
        if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) throw new RuntimeException('Phiên làm việc đã hết hạn.');
        $action = (string)($_POST['action'] ?? '');
        if (in_array($action, vb_engagement_actions(), true)) {
            vb_engagement_process($action, $user, $canEngagementManage);
        } elseif ($action === 'create_document') {
            if (!$canManage) throw new RuntimeException('Bạn không có quyền cập nhật văn bản.');
            $editId = vb_clean((string)($_POST['id'] ?? ''), 80);
            $documents = vb_rows(VANBAN_DOCUMENTS_FILE);
            $existingDocument = null;
            foreach ($documents as $row) if (($row['id'] ?? '') === $editId) { $existingDocument = $row; break; }
            if ($editId !== '' && !$existingDocument) throw new RuntimeException('Không tìm thấy văn bản cần sửa.');
            $reserved = vb_find_number(vb_clean((string)($_POST['reserved_id'] ?? ''), 80));
            $title = vb_clean((string)($_POST['title'] ?? ''), 500);
            $symbol = vb_clean((string)($_POST['symbol'] ?? ''), 120);
            $issuedDate = vb_date((string)($_POST['issued_date'] ?? ''));
            $issuer = vb_clean((string)($_POST['issuer'] ?? ''), 180);
            if ($reserved) {
                if ($title === '') $title = (string)($reserved['title'] ?? '');
                if ($symbol === '') $symbol = (string)($reserved['symbol'] ?? '');
                if ($issuedDate === '') $issuedDate = (string)($reserved['issued_date'] ?? '');
                if ($issuer === '') $issuer = (string)($reserved['issuer'] ?? '');
            }
            if ($title === '' || $symbol === '' || $issuedDate === '' || $issuer === '') throw new RuntimeException('Hãy nhập đủ số ký hiệu, tên, ngày và đơn vị ban hành.');
            $type = vb_clean((string)($_POST['type'] ?? ''), 80);
            $dashboardVisible = !empty($_POST['dashboard_visible']);
            $dashboardFrom = vb_datetime_local((string)($_POST['dashboard_from'] ?? ''));
            $dashboardTo = vb_datetime_local((string)($_POST['dashboard_to'] ?? ''));
            if ($dashboardVisible && ($dashboardFrom === '' || $dashboardTo === '')) throw new RuntimeException('Hãy chọn đủ thời gian bắt đầu và hết hiển thị trên trang tổng quan.');
            if ($dashboardVisible && strtotime($dashboardTo) <= strtotime($dashboardFrom)) throw new RuntimeException('Thời gian hết hiển thị phải sau thời gian bắt đầu.');
            $attachments = $existingDocument ? vb_document_attachments($existingDocument) : [];
            $path = (string)($existingDocument['file_path'] ?? ($attachments[0]['path'] ?? ''));
            $removeFile = !empty($_POST['remove_file']);
            $newFiles = vb_upload_many('document_files', 'documents', ['title'=>$title,'document_title'=>$title,'document_date'=>$issuedDate,'issuer'=>$issuer,'symbol'=>$symbol]);
            // Tương thích biểu mẫu cũ trong trường hợp trình duyệt còn lưu cache.
            $legacyNewPath = vb_upload('document_file', 'documents', ['title'=>$title,'document_title'=>$title,'document_date'=>$issuedDate,'issuer'=>$issuer,'symbol'=>$symbol]);
            if ($legacyNewPath !== '') $newFiles[] = ['path'=>$legacyNewPath, 'name'=>basename($legacyNewPath)];
            if ($removeFile && $attachments) {
                foreach ($attachments as $item) if (!vb_delete_file((string)$item['path'])) throw new RuntimeException('Không xóa được một tệp hiện tại trên Google Drive.');
                $attachments = [];
            }
            $attachments = array_values(array_merge($attachments, $newFiles));
            if (!$attachments && !$existingDocument && !empty($reserved['file_path'])) {
                $attachments[] = ['path'=>(string)$reserved['file_path'], 'name'=>(string)($reserved['file_name'] ?? basename((string)$reserved['file_path']))];
            }
            $path = (string)($attachments[0]['path'] ?? '');
            if (!$attachments && !$existingDocument) throw new RuntimeException('Hãy chọn ít nhất một tệp văn bản cần tải lên.');
            $id = $editId !== '' ? $editId : vb_id('doc');
            $record = [
                'id'=>$id, 'type'=>$type ?: 'Khác', 'symbol'=>$symbol, 'title'=>$title,
                'issued_date'=>$issuedDate, 'issuer'=>$issuer,
                'issuer_level'=>vb_clean((string)($_POST['issuer_level'] ?? ''), 80),
                'field'=>vb_clean((string)($_POST['field'] ?? ''), 80),
                'signer'=>vb_clean((string)($_POST['signer'] ?? ($reserved['signer'] ?? '')), 180),
                'notes'=>vb_clean((string)($_POST['notes'] ?? ''), 1000), 'file_path'=>$path,
                'attachments'=>$attachments,
                'featured'=>false,
                'dashboard_visible'=>$dashboardVisible,
                'dashboard_from'=>$dashboardVisible ? $dashboardFrom : '',
                'dashboard_to'=>$dashboardVisible ? $dashboardTo : '',
                'reserved_id'=>$reserved['id'] ?? ($existingDocument['reserved_id'] ?? ''),
                'created_by'=>$existingDocument['created_by'] ?? ($user['name'] ?? ''),
                'created_at'=>$existingDocument['created_at'] ?? date('c'),
                'updated_by'=>$user['name'] ?? '', 'updated_at'=>date('c')
            ];
            if ($existingDocument) {
                foreach ($documents as &$row) if (($row['id'] ?? '') === $id) { $row = $record; break; }
                unset($row);
            } else $documents[] = $record;
            if (!vb_save_rows(VANBAN_DOCUMENTS_FILE, $documents)) throw new RuntimeException('Không lưu được dữ liệu văn bản.');
            if ($reserved) {
                $numbers = vb_rows(VANBAN_NUMBERS_FILE);
                foreach ($numbers as &$row) if (($row['id'] ?? '') === ($reserved['id'] ?? '')) {
                    $row['status'] = 'published'; $row['document_id'] = $id; $row['updated_at'] = date('c');
                }
                unset($row); vb_save_rows(VANBAN_NUMBERS_FILE, $numbers);
            }
            $pushResult = null;
            if ($dashboardVisible && !empty($_POST['push_notification'])) {
                $pushResult = cds_push_publish(
                    'CDS – Có văn bản mới',
                    trim($symbol . ' · ' . $title),
                    vb_file_url($path),
                    ['source_id'=>$id,'expires_at'=>$dashboardTo,'audience'=>['all'],'level'=>!empty($_POST['push_urgent'])?'urgent':'normal']
                );
            }
            $message = $existingDocument ? 'Đã sửa văn bản.' : 'Đã cập nhật văn bản và lưu tệp thành công.';
            if (is_array($pushResult)) $message .= ' Đã gửi chuông tới ' . (int)($pushResult['sent']??0) . '/' . (int)($pushResult['devices']??0) . ' thiết bị.';
            flash($message);
        } elseif ($action === 'delete_document') {
            if (!$canManage) throw new RuntimeException('Bạn không có quyền xóa văn bản.');
            $id = vb_clean((string)($_POST['id'] ?? ''), 80);
            $documents = vb_rows(VANBAN_DOCUMENTS_FILE); $deleted = null; $kept = [];
            foreach ($documents as $row) { if (($row['id'] ?? '') === $id) $deleted = $row; else $kept[] = $row; }
            if (!$deleted) throw new RuntimeException('Không tìm thấy văn bản cần xóa.');
            if (!vb_save_rows(VANBAN_DOCUMENTS_FILE, $kept)) throw new RuntimeException('Không xóa được văn bản.');
            if (!empty($deleted['reserved_id'])) {
                $numberRows = vb_rows(VANBAN_NUMBERS_FILE);
                foreach ($numberRows as &$row) if (($row['id'] ?? '') === $deleted['reserved_id']) {
                    $row['status']='reserved'; unset($row['document_id']); $row['updated_at']=date('c');
                }
                unset($row); vb_save_rows(VANBAN_NUMBERS_FILE, $numberRows);
            }
            flash('Đã xóa nội dung văn bản khỏi danh mục. Tệp gốc trên Drive vẫn được giữ an toàn.', 'warning');
        } elseif ($action === 'reserve_number') {
            if (!$canNumber) throw new RuntimeException('Bạn không có quyền lấy số văn bản.');
            $editId = vb_clean((string)($_POST['id'] ?? ''), 80);
            $book = (string)($_POST['book'] ?? 'other');
            if (!in_array($book, ['decision','other'], true)) $book = 'other';
            $date = vb_date((string)($_POST['issued_date'] ?? '')) ?: date('Y-m-d');
            $year = (int)substr($date, 0, 4);
            $symbol = vb_clean((string)($_POST['symbol'] ?? ''), 120);
            if ($symbol === '') throw new RuntimeException('Hãy nhập số, ký hiệu văn bản.');
            if ($book === 'other' && str_contains($symbol, '...')) {
                throw new RuntimeException('Vui lòng thay dấu ... bằng loại văn bản, ví dụ BC, KH hoặc CV.');
            }
            $title = vb_clean((string)($_POST['title'] ?? ''), 500);
            if ($title === '') throw new RuntimeException('Hãy nhập tên hoặc trích yếu văn bản.');
            $numbers = vb_rows(VANBAN_NUMBERS_FILE);
            foreach ($numbers as $existing) {
                if (($existing['id'] ?? '') === $editId) continue;
                if (($existing['book'] ?? '') === $book && (int)($existing['year'] ?? 0) === $year
                    && mb_strtolower(trim((string)($existing['symbol'] ?? '')), 'UTF-8') === mb_strtolower($symbol, 'UTF-8')) {
                    throw new RuntimeException('Số ký hiệu này đã tồn tại trong sổ năm ' . $year . '.');
                }
            }
            preg_match('/^\s*(\d+)/u', $symbol, $numberMatch);
            $number = isset($numberMatch[1]) ? (int)$numberMatch[1] : 0;
            $existingNumber = null;
            foreach ($numbers as $row) if (($row['id'] ?? '') === $editId) { $existingNumber=$row; break; }
            if ($editId !== '' && !$existingNumber) throw new RuntimeException('Không tìm thấy số văn bản cần sửa.');
            $record = [
                'id'=>$editId !== '' ? $editId : vb_id('num'), 'book'=>$book, 'year'=>$year, 'number'=>$number,
                'symbol'=>$symbol, 'title'=>$title, 'issued_date'=>$date,
                'issuer'=>vb_clean((string)($_POST['issuer'] ?? SCHOOL_NAME), 180),
                'drafter'=>vb_clean((string)($_POST['drafter'] ?? ''), 180),
                'signer'=>vb_clean((string)($_POST['signer'] ?? ''), 180),
                'status'=>$existingNumber['status'] ?? 'reserved',
                'document_id'=>$existingNumber['document_id'] ?? '',
                'created_by'=>$existingNumber['created_by'] ?? ($user['name'] ?? ''),
                'created_at'=>$existingNumber['created_at'] ?? date('c'),
                'updated_by'=>$user['name'] ?? '', 'updated_at'=>date('c')
            ];
            if ($existingNumber) {
                foreach ($numbers as &$row) if (($row['id'] ?? '') === $editId) { $row=$record; break; }
                unset($row);
            } else $numbers[] = $record;
            if (!vb_save_rows(VANBAN_NUMBERS_FILE, $numbers)) throw new RuntimeException('Không lưu được sổ số văn bản.');
            flash($existingNumber ? 'Đã sửa số văn bản.' : 'Đã lấy số ' . $symbol . '.');
        } elseif ($action === 'upload_number_file') {
            if (!$canNumber) throw new RuntimeException('Bạn không có quyền tải tệp cho số văn bản.');
            $numberId = vb_clean((string)($_POST['number_id'] ?? ''), 80);
            $numbers = vb_rows(VANBAN_NUMBERS_FILE); $numberIndex = null;
            foreach ($numbers as $index=>$row) if (($row['id'] ?? '') === $numberId) { $numberIndex=$index; break; }
            if ($numberIndex === null) throw new RuntimeException('Không tìm thấy số văn bản cần tải tệp.');
            if (($numbers[$numberIndex]['status'] ?? '') === 'cancelled') throw new RuntimeException('Số đã hủy không thể tải văn bản lên.');
            $number = $numbers[$numberIndex];
            $originalName = basename((string)($_FILES['number_file']['name'] ?? ''));
            $path = vb_upload('number_file', 'documents', [
                'title'=>(string)($number['title'] ?? ''), 'document_title'=>(string)($number['title'] ?? ''),
                'document_date'=>(string)($number['issued_date'] ?? ''), 'issuer'=>(string)($number['issuer'] ?? ''),
                'symbol'=>(string)($number['symbol'] ?? ''),
            ]);
            if ($path === '') throw new RuntimeException('Hãy chọn tệp văn bản cần tải lên.');
            $publishDocument = !empty($_POST['publish_document']);
            $documentId = (string)($number['document_id'] ?? '');
            if ($publishDocument && $documentId === '') {
                $documents = vb_rows(VANBAN_DOCUMENTS_FILE);
                $documentId = vb_id('doc');
                $symbolUpper = mb_strtoupper((string)($number['symbol'] ?? ''), 'UTF-8');
                $documentType = ($number['book'] ?? '') === 'decision' ? 'Quyết định' : (str_contains($symbolUpper, '/KH-') ? 'Kế hoạch' : 'Khác');
                $documents[] = [
                    'id'=>$documentId,
                    'type'=>$documentType,
                    'symbol'=>(string)($number['symbol'] ?? ''), 'title'=>(string)($number['title'] ?? ''),
                    'issued_date'=>(string)($number['issued_date'] ?? ''), 'issuer'=>(string)($number['issuer'] ?? ''),
                    'issuer_level'=>'Trường', 'field'=>'Hành chính', 'signer'=>(string)($number['signer'] ?? ''),
                    'notes'=>'', 'file_path'=>$path,
                    'attachments'=>[['path'=>$path,'name'=>$originalName !== '' ? $originalName : basename($path)]],
                    'featured'=>false, 'dashboard_visible'=>false, 'dashboard_from'=>'', 'dashboard_to'=>'',
                    'reserved_id'=>$numberId, 'created_by'=>$user['name'] ?? '', 'created_at'=>date('c'),
                    'updated_by'=>$user['name'] ?? '', 'updated_at'=>date('c'),
                ];
                if (!vb_save_rows(VANBAN_DOCUMENTS_FILE, $documents)) throw new RuntimeException('Tệp đã tải lên nhưng chưa tạo được mục Văn bản cập nhật.');
            }
            $numbers[$numberIndex]['file_path']=$path;
            $numbers[$numberIndex]['file_name']=$originalName;
            $numbers[$numberIndex]['updated_by']=$user['name'] ?? '';
            $numbers[$numberIndex]['updated_at']=date('c');
            if ($publishDocument) {
                $numbers[$numberIndex]['status']='published';
                $numbers[$numberIndex]['document_id']=$documentId;
            }
            if (!vb_save_rows(VANBAN_NUMBERS_FILE, $numbers)) throw new RuntimeException('Tệp đã tải lên nhưng chưa cập nhật được sổ số.');
            flash($publishDocument ? 'Đã tải tệp và đưa văn bản sang Văn bản cập nhật.' : 'Đã tải tệp vào sổ lấy số.');
        } elseif ($action === 'delete_number') {
            if (!$canNumber) throw new RuntimeException('Bạn không có quyền xóa số văn bản.');
            $id = vb_clean((string)($_POST['id'] ?? ''), 80);
            $numbers = vb_rows(VANBAN_NUMBERS_FILE); $found = null; $kept = [];
            foreach ($numbers as $row) { if (($row['id'] ?? '') === $id) $found=$row; else $kept[]=$row; }
            if (!$found) throw new RuntimeException('Không tìm thấy số văn bản cần xóa.');
            if (!empty($found['document_id'])) throw new RuntimeException('Số này đã liên kết với văn bản. Hãy xóa văn bản cập nhật trước.');
            if (!vb_save_rows(VANBAN_NUMBERS_FILE, $kept)) throw new RuntimeException('Không xóa được số văn bản.');
            flash('Đã xóa số văn bản khỏi sổ.', 'warning');
        } elseif ($action === 'cancel_number') {
            if (!$canNumber) throw new RuntimeException('Bạn không có quyền hủy số.');
            $id = vb_clean((string)($_POST['id'] ?? ''), 80);
            $reason = vb_clean((string)($_POST['reason'] ?? ''), 500);
            if ($reason === '') throw new RuntimeException('Hãy nhập lý do hủy số.');
            $numbers = vb_rows(VANBAN_NUMBERS_FILE); $found = false;
            foreach ($numbers as &$row) if (($row['id'] ?? '') === $id && ($row['status'] ?? '') !== 'published') {
                $row['status']='cancelled'; $row['cancel_reason']=$reason; $row['updated_at']=date('c'); $found=true;
            }
            unset($row);
            if (!$found || !vb_save_rows(VANBAN_NUMBERS_FILE, $numbers)) throw new RuntimeException('Không thể hủy số này.');
            flash('Đã đánh dấu số văn bản là đã hủy.', 'warning');
        } elseif ($action === 'create_archive') {
            if (!$canArchive) throw new RuntimeException('Bạn không có quyền cập nhật văn bản mẫu.');
            $title = vb_clean((string)($_POST['title'] ?? ''), 500);
            if ($title === '') throw new RuntimeException('Hãy nhập tên hồ sơ hoặc biểu mẫu.');
            $date = vb_date((string)($_POST['record_date'] ?? '')) ?: date('Y-m-d');
            $path = vb_upload('archive_file', 'documents', ['title'=>$title,'document_title'=>$title,'document_date'=>$date]);
            if ($path === '') throw new RuntimeException('Hãy chọn tệp cần tải lên.');
            $rows = vb_rows(VANBAN_ARCHIVES_FILE);
            $rows[] = [
                'id'=>vb_id('arc'), 'type'=>vb_clean((string)($_POST['type'] ?? 'Khác'), 100), 'title'=>$title,
                'record_date'=>$date, 'department'=>vb_clean((string)($_POST['department'] ?? ''), 180),
                'notes'=>vb_clean((string)($_POST['notes'] ?? ''), 1000), 'file_path'=>$path,
                'created_by'=>$user['name'] ?? '', 'created_at'=>date('c')
            ];
            if (!vb_save_rows(VANBAN_ARCHIVES_FILE, $rows)) throw new RuntimeException('Không lưu được hồ sơ.');
            flash('Đã lưu hồ sơ lên kho lưu trữ.');
        }
    } catch (Throwable $error) {
        flash($error->getMessage(), 'danger');
    }
    $redirect = BASE_URL . 'vanban.php?tab=' . urlencode($returnTab);
    if ($returnTab === 'engagement') {
        $engagementReturn = (string)($_POST['engagement_tab'] ?? 'polls');
        if (in_array($engagementReturn, ['polls','surveys','statistics'], true)) $redirect .= '&engagement_tab=' . urlencode($engagementReturn);
    }
    header('Location: ' . $redirect); exit;
}

$documents = vb_rows(VANBAN_DOCUMENTS_FILE);
$numbers = vb_rows(VANBAN_NUMBERS_FILE);
$archives = vb_rows(VANBAN_ARCHIVES_FILE);
$polls = vb_rows(VANBAN_POLLS_FILE);
$surveys = vb_rows(VANBAN_SURVEYS_FILE);
$tickets = vb_rows(VANBAN_FEEDBACK_FILE);
$userKey = vb_user_key($user);
$audienceUsers=array_values(array_filter(get_users(),fn($row)=>($row['active']??true)&&vb_user_key($row)!==''));
usort($audienceUsers,fn($a,$b)=>strnatcasecmp((string)($a['name']??$a['username']??''),(string)($b['name']??$b['username']??'')));
$audienceGroupLabels=vb_engagement_group_labels();
foreach($audienceUsers as $account)foreach(vb_engagement_user_groups($account) as $group)if(!isset($audienceGroupLabels[$group]))$audienceGroupLabels[$group]=$group;
usort($documents, fn($a,$b)=>strcmp((string)($b['issued_date']??''),(string)($a['issued_date']??'')) ?: strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
usort($numbers, fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
usort($archives, fn($a,$b)=>strcmp((string)($b['record_date']??''),(string)($a['record_date']??'')));
usort($polls, fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
usort($surveys, fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
usort($tickets, fn($a,$b)=>strcmp((string)($b['updated_at']??''),(string)($a['updated_at']??'')));
$filters = ['q'=>(string)($_GET['q']??''),'type'=>(string)($_GET['type']??''),'issuer_level'=>(string)($_GET['issuer_level']??''),'field'=>(string)($_GET['field']??'')];
$filteredDocuments = array_values(array_filter($documents, fn($row)=>vb_matches($row,$filters)));
$qArchive = vb_norm((string)($_GET['archive_q'] ?? ''));
$filteredArchives = array_values(array_filter($archives, fn($row)=>$qArchive==='' || str_contains(vb_norm(implode(' ',[$row['title']??'',$row['type']??'',$row['department']??''])),$qArchive)));
$numberQuery = vb_norm((string)($_GET['number_q'] ?? ''));
$numberBook = (string)($_GET['number_book'] ?? 'all');
if (!in_array($numberBook, ['all','decision','other','stats'], true)) $numberBook = 'all';
$filteredNumbers = array_values(array_filter($numbers, function($row) use ($numberQuery, $numberBook) {
    if ($numberBook !== 'all' && ($row['book'] ?? '') !== $numberBook) return false;
    if ($numberQuery === '') return true;
    return str_contains(vb_norm(implode(' ', [
        $row['symbol'] ?? '', $row['title'] ?? '', $row['issuer'] ?? '',
        $row['drafter'] ?? '', $row['signer'] ?? ''
    ])), $numberQuery);
}));
$numberSort = (string)($_GET['number_sort'] ?? 'issued_date');
$numberDir = strtolower((string)($_GET['number_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
$numberSortFields = ['symbol','title','issued_date','drafter','signer','status'];
if (!in_array($numberSort, $numberSortFields, true)) $numberSort = 'issued_date';
usort($filteredNumbers, function($a, $b) use ($numberSort, $numberDir) {
    $left = (string)($a[$numberSort] ?? '');
    $right = (string)($b[$numberSort] ?? '');
    $compare = $numberSort === 'issued_date' ? strcmp($left, $right) : strnatcasecmp($left, $right);
    if ($compare === 0) $compare = ((int)($a['number'] ?? 0)) <=> ((int)($b['number'] ?? 0));
    if ($compare === 0) $compare = strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? ''));
    return $numberDir === 'asc' ? $compare : -$compare;
});
$numberSortUrl = function($field) use ($numberSort, $numberDir, $numberBook) {
    $nextDir = $numberSort === $field && $numberDir === 'asc' ? 'desc' : 'asc';
    return '?' . http_build_query([
        'tab'=>'numbers', 'number_book'=>$numberBook,
        'number_q'=>(string)($_GET['number_q'] ?? ''),
        'number_sort'=>$field, 'number_dir'=>$nextDir,
    ]);
};
$documentsById=[]; foreach($documents as $document) $documentsById[(string)($document['id']??'')]=$document;
$numberStats=['total'=>count($numbers),'published'=>0,'with_file'=>0,'without_file'=>0,'reserved_without_file'=>0,'cancelled'=>0,'duplicates'=>0,'groups'=>[]];
$numbersWithoutFile=[];
$numberSeen=[];
foreach($numbers as $row){
    $linked=$documentsById[(string)($row['document_id']??'')]??null;
    $hasFile=trim((string)($row['file_path']??''))!==''||($linked&&count(vb_document_attachments($linked))>0);
    if(($row['status']??'')==='published'||!empty($row['document_id']))$numberStats['published']++;
    if(($row['status']??'')==='cancelled')$numberStats['cancelled']++;
    if($hasFile)$numberStats['with_file']++;else{$numberStats['without_file']++;$numbersWithoutFile[]=$row;if(($row['status']??'reserved')==='reserved')$numberStats['reserved_without_file']++;}
    $book=(string)($row['book']??'other');$year=(int)($row['year']??0);$number=(int)($row['number']??0);$key=$book.'|'.$year;
    if(!isset($numberStats['groups'][$key]))$numberStats['groups'][$key]=['book'=>$book,'year'=>$year,'numbers'=>[],'total'=>0,'without_file'=>0,'cancelled'=>0];
    $numberStats['groups'][$key]['total']++;if(!$hasFile)$numberStats['groups'][$key]['without_file']++;if(($row['status']??'')==='cancelled')$numberStats['groups'][$key]['cancelled']++;
    if($number>0)$numberStats['groups'][$key]['numbers'][]=$number;
    $duplicateKey=$key.'|'.$number;if($number>0&&isset($numberSeen[$duplicateKey]))$numberStats['duplicates']++;$numberSeen[$duplicateKey]=true;
}
foreach($numberStats['groups'] as &$group){$unique=array_values(array_unique($group['numbers']));sort($unique);$group['min']=$unique?$unique[0]:0;$group['max']=$unique?$unique[count($unique)-1]:0;$group['missing']=[];$group['missing_count']=0;for($i=1;$i<count($unique);$i++){$gap=max(0,$unique[$i]-$unique[$i-1]-1);$group['missing_count']+=$gap;if(count($group['missing'])<200)for($n=$unique[$i-1]+1;$n<$unique[$i]&&count($group['missing'])<200;$n++)$group['missing'][]=$n;}}unset($group);
uasort($numberStats['groups'],fn($a,$b)=>($b['year']<=>$a['year'])?:strcmp((string)$a['book'],(string)$b['book']));
$pendingNumbers = array_values(array_filter($numbers, fn($row)=>($row['status']??'')==='reserved' && empty($row['document_id'])));
$typeCounts=[]; foreach($documents as $row){$key=(string)($row['type']??'Khác');$typeCounts[$key]=($typeCounts[$key]??0)+1;} arsort($typeCounts);
$nav = [
    'overview'=>['Tổng quan','bi-grid-1x2-fill',true],
    'documents'=>['Văn bản cập nhật','bi-file-earmark-text-fill',true],
    'archives'=>['Văn bản mẫu','bi-archive-fill',true],
    'engagement'=>['Khảo sát · Bình chọn','bi-ui-checks-grid',true],
    'feedback'=>['Góp ý','bi-chat-square-heart-fill',true],
    'numbers'=>['Lấy số văn bản','bi-journal-check',$canNumber],
];
function vb_fmt_date(string $date): string { $time=strtotime($date); return $time?date('d/m/Y',$time):'—'; }
function vb_status(array $row): array { return match($row['status']??'reserved'){'published'=>['Đã phát hành','success'],'cancelled'=>['Đã hủy','danger'],default=>['Đang giữ số','warning']}; }
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Văn thư nội bộ – CDS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--vb:#5b3db2;--vb-dark:#30206f;--vb-soft:#f2effc;--ink:#172033;--muted:#657087;--line:#e5e8f0;--good:#16835d;--warn:#b56c08;--bad:#c83c55}*{box-sizing:border-box}body{margin:0;background:#f5f7fb;color:var(--ink);font-family:Inter,"Segoe UI",system-ui,-apple-system,sans-serif}.shell{min-height:100vh;display:grid;grid-template-columns:260px minmax(0,1fr)}.side{height:100vh;position:sticky;top:0;background:linear-gradient(180deg,var(--vb-dark),#211653);color:#fff;padding:1.15rem;display:flex;flex-direction:column}.brand{display:flex;gap:.75rem;align-items:center;padding:.4rem .35rem 1.5rem}.brand-icon{width:45px;height:45px;border-radius:14px;background:#ffffff1c;display:grid;place-items:center;font-size:1.35rem}.brand strong{font-size:1.12rem}.brand small{display:block;color:#d6cdf9;margin-top:.15rem}.nav-label{font-size:.68rem;letter-spacing:.14em;color:#a99be2;font-weight:800;margin:.2rem .6rem .65rem}.nav a{display:flex;align-items:center;gap:.7rem;color:#eae6ff;text-decoration:none;padding:.78rem .8rem;border-radius:12px;margin:.2rem 0;font-weight:700}.nav a i{font-size:1.05rem}.nav a.active,.nav a:hover{background:#fff;color:var(--vb-dark)}.side-foot{margin-top:auto}.side-foot a{color:#dfd9fa;text-decoration:none;display:block;padding:.65rem}.main{min-width:0;padding:1.25rem 1.4rem 2.5rem}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1.1rem}.top h1{font-size:clamp(1.6rem,3vw,2.25rem);margin:.1rem 0}.top p{color:var(--muted);margin:.2rem 0}.user{background:#fff;border:1px solid var(--line);border-radius:999px;padding:.5rem .75rem;display:flex;gap:.5rem;align-items:center;white-space:nowrap}.card{background:#fff;border:1px solid var(--line);border-radius:17px;box-shadow:0 6px 24px #1b245008}.pad{padding:1rem}.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;margin-bottom:1rem}.kpi{padding:1rem;position:relative;overflow:hidden}.kpi i{position:absolute;right:.85rem;top:.75rem;font-size:1.5rem;color:#a99be2}.kpi strong{display:block;font-size:1.65rem;color:var(--vb-dark)}.kpi span{color:var(--muted);font-size:.82rem}.grid-2{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(280px,.75fr);gap:1rem}.section-head{display:flex;justify-content:space-between;align-items:center;gap:.75rem;margin-bottom:.85rem}.section-head h2{margin:0;font-size:1.05rem}.btn{border:0;border-radius:10px;padding:.68rem .9rem;font-weight:750;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:.4rem;font:inherit}.btn-primary{background:var(--vb);color:#fff}.btn-soft{background:var(--vb-soft);color:var(--vb-dark)}.btn-outline{background:#fff;color:var(--vb-dark);border:1px solid #cfc6f1}.btn-danger{background:#fee8ec;color:var(--bad)}.row-actions{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap}.row-actions form{margin:0}.filters{display:grid;grid-template-columns:2fr repeat(3,1fr) auto;gap:.6rem;padding:1rem;margin-bottom:1rem}.field label{display:block;font-weight:750;font-size:.78rem;margin:0 0 .35rem}.input,select,textarea{width:100%;border:1px solid #d7dce7;border-radius:10px;padding:.7rem .75rem;background:#fff;color:var(--ink);font:inherit;min-height:44px}textarea{min-height:86px;resize:vertical}.input:focus,select:focus,textarea:focus{outline:3px solid #7357c822;border-color:#7357c8}.radio-group{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}.radio-card{position:relative}.radio-card input{position:absolute;opacity:0;pointer-events:none}.radio-card span{display:flex;align-items:center;gap:.5rem;min-height:44px;padding:.65rem .7rem;border:1px solid #d7dce7;border-radius:10px;font-weight:700;cursor:pointer}.radio-card span::before{content:'';width:16px;height:16px;border:2px solid #a3a9b7;border-radius:50%;box-shadow:inset 0 0 0 3px #fff}.radio-card input:checked+span{border-color:var(--vb);background:var(--vb-soft);color:var(--vb-dark)}.radio-card input:checked+span::before{background:var(--vb);border-color:var(--vb)}.check-row{display:flex;align-items:center;gap:.55rem;padding:.75rem;border:1px solid #d7dce7;border-radius:10px;font-weight:750;background:#faf9fe}.check-row input{width:18px;height:18px;accent-color:var(--vb)}.table-wrap{overflow:auto}.table{width:100%;border-collapse:collapse;min-width:780px}.table th{background:#f7f5fd;color:#54457f;text-align:left;font-size:.73rem;text-transform:uppercase;letter-spacing:.04em;padding:.72rem}.table td{border-top:1px solid var(--line);padding:.78rem .72rem;vertical-align:middle}.table .title{font-weight:750}.sub{color:var(--muted);font-size:.78rem;margin-top:.2rem}.pill{display:inline-flex;padding:.28rem .55rem;border-radius:999px;background:var(--vb-soft);color:var(--vb-dark);font-size:.72rem;font-weight:800}.pill.success{background:#e1f5ed;color:var(--good)}.pill.warning{background:#fff1d7;color:var(--warn)}.pill.danger{background:#fee8ec;color:var(--bad)}.type-list{display:grid;gap:.55rem}.type-row{display:grid;grid-template-columns:1fr auto;align-items:center;padding:.7rem .8rem;background:#faf9fe;border-radius:11px}.empty{text-align:center;color:var(--muted);padding:2.4rem 1rem}.empty i{font-size:2rem;display:block;margin-bottom:.5rem;color:#b5a9df}.form-card{padding:1rem;margin-bottom:1rem}.form-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:.75rem}.span-2{grid-column:span 2}.span-3{grid-column:1/-1}.hint{padding:.8rem;background:#fff8e8;border:1px solid #f5dda8;border-radius:11px;color:#805615;margin-bottom:.8rem}.mobile-nav{display:none}.alert{padding:.8rem 1rem;border-radius:11px;margin-bottom:1rem;background:#e1f5ed;color:#126544}.alert-danger{background:#fee8ec;color:#9e2740}.alert-warning{background:#fff1d7;color:#8b5707}.modal{border:0;border-radius:16px;padding:0;max-width:680px;width:calc(100% - 2rem);box-shadow:0 20px 80px #11182755}.modal::backdrop{background:#12142b99}.modal-head{display:flex;justify-content:space-between;align-items:center;padding:1rem;border-bottom:1px solid var(--line)}.modal-body{padding:1rem}.icon-btn{border:0;background:transparent;font-size:1.4rem;cursor:pointer}.quick-actions{display:flex;gap:.55rem;flex-wrap:wrap}.doc-cards{display:none}
/* Danh sách văn bản gọn hơn nhưng vẫn giữ đủ thông tin và thao tác. */
.table{font-size:.84rem;line-height:1.25}.table td{padding:.52rem .62rem}.table .title{line-height:1.22}.table .sub{line-height:1.2;margin-top:.1rem}.table .row-actions{flex-wrap:nowrap;justify-content:flex-end}.table .btn{min-height:34px;padding:.38rem .52rem;font-size:.76rem;white-space:nowrap}.table th:nth-child(1){width:135px}.table th:nth-child(3){width:118px}.table th:nth-child(4),.table th:nth-child(5){width:112px}.table th:nth-child(6){width:92px}.table th:last-child{width:220px}.table td:last-child{white-space:nowrap}
.document-table{width:100%;min-width:760px;table-layout:fixed}.document-table th:nth-child(1){width:19%}.document-table th:nth-child(2){width:38%}.document-table th:nth-child(3){width:12%}.document-table th:nth-child(4){width:14%}.document-table th:nth-child(5){width:17%}.document-table.manage-table th:nth-child(1){width:16%}.document-table.manage-table th:nth-child(2){width:31%}.document-table.manage-table th:nth-child(3){width:10%}.document-table.manage-table th:nth-child(4){width:12%}.document-table.manage-table th:nth-child(5){width:15%}.document-table.manage-table .action-column{width:16%}.document-title-cell{position:relative;min-width:0}.document-title-cell .doc-title-link,.document-title-cell .sub{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.document-title-cell::after{content:attr(data-full-title);position:absolute;z-index:40;left:.55rem;bottom:calc(100% - .25rem);width:max-content;max-width:min(620px,70vw);padding:.58rem .75rem;border:1px solid #cfc6f1;border-radius:9px;background:#fff;color:var(--ink);box-shadow:0 8px 24px #17203329;font-size:.9rem;font-weight:600;line-height:1.35;white-space:normal;opacity:0;visibility:hidden;transform:translateY(3px);transition:opacity .06s ease,transform .06s ease;pointer-events:none}.document-title-cell:hover::after{opacity:1;visibility:visible;transform:translateY(0)}.document-table td:last-child{white-space:normal}
.number-book-tabs{gap:.5rem}.number-book-tab{display:inline-flex;align-items:center;gap:.45rem;padding:.58rem .78rem;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);text-decoration:none;font-size:.82rem;font-weight:800}.number-book-tab b{min-width:22px;padding:.12rem .38rem;border-radius:999px;background:#f0f2f7;text-align:center}.number-book-tab.active{box-shadow:0 0 0 2px currentColor inset}.number-book-tab.decision{color:#8d2638;background:#fff5f6}.number-book-tab.other{color:var(--vb);background:var(--vb-soft)}.number-book-tab.stats{color:#126544;background:#e9f8ef}.number-filters{display:grid;grid-template-columns:minmax(260px,1fr) auto auto;align-items:end;gap:.65rem;padding:.85rem;margin-bottom:.85rem}.number-table .decision-row td{background:#fff1f3}.number-table .other-row td{background:#f0fbf4}.number-table .decision-row .number-title{color:#8d2638}.number-table .other-row .number-title{color:#226b42}.number-table .sort-link{display:inline-flex;align-items:center;gap:.28rem;color:inherit;text-decoration:none}.number-table .sort-link:hover{color:var(--vb-dark)}.number-table .sort-link i{font-size:.72rem}.attachment-mark{display:inline-flex;vertical-align:middle;margin-left:.2rem;color:var(--good);font-size:.86rem;text-decoration:none}.attachment-mark:hover{transform:scale(1.15)}.text-danger{color:var(--bad)}.number-stat-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:.65rem;margin-bottom:.85rem}.number-stat{padding:.9rem}.number-stat strong{display:block;font-size:1.45rem;color:var(--vb-dark)}.number-stat span{font-size:.75rem;color:var(--muted)}.preview-modal{max-width:1100px;width:calc(100% - 2rem)}.preview-frame{display:block;width:100%;height:min(75vh,850px);border:0;background:#eef1f5}.preview-actions{display:flex;gap:.45rem;align-items:center}.upload-number-current{padding:.65rem;border-radius:10px;background:#eef8f2;color:#226b42;margin-bottom:.65rem}@media(max-width:1050px){.number-stat-grid{grid-template-columns:repeat(3,1fr)}}
.upload-tools{display:none;padding:.75rem;border:1px solid var(--line);border-radius:11px;background:#fafbfe}.upload-tools.visible{display:block}.current-file{display:flex;align-items:center;justify-content:space-between;gap:.65rem;margin-bottom:.6rem}.remove-file{display:flex;align-items:center;gap:.4rem;color:var(--bad);font-size:.8rem;font-weight:750}.remove-file input{width:17px;height:17px;accent-color:var(--bad)}.upload-progress{display:none}.upload-progress.visible{display:block}.upload-progress-head{display:flex;justify-content:space-between;gap:.5rem;margin-bottom:.35rem;font-size:.8rem;font-weight:750}.upload-progress-track{height:9px;overflow:hidden;border-radius:999px;background:#e7e9ef}.upload-progress-bar{width:0;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--vb),#8b6ee0);transition:width .12s linear}
@media(max-width:1050px){.kpis{grid-template-columns:1fr 1fr}.filters{grid-template-columns:1fr 1fr}.filters .search{grid-column:1/-1}.grid-2{grid-template-columns:1fr}.form-grid{grid-template-columns:1fr 1fr}.span-3{grid-column:1/-1}}
@media(max-width:760px){.shell{display:block}.side{display:none}.main{padding:.85rem .75rem 5.4rem}.top{align-items:center}.top p,.user span{display:none}.kpis{gap:.55rem}.kpi{padding:.8rem}.kpi strong{font-size:1.35rem}.filters,.form-grid,.number-filters{grid-template-columns:1fr}.filters .search,.span-2,.span-3{grid-column:auto}.number-stat-grid{grid-template-columns:repeat(2,1fr)}.preview-modal{width:calc(100% - .5rem)}.preview-modal .modal-head{align-items:flex-start;gap:.5rem}.preview-actions{flex-wrap:wrap;justify-content:flex-end}.preview-frame{height:72vh}.mobile-nav{position:fixed;z-index:30;left:0;right:0;bottom:0;display:grid;grid-template-columns:repeat(4,1fr);background:#fff;border-top:1px solid var(--line);padding:.38rem max(.25rem,env(safe-area-inset-right)) calc(.38rem + env(safe-area-inset-bottom)) max(.25rem,env(safe-area-inset-left));box-shadow:0 -5px 20px #1b245014}.mobile-nav a{text-align:center;color:#70778a;text-decoration:none;font-size:.62rem;font-weight:750}.mobile-nav i{display:block;font-size:1.2rem;margin-bottom:.18rem}.mobile-nav a.active{color:var(--vb)}.desktop-table{display:none}.doc-cards{display:grid;gap:.65rem}.doc-card{padding:.85rem}.doc-card .meta{display:flex;gap:.35rem;flex-wrap:wrap;margin:.5rem 0}.section-head{align-items:flex-start}.section-head .quick-actions{justify-content:flex-end}.btn{padding:.62rem .72rem}}
</style><style>
.nav a.locked{opacity:.38;cursor:not-allowed;pointer-events:none}.ms-auto{margin-left:auto}.doc-title-link{color:var(--vb-dark);text-decoration:none}.doc-title-link:hover{text-decoration:underline;color:var(--vb)}.engage-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.span-all{grid-column:1/-1}.engage-card{padding:1rem}.engage-card h2{font-size:1.15rem;margin:.75rem 0 .35rem}.engage-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem}.engage-description{color:var(--muted);line-height:1.55;margin:.35rem 0 .75rem}.mb{margin-bottom:.7rem}.choice-list,.survey-form{display:grid;gap:.55rem}.choice{display:flex;align-items:center;gap:.6rem;padding:.7rem;border:1px solid var(--line);border-radius:10px;cursor:pointer}.choice:hover{background:var(--vb-soft)}.choice input{accent-color:var(--vb);width:17px;height:17px}.survey-form fieldset{border:1px solid var(--line);border-radius:12px;padding:.75rem;margin:0}.survey-form legend{font-weight:800;padding:0 .3rem}.survey-result{border-top:1px solid var(--line);padding:.8rem 0}.result-list,.survey-results{display:grid;gap:.65rem}.result-row>div:first-child{display:flex;justify-content:space-between;gap:.5rem;font-size:.82rem}.result-bar{height:8px;background:#eeeaf8;border-radius:999px;overflow:hidden;margin-top:.25rem}.result-bar span{display:block;height:100%;background:linear-gradient(90deg,var(--vb),#9477e8);border-radius:inherit}.joined-note{margin-top:.8rem;color:var(--good);font-weight:750;font-size:.82rem}.ticket-list{display:grid;gap:.75rem}.ticket-card{overflow:hidden}.ticket-card summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem;cursor:pointer;list-style:none}.ticket-card summary::-webkit-details-marker{display:none}.ticket-code{font-size:.7rem;font-weight:800;color:var(--vb);background:var(--vb-soft);padding:.25rem .45rem;border-radius:999px;margin-right:.45rem}.ticket-body{border-top:1px solid var(--line);padding:1rem;background:#fbfbfe}.message{max-width:82%;padding:.75rem;border-radius:12px;margin:.5rem 0;background:#fff;border:1px solid var(--line)}.message.handler{margin-left:auto;background:var(--vb-soft);border-color:#d9cff7}.message p{margin:.3rem 0;line-height:1.5}.message time,.message small{font-size:.68rem;color:var(--muted)}.reply-form{display:grid;grid-template-columns:1fr 160px auto;gap:.55rem;margin-top:1rem}.reply-form textarea{min-height:70px}.engage-card form,.ticket-card form{margin:0}
.engagement-tabs{display:flex;gap:.4rem;padding:.35rem;background:#eae7f7;border-radius:13px;margin-bottom:1rem;width:max-content;max-width:100%;overflow:auto}.engagement-tabs a,.engagement-tabs span{display:flex;align-items:center;gap:.4rem;padding:.65rem .9rem;border-radius:10px;text-decoration:none;color:var(--vb-dark);font-weight:800;white-space:nowrap}.engagement-tabs a.active{background:#fff;box-shadow:0 3px 12px #30206f18}.engagement-tabs .locked-tab{opacity:.45}.stats-list{overflow:hidden}.stats-head{display:flex;justify-content:space-between;padding:1rem;border-bottom:1px solid var(--line)}.stats-round{width:100%;display:grid;grid-template-columns:1fr auto auto;align-items:center;text-align:left;gap:1rem;padding:1rem;border:0;border-bottom:1px solid var(--line);background:#fff;cursor:pointer;color:var(--ink)}.stats-round:hover{background:#faf9fe}.stats-round strong,.stats-round small{display:block}.stats-round small{margin-top:.25rem;color:var(--muted)}.stat-result{margin-bottom:.65rem}.stats-modal{max-width:840px}.chart-heading{display:flex;align-items:center;gap:.55rem;margin-bottom:1rem;padding:.75rem;background:var(--vb-soft);border-radius:11px;color:var(--vb-dark);font-weight:800}.option-stat{padding:.8rem 0;border-bottom:1px solid var(--line)}.option-voters summary{color:var(--vb-dark);font-size:.82rem;font-weight:750;cursor:pointer}.voter-list{display:grid;grid-template-columns:1fr 1fr;gap:.45rem;margin-top:.65rem}.voter-list>div{display:flex;align-items:flex-start;gap:.5rem;padding:.6rem;background:#f7f5fd;border-radius:9px}.voter-list small{display:block;color:var(--muted);margin-top:.15rem}.create-engagement-modal{max-width:820px}.audience-modes{display:grid;grid-template-columns:repeat(3,1fr);gap:.45rem}.audience-modes label{cursor:pointer}.audience-modes input{position:absolute;opacity:0}.audience-modes span{display:block;padding:.7rem;border:1px solid var(--line);border-radius:10px;text-align:center;font-weight:750}.audience-modes input:checked+span{background:var(--vb-soft);border-color:var(--vb);color:var(--vb-dark)}.audience-panel{margin-top:.65rem;padding:.75rem;border:1px solid var(--line);border-radius:11px;background:#fafbfe}.audience-checks{display:grid;grid-template-columns:repeat(2,1fr);gap:.4rem;margin-top:.55rem;max-height:230px;overflow:auto}.audience-checks label{display:flex;align-items:flex-start;gap:.45rem;padding:.55rem;background:#fff;border:1px solid var(--line);border-radius:8px}.audience-checks input{margin-top:.2rem;accent-color:var(--vb)}.audience-checks small{display:block;color:var(--muted);margin-top:.1rem}.audience-search{margin-top:.5rem}
@media(max-width:900px){.engage-grid{grid-template-columns:1fr}.reply-form{grid-template-columns:1fr}.voter-list,.audience-checks{grid-template-columns:1fr}.audience-modes{grid-template-columns:1fr}.mobile-nav{display:flex!important;overflow-x:auto;justify-content:flex-start}.mobile-nav a{flex:0 0 78px}.mobile-nav a.locked{opacity:.38;pointer-events:none}}
</style><style>.side .nav a.locked,.mobile-nav a.locked{display:none!important}</style></head><body>
<style>.kpi{padding:1rem 3.2rem 1rem 1rem}.kpi>i{right:.85rem;top:.85rem;font-size:1.25rem;opacity:.8;pointer-events:none}.kpi span{display:block;line-height:1.35}@media(max-width:760px){.kpi{padding:.8rem 2.7rem .8rem .8rem}.kpi>i{right:.7rem;top:.75rem;font-size:1.1rem}}</style>
<?php require_once __DIR__.'/includes/module_switcher.php'; ?>
<div class="shell">
<aside class="side"><div class="brand"><div class="brand-icon"><i class="bi bi-file-earmark-text-fill"></i></div><div><strong>VĂN THƯ NỘI BỘ</strong><small>Hệ sinh thái CDS</small></div></div><div class="nav-label">DANH MỤC</div><nav class="nav"><?php foreach($nav as $key=>$item):$allowed=$item[2]??true;?><a class="<?=$tab===$key?'active':''?> <?=$allowed?'':'locked'?>" href="<?=$allowed?BASE_URL.'vanban.php?tab='.e($key):'#'?>" <?=$allowed?'':'aria-disabled="true" title="Chưa được phân quyền"'?>><i class="bi <?=e($item[1])?>"></i><?=e($item[0])?><?=$allowed?'':' <i class="bi bi-lock-fill ms-auto"></i>'?></a><?php endforeach;?></nav><div class="side-foot"><a href="<?=BASE_URL?>"><i class="bi bi-grid"></i> Hệ sinh thái CDS</a><a href="<?=BASE_URL?>logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a></div></aside>
<main class="main"><header class="top"><div><h1><?=e($nav[$tab][0])?></h1><p>Tra cứu, phát hành và lưu trữ văn bản tập trung</p></div><div class="user"><i class="bi bi-person-circle"></i><span><?=e($user['name']??'')?></span></div></header><?php show_flash();?>
<?php if($tab==='overview'):?>
<section class="kpis"><article class="card kpi"><i class="bi bi-files"></i><strong><?=count($documents)?></strong><span>Văn bản đã cập nhật</span></article><article class="card kpi"><i class="bi bi-hourglass-split"></i><strong><?=count($pendingNumbers)?></strong><span>Đang chờ bổ sung tệp</span></article><article class="card kpi"><i class="bi bi-calendar3"></i><strong><?=count(array_filter($documents,fn($r)=>str_starts_with((string)($r['issued_date']??''),date('Y'))))?></strong><span>Văn bản năm <?=date('Y')?></span></article><article class="card kpi"><i class="bi bi-archive"></i><strong><?=count($archives)?></strong><span>Văn bản mẫu</span></article></section>
<form class="card filters" method="get"><input type="hidden" name="tab" value="overview"><div class="field search"><label>Tìm nhanh</label><input class="input" name="q" value="<?=e($filters['q'])?>" placeholder="Tên, số ký hiệu, đơn vị, người ký..."></div><div class="field"><label>Loại văn bản</label><select name="type"><option value="">Tất cả</option><?php foreach(vb_document_types() as $v):?><option <?=$filters['type']===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div><div class="field"><label>Cấp ban hành</label><select name="issuer_level"><option value="">Tất cả</option><?php foreach(vb_issuer_levels() as $v):?><option <?=$filters['issuer_level']===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div><div class="field"><label>Lĩnh vực</label><select name="field"><option value="">Tất cả</option><option <?=$filters['field']==='Chuyên môn'?'selected':''?>>Chuyên môn</option><option <?=$filters['field']==='Hành chính'?'selected':''?>>Hành chính</option></select></div><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> Lọc</button></form>
<div class="grid-2"><section class="card"><div class="pad"><div class="section-head"><h2>Văn bản mới nhất</h2><a class="btn btn-soft" href="?tab=documents">Xem tất cả</a></div></div><?php include __DIR__.'/includes/vanban_table.php';?></section><aside class="card pad"><div class="section-head"><h2>Thống kê theo loại</h2></div><div class="type-list"><?php if(!$typeCounts):?><div class="empty"><i class="bi bi-pie-chart"></i>Chưa có số liệu</div><?php else:foreach(array_slice($typeCounts,0,10,true) as $name=>$count):?><div class="type-row"><span><?=e($name)?></span><strong><?=number_format($count)?></strong></div><?php endforeach;endif;?></div></aside></div>
<?php elseif($tab==='documents'):?>
<div class="section-head"><div><?php if($pendingNumbers):?><div class="hint"><strong><i class="bi bi-lightbulb"></i> Có <?=count($pendingNumbers)?> số đã lấy chưa liên kết văn bản.</strong> Hệ thống không tự chuyển; quản trị chọn thủ công khi cần.</div><?php endif;?></div><?php if($canManage):?><div class="row-actions"><?php if($pendingNumbers):?><button class="btn btn-outline" type="button" onclick="document.getElementById('numberImportDialog').showModal()"><i class="bi bi-box-arrow-in-down"></i> Lấy từ sổ số</button><?php endif;?><button class="btn btn-primary" type="button" onclick="openNewDocument()"><i class="bi bi-plus-lg"></i> Thêm văn bản</button></div><?php endif;?></div>
<form class="card filters" method="get"><input type="hidden" name="tab" value="documents"><div class="field search"><label>Tìm văn bản</label><input class="input" name="q" value="<?=e($filters['q'])?>" placeholder="Tên, số ký hiệu, đơn vị..."></div><div class="field"><label>Loại</label><select name="type"><option value="">Tất cả</option><?php foreach(vb_document_types() as $v):?><option <?=$filters['type']===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div><div class="field"><label>Cấp ban hành</label><select name="issuer_level"><option value="">Tất cả</option><?php foreach(vb_issuer_levels() as $v):?><option <?=$filters['issuer_level']===$v?'selected':''?>><?=e($v)?></option><?php endforeach;?></select></div><div class="field"><label>Lĩnh vực</label><select name="field"><option value="">Tất cả</option><option>Chuyên môn</option><option>Hành chính</option></select></div><button class="btn btn-primary"><i class="bi bi-search"></i> Lọc</button></form><section class="card"><?php include __DIR__.'/includes/vanban_table.php';?></section>
<?php elseif($tab==='numbers'):?>
<div class="section-head"><div class="quick-actions number-book-tabs">
  <?php foreach(['all'=>'Tất cả','decision'=>'Quyết định','other'=>'Văn bản khác'] as $bookKey=>$bookLabel):$bookCount=$bookKey==='all'?count($numbers):count(array_filter($numbers,fn($r)=>($r['book']??'')===$bookKey));?>
  <a class="number-book-tab <?=e($bookKey)?> <?=$numberBook===$bookKey?'active':''?>" href="?<?=e(http_build_query(['tab'=>'numbers','number_book'=>$bookKey,'number_q'=>(string)($_GET['number_q']??''),'number_sort'=>$numberSort,'number_dir'=>$numberDir]))?>"><?=e($bookLabel)?> <b><?=$bookCount?></b></a>
  <?php endforeach;?><a class="number-book-tab stats <?=$numberBook==='stats'?'active':''?>" href="?tab=numbers&number_book=stats"><i class="bi bi-bar-chart-line"></i> Thống kê</a>
</div><?php if($canNumber):?><button class="btn btn-primary" onclick="document.getElementById('numberDialog').showModal()"><i class="bi bi-plus-lg"></i> Lấy số mới</button><?php endif;?></div>
<?php if($numberBook==='stats'):?>
<section class="number-stat-grid">
  <?php foreach([['total','Tổng số đã lấy'],['published','Đã đưa ra cập nhật'],['with_file','Đã có tệp'],['without_file','Chưa có tệp'],['reserved_without_file','Đang giữ, chưa tệp'],['cancelled','Số đã hủy']] as [$key,$label]):?><article class="card number-stat"><strong><?=number_format((int)$numberStats[$key])?></strong><span><?=e($label)?></span></article><?php endforeach;?>
</section>
<?php if($numberStats['duplicates']):?><div class="alert alert-danger"><strong>Cảnh báo:</strong> phát hiện <?=number_format((int)$numberStats['duplicates'])?> số trùng trong cùng sổ và năm.</div><?php endif;?>
<section class="card table-wrap"><table class="table"><thead><tr><th>Sổ / năm</th><th>Khoảng số</th><th>Đã lấy</th><th>Chưa có tệp</th><th>Đã hủy</th><th>Số bị bỏ qua</th></tr></thead><tbody><?php foreach($numberStats['groups'] as $group):?><tr><td><strong><?=$group['book']==='decision'?'Quyết định':'Văn bản khác'?></strong><div class="sub"><?=(int)$group['year']?></div></td><td><?=$group['min']&&$group['max']?((int)$group['min'].' – '.(int)$group['max']):'—'?></td><td><?=number_format((int)$group['total'])?></td><td><span class="pill <?=!$group['without_file']?'success':'warning'?>"><?=number_format((int)$group['without_file'])?></span></td><td><?=number_format((int)$group['cancelled'])?></td><td><?php if($group['missing_count']):?><strong class="text-danger"><?=e(implode(', ',$group['missing']))?><?=$group['missing_count']>count($group['missing'])?' …':''?></strong><div class="sub"><?=number_format((int)$group['missing_count'])?> số thiếu trong khoảng</div><?php else:?><span class="pill success">Liên tục</span><?php endif;?></td></tr><?php endforeach;?></tbody></table></section>
<section class="card table-wrap" style="margin-top:.85rem"><div class="pad"><h3 style="margin:0">Danh sách số chưa có tệp (<?=count($numbersWithoutFile)?>)</h3><div class="sub">Dùng danh sách này để kiểm tra và bổ sung đúng văn bản còn thiếu.</div></div><table class="table"><thead><tr><th>Số, ký hiệu</th><th>Tên văn bản</th><th>Ngày</th><th>Người soạn</th><th>Trạng thái</th><th></th></tr></thead><tbody><?php if(!$numbersWithoutFile):?><tr><td colspan="6" class="empty">Tất cả số văn bản đã có tệp.</td></tr><?php else:foreach($numbersWithoutFile as $row):$st=vb_status($row);?><tr><td><strong><?=e($row['symbol']??'')?></strong><div class="sub"><?=($row['book']??'')==='decision'?'Quyết định':'Văn bản khác'?></div></td><td><?=e($row['title']??'')?></td><td><?=vb_fmt_date((string)($row['issued_date']??''))?></td><td><?=e($row['drafter']??'—')?></td><td><span class="pill <?=e($st[1])?>"><?=e($st[0])?></span></td><td><?php if(($row['status']??'')!=='cancelled'&&$canNumber):?><button type="button" class="btn btn-soft upload-number-file" data-record="<?=e(json_encode(['id'=>$row['id']??'','symbol'=>$row['symbol']??'','title'=>$row['title']??'','has_file'=>false],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>"><i class="bi bi-cloud-arrow-up"></i> Tải văn bản lên</button><?php endif;?></td></tr><?php endforeach;endif;?></tbody></table></section>
<div class="hint" style="margin-top:.85rem"><strong>Cách kiểm tra:</strong> “Chưa có tệp” là số đã lấy nhưng chưa gắn file; “Số bị bỏ qua” là số còn thiếu giữa số nhỏ nhất và lớn nhất của từng sổ trong cùng năm. Số đã hủy vẫn được giữ trong sổ để bảo toàn lịch sử.</div>
<?php else:?>
<form class="card number-filters" method="get"><input type="hidden" name="tab" value="numbers"><input type="hidden" name="number_book" value="<?=e($numberBook)?>"><input type="hidden" name="number_sort" value="<?=e($numberSort)?>"><input type="hidden" name="number_dir" value="<?=e($numberDir)?>"><div class="field"><label>Tìm nhanh số văn bản</label><input class="input" name="number_q" value="<?=e((string)($_GET['number_q']??''))?>" placeholder="Số ký hiệu, tên văn bản, người soạn, người ký..."></div><button class="btn btn-primary"><i class="bi bi-search"></i> Tìm kiếm</button><?php if($numberQuery!==''):?><a class="btn btn-outline" href="?tab=numbers&number_book=<?=e($numberBook)?>">Đặt lại</a><?php endif;?></form>
<section class="card table-wrap"><table class="table number-table"><thead><tr><?php foreach(['symbol'=>'Số, ký hiệu','title'=>'Tên văn bản','issued_date'=>'Ngày phát hành','drafter'=>'Người soạn','signer'=>'Người ký','status'=>'Trạng thái'] as $sortField=>$sortLabel):?><th><a class="sort-link" href="<?=e($numberSortUrl($sortField))?>"><?=e($sortLabel)?> <i class="bi <?=e($numberSort===$sortField?($numberDir==='asc'?'bi-sort-up':'bi-sort-down'):'bi-arrow-down-up')?>"></i></a></th><?php endforeach;?><th></th></tr></thead><tbody><?php if(!$filteredNumbers):?><tr><td colspan="7" class="empty">Không tìm thấy số văn bản phù hợp.</td></tr><?php else:foreach($filteredNumbers as $row):$st=vb_status($row);$isDecision=($row['book']??'')==='decision';$linkedDocument=$documentsById[(string)($row['document_id']??'')]??null;$filePath=(string)($linkedDocument['file_path']??($row['file_path']??''));$fileName=(string)($linkedDocument?((vb_document_attachments($linkedDocument)[0]['name']??'')):($row['file_name']??basename($filePath)));$canPreview=(bool)preg_match('/\.(pdf|png|jpe?g)$/i',$fileName);$previewUrl=$linkedDocument?BASE_URL.'vanban_open.php?id='.rawurlencode((string)$linkedDocument['id']):vb_file_url($filePath);$downloadUrl=$linkedDocument?BASE_URL.'vanban_open.php?id='.rawurlencode((string)$linkedDocument['id']).'&download=1':vb_download_url($filePath);?><tr class="number-row <?=$isDecision?'decision-row':'other-row'?>"><td><strong><?=e($row['symbol']??'')?></strong><div class="sub"><?=$isDecision?'Sổ Quyết định':'Sổ văn bản khác'?></div></td><td><div class="title number-title"><?=e($row['title']??'')?><?php if($previewUrl!==''):?> <a class="attachment-mark preview-document" href="<?=e($previewUrl)?>" data-previewable="<?=$canPreview?'1':'0'?>" data-title="<?=e($row['title']??'')?>" data-download="<?=e($downloadUrl)?>" title="Xem văn bản"><i class="bi bi-paperclip"></i></a><?php endif;?></div><div class="sub"><?=e($row['issuer']??'')?></div></td><td><?=vb_fmt_date((string)($row['issued_date']??''))?></td><td><?=e($row['drafter']??'—')?></td><td><?=e($row['signer']??'—')?></td><td><span class="pill <?=e($st[1])?>"><?=e($st[0])?></span></td><td><div class="row-actions"><?php if($previewUrl!==''):?><a class="btn btn-soft preview-document" href="<?=e($previewUrl)?>" data-previewable="<?=$canPreview?'1':'0'?>" data-title="<?=e($row['title']??'')?>" data-download="<?=e($downloadUrl)?>"><i class="bi bi-eye"></i> Xem văn bản</a><?php endif;?><?php if(($row['status']??'')!=='cancelled'&&empty($row['document_id'])&&$canNumber):?><button type="button" class="btn btn-soft upload-number-file" data-record="<?=e(json_encode(['id'=>$row['id']??'','symbol'=>$row['symbol']??'','title'=>$row['title']??'','has_file'=>$filePath!==''],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>"><i class="bi bi-cloud-arrow-up"></i> Tải văn bản lên</button><?php elseif($linkedDocument&&$canManage&&!$previewUrl):?><a class="btn btn-soft" href="?tab=documents&edit=<?=e($linkedDocument['id']??'')?>"><i class="bi bi-cloud-arrow-up"></i> Bổ sung tệp</a><?php endif;?></div></td></tr><?php endforeach;endif;?></tbody></table></section>
<?php endif;?>
<?php elseif($tab==='archives'):?>
<div class="section-head"><form method="get" class="quick-actions"><input type="hidden" name="tab" value="archives"><input class="input" name="archive_q" value="<?=e((string)($_GET['archive_q']??''))?>" placeholder="Tìm văn bản mẫu..."><button class="btn btn-soft"><i class="bi bi-search"></i></button></form><?php if($canArchive):?><button class="btn btn-primary" onclick="document.getElementById('archiveDialog').showModal()"><i class="bi bi-plus-lg"></i> Thêm văn bản mẫu</button><?php endif;?></div><section class="card table-wrap"><table class="table"><thead><tr><th>Loại</th><th>Tên văn bản mẫu</th><th>Ngày</th><th>Đơn vị</th><th>Người cập nhật</th><th></th></tr></thead><tbody><?php if(!$filteredArchives):?><tr><td colspan="6" class="empty">Chưa có văn bản phù hợp.</td></tr><?php else:foreach($filteredArchives as $row):$archivePath=(string)($row['file_path']??'');$archivePreviewable=(bool)preg_match('/\.(pdf|png|jpe?g)$/i',basename($archivePath));?><tr><td><span class="pill"><?=e($row['type']??'')?></span></td><td><a class="title doc-title-link preview-document" href="<?=e(vb_file_url($archivePath))?>" data-previewable="<?=$archivePreviewable?'1':'0'?>" data-title="<?=e($row['title']??'')?>" data-download="<?=e(vb_download_url($archivePath))?>"><?=e($row['title']??'')?></a><div class="sub"><?=e($row['notes']??'')?></div></td><td><?=vb_fmt_date((string)($row['record_date']??''))?></td><td><?=e($row['department']??'—')?></td><td><?=e($row['created_by']??'')?></td><td></td></tr><?php endforeach;endif;?></tbody></table></section>
<?php else:?><?php include __DIR__.'/includes/vanban_engagement_view.php';?>
<?php endif;?></main></div>
<nav class="mobile-nav"><?php foreach($nav as $key=>$item):$allowed=$item[2]??true;?><a class="<?=$tab===$key?'active':''?> <?=$allowed?'':'locked'?>" href="<?=$allowed?'?tab='.e($key):'#'?>"><i class="bi <?=e($item[1])?>"></i><?=e($item[0])?></a><?php endforeach;?></nav>
<?php if($canManage):?><dialog class="modal" id="documentDialog"><div class="modal-head"><strong><i class="bi bi-cloud-arrow-up"></i> Cập nhật văn bản</strong><button class="icon-btn" type="button" onclick="this.closest('dialog').close()">×</button></div><form method="post" enctype="multipart/form-data" class="modal-body" id="documentForm"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="create_document"><input type="hidden" name="return_tab" value="documents"><input type="hidden" name="reserved_id" value=""><div class="form-grid"><div class="field"><label>Loại văn bản *</label><select name="type" required><?php foreach(vb_document_types() as $v):?><option><?=e($v)?></option><?php endforeach;?></select></div><div class="field"><label>Số, ký hiệu *</label><input class="input" name="symbol" required></div><div class="field"><label>Ngày ban hành *</label><input class="input" type="date" name="issued_date" value="<?=date('Y-m-d')?>" required></div><div class="field span-3"><label>Tên/trích yếu văn bản *</label><input class="input" name="title" required></div><div class="field"><label>Đơn vị ban hành *</label><input class="input" name="issuer" required></div><div class="field"><label>Cấp ban hành</label><select name="issuer_level"><?php foreach(vb_issuer_levels() as $v):?><option><?=e($v)?></option><?php endforeach;?></select></div><div class="field"><label>Lĩnh vực</label><select name="field"><option>Chuyên môn</option><option>Hành chính</option></select></div><div class="field"><label>Người ký</label><input class="input" name="signer"></div><div class="field span-3"><label>Các tệp đính kèm (tối đa 25 MB/tệp)</label><div id="documentFiles" class="upload-file-list"></div><button class="btn btn-soft" type="button" onclick="addDocumentFile()"><i class="bi bi-plus-circle"></i> Thêm ô tải tệp</button><small>Mỗi tệp dùng một ô riêng; có thể thêm nhiều tệp trong cùng văn bản.</small></div><div class="field span-3"><label>Ghi chú</label><textarea name="notes"></textarea></div><button class="btn btn-primary span-3"><i class="bi bi-cloud-check"></i> Tải lên và lưu</button></div></form></dialog>
<?php if($pendingNumbers):?><dialog class="modal" id="numberImportDialog"><div class="modal-head"><strong><i class="bi bi-journal-arrow-down"></i> Chọn văn bản từ sổ lấy số</strong><button class="icon-btn" type="button" onclick="this.closest('dialog').close()">×</button></div><div class="modal-body"><p class="sub">Chỉ văn bản được chọn mới chuyển thông tin sang biểu mẫu cập nhật.</p><div class="reserved-pick-list"><?php foreach($pendingNumbers as $row):?><button type="button" class="reserved-pick" data-reserved="<?=e(json_encode(['id'=>$row['id']??'','symbol'=>$row['symbol']??'','title'=>$row['title']??'','date'=>$row['issued_date']??'','issuer'=>$row['issuer']??'','signer'=>$row['signer']??''],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>"><span><strong><?=e($row['symbol']??'')?></strong><small><?=e($row['title']??'')?></small></span><i class="bi bi-chevron-right"></i></button><?php endforeach;?></div></div></dialog><?php endif;endif;?>
<?php if($canNumber):?><dialog class="modal" id="numberDialog"><div class="modal-head"><strong><i class="bi bi-journal-check"></i> Lấy số văn bản</strong><button class="icon-btn" onclick="this.closest('dialog').close()">×</button></div><form method="post" class="modal-body"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="reserve_number"><input type="hidden" name="return_tab" value="numbers"><div class="form-grid"><div class="field span-2"><label>Loại sổ *</label><div class="radio-group"><label class="radio-card"><input type="radio" name="book" value="decision"><span>Quyết định</span></label><label class="radio-card"><input type="radio" name="book" value="other" checked><span>Văn bản khác</span></label></div></div><div class="field"><label>Số, ký hiệu *</label><input class="input" name="symbol" placeholder="Ví dụ: 157/KH-NTXM" required></div><div class="field"><label>Ngày phát hành *</label><input class="input" type="date" name="issued_date" value="<?=date('Y-m-d')?>" required></div><div class="field span-2"><label>Đơn vị phát hành *</label><input class="input" name="issuer" value="<?=e(SCHOOL_NAME)?>" required></div><div class="field span-3"><label>Tên/trích yếu văn bản *</label><input class="input" name="title" required></div><div class="field"><label>Người soạn thảo</label><input class="input" name="drafter" list="drafterSuggestions" placeholder="Nhập hoặc chọn gợi ý"><datalist id="drafterSuggestions"><option value="Hoàng Tú Phượng"></datalist></div><div class="field"><label>Người ký</label><input class="input" name="signer" list="signerSuggestions" placeholder="Nhập hoặc chọn gợi ý"><datalist id="signerSuggestions"><option value="Nguyễn Thị Ngân"><option value="Nguyễn Hồng Dân"><option value="Lục Thị Kim Liên"></datalist></div><button class="btn btn-primary span-3"><i class="bi bi-check2-circle"></i> Xác nhận lấy số</button></div></form></dialog><?php endif;?>
<?php if($canNumber):?><dialog class="modal" id="numberUploadDialog"><div class="modal-head"><div><strong><i class="bi bi-cloud-arrow-up"></i> Tải văn bản lên</strong><div class="sub" data-upload-number-label></div></div><button class="icon-btn" type="button" onclick="this.closest('dialog').close()">×</button></div><form method="post" enctype="multipart/form-data" class="modal-body"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="upload_number_file"><input type="hidden" name="return_tab" value="numbers"><input type="hidden" name="number_id" value=""><div class="upload-number-current" data-upload-number-current hidden>Số này đã có tệp. Tệp mới sẽ thay tệp đang gắn trực tiếp trong sổ số.</div><div class="field"><label>Chọn tệp văn bản *</label><input class="input" type="file" name="number_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" required></div><label class="check-row" style="margin-top:.75rem"><input type="checkbox" name="publish_document" value="1"><span><strong>Đồng thời đưa sang Văn bản cập nhật</strong><br><small>Tạo mục văn bản chính thức và liên kết với số này.</small></span></label><button class="btn btn-primary" style="width:100%;margin-top:.75rem" type="submit"><i class="bi bi-cloud-check"></i> Tải lên và lưu</button></form></dialog><?php endif;?>
<dialog class="modal preview-modal" id="documentPreviewDialog"><div class="modal-head"><strong data-preview-title>Xem văn bản</strong><div class="preview-actions"><a class="btn btn-primary" data-preview-download href="#" download><i class="bi bi-download"></i> Tải về</a><button class="btn btn-outline" type="button" data-preview-close><i class="bi bi-x-lg"></i> Đóng</button></div></div><iframe class="preview-frame" data-preview-frame title="Xem trước văn bản"></iframe></dialog>
<?php if($canArchive):?><dialog class="modal" id="archiveDialog"><div class="modal-head"><strong><i class="bi bi-archive"></i> Thêm văn bản mẫu</strong><button class="icon-btn" onclick="this.closest('dialog').close()">×</button></div><form method="post" enctype="multipart/form-data" class="modal-body"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="create_archive"><input type="hidden" name="return_tab" value="archives"><div class="form-grid"><div class="field"><label>Loại văn bản mẫu *</label><select name="type"><?php foreach(vb_archive_types() as $v):?><option><?=e($v)?></option><?php endforeach;?></select></div><div class="field"><label>Ngày cập nhật</label><input class="input" type="date" name="record_date" value="<?=date('Y-m-d')?>"></div><div class="field"><label>Đơn vị/bộ phận</label><input class="input" name="department"></div><div class="field span-3"><label>Tên văn bản mẫu *</label><input class="input" name="title" required></div><div class="field span-3"><label>Chọn tệp * (tối đa 25 MB)</label><input class="input" type="file" name="archive_file" required></div><div class="field span-3"><label>Mô tả</label><textarea name="notes"></textarea></div><button class="btn btn-primary span-3"><i class="bi bi-cloud-check"></i> Tải lên và lưu</button></div></form></dialog><?php endif;?>
<style>
.upload-file-list,.reserved-pick-list,.form-field-list{display:grid;gap:.55rem;margin:.45rem 0}.upload-file-row{display:grid;grid-template-columns:1fr 42px;gap:.45rem;align-items:center}.reserved-pick{width:100%;display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem;border:1px solid var(--line);border-radius:12px;background:#fff;text-align:left;color:var(--vb-dark);cursor:pointer}.reserved-pick:hover{background:var(--vb-soft);border-color:#b9a6ee}.reserved-pick span,.reserved-pick small{display:block}.form-field-row{display:grid;grid-template-columns:minmax(180px,1.2fr) 150px minmax(180px,1fr) auto 42px;gap:.45rem;align-items:center;padding:.55rem;border:1px solid var(--line);border-radius:12px;background:#fbfaff}.field-required{display:flex;align-items:center;gap:.35rem;white-space:nowrap;font-size:.8rem}.builder-head,.form-stat-actions{display:flex;align-items:center;justify-content:space-between;gap:.65rem}.builder-head small{display:block;color:var(--muted);margin-top:.2rem}.attachment-links{display:flex;flex-wrap:wrap;gap:.35rem;margin-top:.35rem}.attachment-links a{font-size:.72rem;padding:.2rem .45rem;border-radius:999px;background:var(--vb-soft);text-decoration:none}.form-summary-table{margin-top:.8rem}.form-summary-table .total-row{background:var(--vb-soft)}.form-result-note{padding:.8rem;border-radius:10px;background:#e9f8ef;color:#17633a}.flexible-response>.field{margin-bottom:.6rem}
@media(max-width:900px){.form-field-row{grid-template-columns:1fr 1fr}.form-field-row .field-options{grid-column:1/-1}.form-field-row .icon-btn{grid-column:2;justify-self:end}}
@media print{body *{visibility:hidden!important}.print-target,.print-target *{visibility:visible!important}.print-target{position:absolute!important;inset:0!important;width:100%!important;max-width:none!important;height:auto!important;border:0!important}.print-target .no-print,.print-target .modal-head .icon-btn{display:none!important}.print-target::backdrop{display:none!important}}
</style>
<script>
document.querySelectorAll('dialog').forEach(function(d){d.addEventListener('click',function(e){if(e.target===d)d.close()})});
function hiddenInput(form,name,value){let input=form.querySelector('input[type="hidden"][name="'+name+'"]');if(!input){input=document.createElement('input');input.type='hidden';input.name=name;form.appendChild(input)}input.value=value||'';return input}
const previewDialog=document.getElementById('documentPreviewDialog');
if(previewDialog){
  const frame=previewDialog.querySelector('[data-preview-frame]'),title=previewDialog.querySelector('[data-preview-title]'),download=previewDialog.querySelector('[data-preview-download]');
  function closePreview(){frame.src='about:blank';previewDialog.close()}
  previewDialog.querySelector('[data-preview-close]').addEventListener('click',closePreview);
  previewDialog.addEventListener('cancel',event=>{event.preventDefault();closePreview()});
  previewDialog.addEventListener('click',event=>{if(event.target===previewDialog)frame.src='about:blank'});
  document.querySelectorAll('.preview-document').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();title.textContent=link.dataset.title||'Xem văn bản';frame.removeAttribute('srcdoc');if(link.dataset.previewable==='0'&&link.dataset.officeSource){const source=new URL(link.dataset.officeSource,window.location.href).href;frame.src='https://view.officeapps.live.com/op/embed.aspx?src='+encodeURIComponent(source)}else{frame.src=link.href}download.href=link.dataset.download||link.href;previewDialog.showModal()}));
}
const numberUploadDialog=document.getElementById('numberUploadDialog');
if(numberUploadDialog){
  const uploadForm=numberUploadDialog.querySelector('form'),label=numberUploadDialog.querySelector('[data-upload-number-label]'),current=numberUploadDialog.querySelector('[data-upload-number-current]');
  document.querySelectorAll('.upload-number-file').forEach(button=>button.addEventListener('click',()=>{const row=JSON.parse(button.dataset.record||'{}');uploadForm.reset();uploadForm.elements.number_id.value=row.id||'';label.textContent=(row.symbol||'')+' · '+(row.title||'');current.hidden=!row.has_file;numberUploadDialog.showModal()}));
}
function addDocumentFile(){const list=document.getElementById('documentFiles');if(!list)return;const row=document.createElement('div');row.className='upload-file-row';row.innerHTML='<input class="input" type="file" name="document_files[]" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png"><button class="icon-btn" type="button" title="Bỏ ô này">×</button>';row.querySelector('button').onclick=()=>{if(list.children.length>1)row.remove();else row.querySelector('input').value=''};list.appendChild(row)}
function resetDocumentFiles(){const list=document.getElementById('documentFiles');if(!list)return;list.innerHTML='';addDocumentFile()}
function openNewDocument(){const dialog=document.getElementById('documentDialog');if(!dialog)return;const form=dialog.querySelector('form');form.reset();hiddenInput(form,'id','');form.elements.reserved_id.value='';resetDocumentFiles();dialog.showModal()}
const documentDialog=document.getElementById('documentDialog');
if(documentDialog){
  const form=documentDialog.querySelector('form'),submit=form.querySelector('button[type="submit"],button:not([type])');
  const dashboardWrap=document.createElement('div');dashboardWrap.className='span-3';dashboardWrap.innerHTML='<div class="field"><label class="check-row"><input type="checkbox" name="dashboard_visible" value="1"> <span><strong>Hiển thị tại admin.php</strong> – mục Thông báo đang và sắp diễn ra</span></label></div><div class="form-grid" data-dashboard-schedule><div class="field"><label>Bắt đầu hiển thị</label><input class="input" type="datetime-local" name="dashboard_from"></div><div class="field"><label>Hết hiệu lực hiển thị</label><input class="input" type="datetime-local" name="dashboard_to"></div><div class="field"><div class="hint" style="margin:0">Không hiển thị ở trang hệ sinh thái ngoài cùng. Tự đánh dấu <strong>Mới</strong> trong 3 ngày đầu và <strong>Sắp hết hạn</strong> trong 3 ngày cuối.</div></div><div class="field span-3 push-options"><label class="check-row"><input type="checkbox" name="push_notification" value="1"> <span><strong>Gửi chuông thông báo đến điện thoại</strong> (chỉ gửi khi bấm lưu)</span></label><label class="check-row"><input type="checkbox" name="push_urgent" value="1"> <span>Thông báo khẩn – yêu cầu người nhận chú ý</span></label></div></div>';
  submit.parentNode.insertBefore(dashboardWrap,submit);
  const uploadTools=document.createElement('div');uploadTools.className='span-3 upload-tools';uploadTools.innerHTML='<div class="current-file"><a class="btn btn-soft" data-current-file target="_blank" rel="noopener"><i class="bi bi-paperclip"></i> Mở tệp hiện tại</a><label class="remove-file"><input type="checkbox" name="remove_file" value="1"> Xóa tệp hiện tại</label></div><div class="upload-progress" data-upload-progress><div class="upload-progress-head"><span data-upload-status>Đang chuẩn bị...</span><b data-upload-percent>0%</b></div><div class="upload-progress-track"><div class="upload-progress-bar" data-upload-bar></div></div></div>';
  submit.parentNode.insertBefore(uploadTools,dashboardWrap);
  const currentFileLink=uploadTools.querySelector('[data-current-file]'),removeFile=uploadTools.querySelector('input[name="remove_file"]'),progress=uploadTools.querySelector('[data-upload-progress]'),progressStatus=uploadTools.querySelector('[data-upload-status]'),progressPercent=uploadTools.querySelector('[data-upload-percent]'),progressBar=uploadTools.querySelector('[data-upload-bar]');
  function setCurrentFile(row){const path=row&&row.file_path||'';uploadTools.classList.toggle('visible',path!=='');currentFileLink.href=!path?'#':String(path).startsWith('gdrive:')?<?=json_encode(BASE_URL,JSON_UNESCAPED_SLASHES)?>+'admin.php?drive_file='+encodeURIComponent(String(path).slice(7)):<?=json_encode(BASE_URL,JSON_UNESCAPED_SLASHES)?>+String(path).replace(/^\//,'');removeFile.checked=false}
  function setUploadProgress(percent,status){progress.classList.add('visible');progressBar.style.width=percent+'%';progressPercent.textContent=percent+'%';progressStatus.textContent=status}
  const dashboardToggle=form.elements.dashboard_visible,schedule=dashboardWrap.querySelector('[data-dashboard-schedule]');
  function syncDashboardSchedule(){const enabled=dashboardToggle.checked;schedule.hidden=!enabled;form.elements.dashboard_from.required=enabled;form.elements.dashboard_to.required=enabled}
  dashboardToggle.addEventListener('change',syncDashboardSchedule);syncDashboardSchedule();
  document.querySelectorAll('.edit-document').forEach(button=>button.addEventListener('click',()=>{const row=JSON.parse(button.dataset.record||'{}');form.reset();hiddenInput(form,'id',row.id);form.elements.reserved_id.value=row.reserved_id||'';['type','symbol','title','issued_date','issuer','issuer_level','field','signer','notes','dashboard_from','dashboard_to'].forEach(name=>{if(form.elements[name])form.elements[name].value=row[name]||''});form.elements.dashboard_visible.checked=!!row.dashboard_visible;syncDashboardSchedule();setCurrentFile(row);resetDocumentFiles();progress.classList.remove('visible');submit.innerHTML='<i class="bi bi-check2-circle"></i> Lưu thay đổi';documentDialog.showModal()}));
  document.querySelectorAll('.reserved-pick').forEach(button=>button.addEventListener('click',()=>{const row=JSON.parse(button.dataset.reserved||'{}');form.reset();hiddenInput(form,'id','');form.elements.reserved_id.value=row.id||'';form.elements.symbol.value=row.symbol||'';form.elements.title.value=row.title||'';form.elements.issued_date.value=row.date||'';form.elements.issuer.value=row.issuer||'';form.elements.signer.value=row.signer||'';syncDashboardSchedule();setCurrentFile(null);resetDocumentFiles();progress.classList.remove('visible');submit.innerHTML='<i class="bi bi-cloud-check"></i> Tải lên và liên kết';document.getElementById('numberImportDialog')?.close();documentDialog.showModal()}));
  resetDocumentFiles();
  form.addEventListener('submit',event=>{event.preventDefault();if(!form.reportValidity())return;const xhr=new XMLHttpRequest(),data=new FormData(form);submit.disabled=true;setUploadProgress(2,'Đang chuẩn bị tải tệp...');xhr.open('POST',location.href,true);xhr.upload.addEventListener('progress',e=>{if(!e.lengthComputable)return;const percent=Math.min(95,Math.max(2,Math.round(e.loaded/e.total*95)));setUploadProgress(percent,'Đang tải tệp lên máy chủ...')});xhr.upload.addEventListener('load',()=>setUploadProgress(96,'Đang chuyển tệp vào Google Drive...'));xhr.addEventListener('load',()=>{if(xhr.status>=200&&xhr.status<400){setUploadProgress(100,'Đã xử lý xong. Đang cập nhật danh sách...');setTimeout(()=>{location.href=xhr.responseURL||'?tab=documents'},350)}else{setUploadProgress(0,'Tải lên chưa thành công. Vui lòng thử lại.');submit.disabled=false}});xhr.addEventListener('error',()=>{setUploadProgress(0,'Mất kết nối khi tải tệp.');submit.disabled=false});xhr.send(data)});
  const requestedEdit=<?=json_encode((string)($_GET['edit']??''),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;if(requestedEdit){const editButton=Array.from(document.querySelectorAll('.edit-document')).find(button=>{try{return JSON.parse(button.dataset.record||'{}').id===requestedEdit}catch(e){return false}});if(editButton)editButton.click()}
}
const numberDialog=document.getElementById('numberDialog');
if(numberDialog){
  const form=numberDialog.querySelector('form'),submit=form.querySelector('button[type="submit"],button:not([type])'),rows=<?=json_encode($tab==='numbers'?$filteredNumbers:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,allRows=<?=json_encode($tab==='numbers'?$numbers:[],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;
  const symbolInput=form.elements.symbol,dateInput=form.elements.issued_date;
  symbolInput.placeholder='Ví dụ: 25/QĐ-NTXM hoặc 26/KH-NTXM';
  function suggestedSymbol(){
    const book=form.elements.book.value||'decision',year=parseInt((dateInput.value||'<?=date('Y-m-d')?>').slice(0,4),10)||<?=date('Y')?>;
    let max=0;
    allRows.forEach(row=>{if(row.book!==book||parseInt(row.year||0,10)!==year)return;max=Math.max(max,parseInt(row.number||String(row.symbol||'').match(/^\\s*(\\d+)/)?.[1]||0,10))});
    return String(max+1).padStart(2,'0')+(book==='decision'?'/QĐ-NTXM':'/KH-NTXM');
  }
  function syncSuggestedSymbol(){if(!form.elements.id||!form.elements.id.value)symbolInput.value=suggestedSymbol()}
  form.querySelectorAll('input[name="book"]').forEach(input=>input.addEventListener('change',syncSuggestedSymbol));
  dateInput.addEventListener('change',syncSuggestedSymbol);
  const tableRows=document.querySelectorAll('main .number-table tbody tr');
  rows.forEach((row,index)=>{const tr=tableRows[index];if(!tr)return;const cell=tr.lastElementChild;if(!cell)return;const actions=document.createElement('div');actions.className='row-actions';Array.from(cell.children).forEach(child=>actions.appendChild(child));
    const edit=document.createElement('button');edit.type='button';edit.className='btn btn-outline';edit.innerHTML='<i class="bi bi-pencil"></i> Sửa';edit.addEventListener('click',()=>{form.reset();hiddenInput(form,'id',row.id);const radio=form.querySelector('input[name="book"][value="'+row.book+'"]');if(radio)radio.checked=true;['symbol','title','issued_date','issuer','drafter','signer'].forEach(name=>{if(form.elements[name])form.elements[name].value=row[name]||''});submit.innerHTML='<i class="bi bi-check2-circle"></i> Lưu thay đổi';numberDialog.showModal()});actions.appendChild(edit);
    const del=document.createElement('form');del.method='post';del.innerHTML='<input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete_number"><input type="hidden" name="return_tab" value="numbers"><input type="hidden" name="id" value="'+String(row.id).replace(/"/g,'&quot;')+'"><button class="btn btn-danger" type="submit"><i class="bi bi-trash"></i></button>';del.addEventListener('submit',event=>{if(!confirm('Xóa số văn bản '+row.symbol+'?'))event.preventDefault()});actions.appendChild(del);cell.appendChild(actions)
  });
  const addButton=document.querySelector('button[onclick*="numberDialog"]');if(addButton)addButton.addEventListener('click',()=>{form.reset();hiddenInput(form,'id','');syncSuggestedSymbol();submit.innerHTML='<i class="bi bi-check2-circle"></i> Xác nhận lấy số'});
}
const engagementDialog=document.getElementById('engagementDialog');
if(engagementDialog){
  const modeInputs=engagementDialog.querySelectorAll('input[name="audience_mode"]'),panels=engagementDialog.querySelectorAll('[data-audience-panel]');
  function syncAudience(){const mode=engagementDialog.querySelector('input[name="audience_mode"]:checked')?.value||'all';panels.forEach(panel=>{const active=panel.dataset.audiencePanel===mode;panel.hidden=!active;panel.querySelectorAll('input[type="checkbox"]').forEach(input=>input.disabled=!active)})}
  modeInputs.forEach(input=>input.addEventListener('change',syncAudience));syncAudience();
  const search=engagementDialog.querySelector('.audience-search');if(search)search.addEventListener('input',()=>{const query=search.value.trim().toLocaleLowerCase('vi');engagementDialog.querySelectorAll('.audience-users label').forEach(label=>{label.hidden=query!==''&&!String(label.dataset.search||'').includes(query)})});
  const questions=engagementDialog.querySelector('textarea[name="questions"]');
  if(questions){
    const holder=questions.closest('.field'),builder=document.createElement('div');builder.className='field span-3 flexible-builder';builder.hidden=true;builder.innerHTML='<div class="builder-head"><div><strong>Thiết kế biểu mẫu nhập số liệu</strong><small>Có thể kết hợp ô số, chữ, xổ xuống, chọn một hoặc chọn nhiều.</small></div><button class="btn btn-soft" type="button" data-add-field><i class="bi bi-plus-circle"></i> Thêm trường</button></div><div class="form-field-list"></div>';
    const mode=document.createElement('div');mode.className='field span-3';mode.innerHTML='<label>Loại khảo sát</label><div class="audience-modes"><label><input type="radio" name="survey_mode" value="choice" checked><span>Câu hỏi lựa chọn</span></label><label><input type="radio" name="survey_mode" value="form"><span>Biểu mẫu nhập số liệu</span></label></div>';
    holder.parentNode.insertBefore(mode,holder);holder.parentNode.insertBefore(builder,holder.nextSibling);
    const list=builder.querySelector('.form-field-list');let fieldIndex=0;
    function addFormField(label='',type='number',options=''){const index=fieldIndex++,row=document.createElement('div');row.className='form-field-row';row.innerHTML='<input class="input" name="field_label['+index+']" placeholder="Tên trường, ví dụ: Size S"><select name="field_type['+index+']"><option value="number">Nhập số</option><option value="text">Gõ văn bản</option><option value="select">Xổ xuống</option><option value="radio">Chọn một</option><option value="checkbox">Tích nhiều</option></select><input class="input field-options" name="field_options['+index+']" placeholder="Các lựa chọn, cách nhau bằng dấu phẩy"><label class="field-required"><input type="checkbox" name="field_required['+index+']" value="1" checked> Bắt buộc</label><button class="icon-btn" type="button">×</button>';row.querySelector('[name^="field_label"]').value=label;row.querySelector('[name^="field_type"]').value=type;row.querySelector('[name^="field_options"]').value=options;const select=row.querySelector('select');function sync(){row.querySelector('.field-options').hidden=!['select','radio','checkbox'].includes(select.value)}select.onchange=sync;sync();row.querySelector('.icon-btn').onclick=()=>row.remove();list.appendChild(row)}
    builder.querySelector('[data-add-field]').onclick=()=>addFormField();
    mode.querySelectorAll('input[name="survey_mode"]').forEach(input=>input.onchange=()=>{const isForm=input.form.elements.survey_mode.value==='form';builder.hidden=!isForm;holder.hidden=isForm;questions.required=!isForm;list.querySelectorAll('input,select').forEach(el=>el.disabled=!isForm);if(isForm&&!list.children.length){addFormField('Size S','number');addFormField('Size M','number');addFormField('Size L','number')}});
  }
}
function printSurveyResult(id){const dialog=document.getElementById(id);if(!dialog)return;dialog.classList.add('print-target');window.print();setTimeout(()=>dialog.classList.remove('print-target'),300)}
async function shareSurveyResult(title){const data={title:title,text:'Kết quả '+title,url:location.href};try{if(navigator.share)await navigator.share(data);else{await navigator.clipboard.writeText(location.href);alert('Đã sao chép liên kết kết quả.')}}catch(error){if(error.name!=='AbortError')alert('Không thể chia sẻ. Vui lòng sao chép liên kết trên trình duyệt.')}}
</script></body></html>
