<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_login();
require_module('noitru', 'view');
if (!can_perm('nt.lichtruc')) { http_response_code(403); exit('Bạn không có quyền xuất biên bản trực.'); }

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { http_response_code(400); exit('Khoảng ngày không hợp lệ.'); }
$start = new DateTimeImmutable($from);
$end = new DateTimeImmutable($to);
if ($start > $end || $start->diff($end)->days > 366) { http_response_code(400); exit('Khoảng xuất phải từ 1 đến 367 ngày.'); }
if (!class_exists('ZipArchive') || !class_exists('DOMDocument')) { http_response_code(500); exit('Máy chủ cần bật PHP ZipArchive và DOM để xuất tệp Word.'); }

function noitru_duty_word_article(string $day): string {
    $oldGet = $_GET;
    $_GET['date'] = $day;
    $canEditCurrent = false;
    ob_start();
    include __DIR__ . '/includes/noitru_duty_report_view.php';
    $rendered = (string)ob_get_clean();
    $_GET = $oldGet;

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$rendered, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $articles = $xpath->query('//article[contains(concat(" ", normalize-space(@class), " "), " duty-report-paper ")]');
    $article = $articles ? $articles->item(0) : null;
    if (!$article) return '';

    $textareas = $xpath->query('.//textarea', $article);
    for ($i = ($textareas ? $textareas->length : 0) - 1; $i >= 0; $i--) {
        $textarea = $textareas->item($i);
        if (!$textarea || !$textarea->parentNode) continue;
        $preview = $dom->createElement('div');
        $preview->setAttribute('class', 'report-entry-preview');
        $preview->appendChild($dom->createTextNode(trim((string)$textarea->textContent) ?: 'Không có'));
        $textarea->parentNode->replaceChild($preview, $textarea);
    }
    $hints = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " report-entry-hint ")]', $article);
    for ($i = ($hints ? $hints->length : 0) - 1; $i >= 0; $i--) {
        $hint = $hints->item($i);
        if ($hint && $hint->parentNode) $hint->parentNode->removeChild($hint);
    }
    $article->removeAttribute('style');
    return (string)$dom->saveHTML($article);
}

function noitru_duty_word_document(string $article): string {
    $css = <<<'CSS'
@page WordSection1{size:595.3pt 841.9pt;margin:51pt 42.5pt 51pt 56.7pt}
html,body{margin:0;padding:0;background:#fff;color:#000;font-family:"Times New Roman",serif;font-size:13pt;line-height:1.25}.duty-report-paper{page:WordSection1;width:100%;box-sizing:border-box}
.report-national{display:table;width:100%;table-layout:fixed;text-align:center;font-weight:700}.report-national>div{display:table-cell;vertical-align:top}.report-national>div:first-child{width:40%}.report-national>div:last-child{width:60%;padding-left:8mm}
.report-national p{margin:0}.report-national .report-agency{font-weight:400}.underline{display:inline-block;border-bottom:1px solid #000;padding-bottom:2px}.report-place{text-align:right;font-style:italic;margin:7mm 0 4mm}
h1{margin:0;text-align:center;font-size:15pt;font-weight:700}.report-year{text-align:center;font-weight:700;margin:1mm 0 6mm}.report-section{margin:2.5mm 0}.report-section-title,.report-subtitle{font-weight:700}
.report-info-line{margin:1.2mm 0 1.2mm 5mm}.report-info-label{display:inline-block;width:25mm;font-weight:700}.report-text,.report-entry-preview{display:block;white-space:pre-wrap;text-align:justify}
.report-attendance{width:100%;margin:2mm 0;border-collapse:collapse;table-layout:fixed;font-size:11.5pt}.report-attendance th,.report-attendance td{border:1px solid #000;padding:1.6mm;text-align:center;vertical-align:top}
.report-attendance th:nth-child(1){width:22%}.report-attendance th:nth-child(2){width:18%}.report-attendance th:nth-child(3){width:60%}.report-attendance .attendance-absent{text-align:left;font-size:10.5pt;line-height:1.25}
.attendance-type-row{padding:1mm 0;border-bottom:1px dotted #aaa}.attendance-type-label{font-weight:700}.attendance-class{margin-bottom:.7mm}.attendance-name{font-style:italic}
.report-signatures{width:100%;margin-top:7mm;border-collapse:collapse;table-layout:fixed;page-break-inside:avoid}.report-signatures td{height:45mm;border:0;padding:2mm;vertical-align:top;text-align:center}
.report-signatures strong{display:block}.report-sign-name{margin-top:11mm;text-align:center}.report-empty{color:#555;font-style:italic}
CSS;
    return "\xEF\xBB\xBF".'<!doctype html><html><head><meta charset="UTF-8"><meta name="ProgId" content="Word.Document"><style>'.$css.'</style></head><body>'.$article.'</body></html>';
}

$temp = tempnam(sys_get_temp_dir(), 'bbtruc_');
$zip = new ZipArchive();
if ($temp === false || $zip->open($temp, ZipArchive::OVERWRITE) !== true) { http_response_code(500); exit('Không tạo được tệp ZIP.'); }
$count = 0;
for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
    $day = $date->format('Y-m-d');
    $article = noitru_duty_word_article($day);
    if ($article === '') continue;
    $filename = 'Biên bản trực nội trú ngày '.$date->format('d-m-Y').'.doc';
    $zip->addFromString($filename, noitru_duty_word_document($article));
    $count++;
}
$zip->addFromString('THONG-TIN.txt', "Biên bản trực từ $from đến $to\r\nSố tệp Word A4: $count\r\nMỗi ngày là một tệp riêng, bao gồm đầy đủ nội dung và bảng điểm danh.\r\n");
$zip->close();
$filename = 'bien-ban-truc-'.$from.'-den-'.$to.'.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($temp));
readfile($temp);
@unlink($temp);
exit;
