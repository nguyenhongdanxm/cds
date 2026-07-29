<?php
/**
 * Xuất báo cáo sử dụng gạo dạng XLSX thật:
 * - Sheet đầu: tổng hợp toàn trường theo lớp.
 * - Các sheet sau: chi tiết từng học sinh của từng lớp.
 */

require_once __DIR__ . '/noitru_meal_month_export.php';

if (!function_exists('nt_rice_styles_xml')) {
function nt_rice_styles_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.000"/></numFmts>'
        . '<fonts count="6">'
        . '<font><sz val="10"/><name val="Times New Roman"/></font>'
        . '<font><b/><sz val="16"/><color rgb="FF0F5E9C"/><name val="Times New Roman"/></font>'
        . '<font><b/><sz val="11"/><name val="Times New Roman"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Times New Roman"/></font>'
        . '<font><i/><sz val="10"/><color rgb="FF475569"/><name val="Times New Roman"/></font>'
        . '<font><b/><sz val="12"/><name val="Times New Roman"/></font>'
        . '</fonts>'
        . '<fills count="6">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F6FAE"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9EAF7"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDCFCE7"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF7D6"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2"><border/><border><left style="thin"><color rgb="FF94A3B8"/></left><right style="thin"><color rgb="FF94A3B8"/></right><top style="thin"><color rgb="FF94A3B8"/></top><bottom style="thin"><color rgb="FF94A3B8"/></bottom></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="13">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="164" fontId="2" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="4" fillId="5" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function nt_rice_build_detail($from, $to, array $riceData) {
    $settings = array_merge(['sang_grams'=>0, 'trua_grams'=>180, 'toi_grams'=>180], $riceData['settings'] ?? []);
    $studentsById = [];
    $classes = [];
    foreach (noitru_boarders_live() as $student) {
        $studentId = trim((string)($student['id'] ?? ''));
        $className = trim((string)($student['class_name'] ?? '')) ?: '(Chưa lớp)';
        if ($studentId === '') continue;
        $studentsById[$studentId] = [
            'id'=>$studentId,
            'name'=>trim((string)($student['name'] ?? '')),
            'class_name'=>$className,
        ];
        $classes[$className][$studentId] = [
            'id'=>$studentId, 'name'=>trim((string)($student['name'] ?? '')),
            'sang'=>0, 'trua'=>0, 'toi'=>0,
            'sang_kg'=>0.0, 'trua_kg'=>0.0, 'toi_kg'=>0.0,
            'total'=>0, 'total_kg'=>0.0,
        ];
    }

    $validReports = [];
    $stateCache = [];
    foreach (noitru_meal_reports_data()['reports'] ?? [] as $report) {
        $date = (string)($report['date'] ?? '');
        $meal = (string)($report['meal'] ?? '');
        $className = trim((string)($report['class_name'] ?? '')) ?: '(Chưa lớp)';
        if ($date < $from || $date > $to || !in_array($meal, ['sang','trua','toi'], true)) continue;
        $stateKey = $date . '|' . $meal;
        if (!isset($stateCache[$stateKey])) $stateCache[$stateKey] = noitru_meal_state($date, $meal)['status'] ?? 'open';
        if ($stateCache[$stateKey] !== 'locked') continue;
        $validReports[$date . '|' . $className . '|' . $meal] = true;
    }

    foreach (noitru_meals_all() as $mealRow) {
        $date = (string)($mealRow['date'] ?? '');
        $studentId = trim((string)($mealRow['student_id'] ?? ''));
        if ($date < $from || $date > $to || !isset($studentsById[$studentId])) continue;
        $student = $studentsById[$studentId];
        $className = $student['class_name'];
        foreach (['sang','trua','toi'] as $meal) {
            if (empty($validReports[$date . '|' . $className . '|' . $meal])) continue;
            if (!in_array($mealRow[$meal] ?? '', ['yes','sick','guest'], true)) continue;
            $kg = (float)($settings[$meal . '_grams'] ?? 0) / 1000;
            $classes[$className][$studentId][$meal]++;
            $classes[$className][$studentId][$meal . '_kg'] = round($classes[$className][$studentId][$meal . '_kg'] + $kg, 3);
            $classes[$className][$studentId]['total']++;
            $classes[$className][$studentId]['total_kg'] = round($classes[$className][$studentId]['total_kg'] + $kg, 3);
        }
    }

    $classTotals = [];
    foreach ($classes as $className => &$classStudents) {
        uasort($classStudents, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        $total = ['sang'=>0,'trua'=>0,'toi'=>0,'sang_kg'=>0.0,'trua_kg'=>0.0,'toi_kg'=>0.0,'total'=>0,'total_kg'=>0.0];
        foreach ($classStudents as $student) {
            foreach (['sang','trua','toi','total'] as $key) $total[$key] += (int)$student[$key];
            foreach (['sang_kg','trua_kg','toi_kg','total_kg'] as $key) $total[$key] = round($total[$key] + (float)$student[$key], 3);
        }
        $classTotals[$className] = $total;
    }
    unset($classStudents);
    ksort($classes, SORT_NATURAL);
    ksort($classTotals, SORT_NATURAL);
    return ['classes'=>$classes, 'class_totals'=>$classTotals, 'settings'=>$settings];
}

function nt_rice_sheet_header(array &$rows, array &$merges, $title, $subtitle, $school, $extra = '') {
    $rows[] = '<row r="1" ht="22">' . nt_xlsx_text_cell('A1', $school, 12) . nt_xlsx_text_cell('F1', 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', 12) . '</row>';
    $rows[] = '<row r="2" ht="20">' . nt_xlsx_text_cell('F2', 'Độc lập - Tự do - Hạnh phúc', 12) . '</row>';
    $rows[] = '<row r="3" ht="8"></row>';
    $rows[] = '<row r="4" ht="28">' . nt_xlsx_text_cell('A4', $title, 1) . '</row>';
    $rows[] = '<row r="5" ht="22">' . nt_xlsx_text_cell('A5', $subtitle, 2) . '</row>';
    if ($extra !== '') $rows[] = '<row r="6" ht="20">' . nt_xlsx_text_cell('A6', $extra, 2) . '</row>';
    else $rows[] = '<row r="6" ht="8"></row>';
    $merges = array_merge($merges, ['A1:E1','F1:J1','F2:J2','A4:J4','A5:J5','A6:J6']);
}

function nt_rice_table_header($row) {
    return '<row r="' . $row . '" ht="36">'
        . nt_xlsx_text_cell('A'.$row, 'STT', 3)
        . nt_xlsx_text_cell('B'.$row, 'Đơn vị / Họ và tên', 3)
        . nt_xlsx_text_cell('C'.$row, 'Bữa sáng' . "\n" . '(lượt)', 3)
        . nt_xlsx_text_cell('D'.$row, 'Gạo sáng' . "\n" . '(kg)', 3)
        . nt_xlsx_text_cell('E'.$row, 'Bữa trưa' . "\n" . '(lượt)', 3)
        . nt_xlsx_text_cell('F'.$row, 'Gạo trưa' . "\n" . '(kg)', 3)
        . nt_xlsx_text_cell('G'.$row, 'Bữa tối' . "\n" . '(lượt)', 3)
        . nt_xlsx_text_cell('H'.$row, 'Gạo tối' . "\n" . '(kg)', 3)
        . nt_xlsx_text_cell('I'.$row, 'Tổng lượt ăn', 3)
        . nt_xlsx_text_cell('J'.$row, 'Tổng gạo' . "\n" . '(kg)', 3)
        . '</row>';
}

function nt_rice_data_row($row, $index, $label, array $data, $total = false) {
    $textStyle = $total ? 8 : 5;
    $intStyle = $total ? 9 : 6;
    $kgStyle = $total ? 10 : 7;
    return '<row r="' . $row . '" ht="21">'
        . ($total ? nt_xlsx_text_cell('A'.$row, '', $textStyle) : nt_xlsx_number_cell('A'.$row, $index, $intStyle))
        . nt_xlsx_text_cell('B'.$row, $label, $textStyle)
        . nt_xlsx_number_cell('C'.$row, $data['sang'] ?? 0, $intStyle)
        . nt_xlsx_number_cell('D'.$row, $data['sang_kg'] ?? 0, $kgStyle)
        . nt_xlsx_number_cell('E'.$row, $data['trua'] ?? 0, $intStyle)
        . nt_xlsx_number_cell('F'.$row, $data['trua_kg'] ?? 0, $kgStyle)
        . nt_xlsx_number_cell('G'.$row, $data['toi'] ?? 0, $intStyle)
        . nt_xlsx_number_cell('H'.$row, $data['toi_kg'] ?? 0, $kgStyle)
        . nt_xlsx_number_cell('I'.$row, $data['total'] ?? 0, $intStyle)
        . nt_xlsx_number_cell('J'.$row, $data['total_kg'] ?? 0, $kgStyle)
        . '</row>';
}

function nt_rice_sheet_xml($title, $subtitle, $school, array $rowsData, $labelKey, array $grandTotal, array $meta, $extra = '', $includeInventory = false) {
    $rows = [];
    $merges = [];
    nt_rice_sheet_header($rows, $merges, $title, $subtitle, $school, $extra);
    $headerRow = 7;
    $rows[] = nt_rice_table_header($headerRow);
    $row = $headerRow + 1;
    $index = 0;
    foreach ($rowsData as $key => $data) {
        $index++;
        $label = $labelKey === 'key' ? $key : ($data[$labelKey] ?? '');
        $rows[] = nt_rice_data_row($row++, $index, $label, $data);
    }
    if (!$index) {
        $rows[] = '<row r="' . $row . '" ht="28">' . nt_xlsx_text_cell('A'.$row, 'Không có dữ liệu bữa ăn đã chốt trong giai đoạn này.', 2) . '</row>';
        $merges[] = 'A'.$row.':J'.$row;
        $row++;
    }
    $rows[] = nt_rice_data_row($row++, 0, 'TỔNG CỘNG', $grandTotal, true);
    $tableEndRow = $row - 1;
    if ($includeInventory) {
        $row++;
        $rows[] = '<row r="' . $row . '" ht="23">' . nt_xlsx_text_cell('A'.$row, 'TỔNG HỢP KHO GẠO', 4) . '</row>';
        $merges[] = 'A'.$row.':J'.$row;
        $row++;
        $inventoryRows = [
            ['Tổng nhập kho trong giai đoạn', (float)($meta['manual_in'] ?? 0)],
            ['Xuất/điều chỉnh thủ công', (float)($meta['manual_out'] ?? 0)],
            ['Tiêu thụ tự động theo suất ăn đã chốt', (float)($grandTotal['total_kg'] ?? 0)],
            ['Tồn kho tại thời điểm xuất báo cáo', (float)($meta['balance'] ?? 0)],
        ];
        foreach ($inventoryRows as $inventoryIndex => $inventoryRow) {
            $style = $inventoryIndex === 3 ? 8 : 5;
            $numberStyle = $inventoryIndex === 3 ? 10 : 7;
            $rows[] = '<row r="' . $row . '" ht="21">'
                . nt_xlsx_text_cell('A'.$row, $inventoryRow[0], $style)
                . nt_xlsx_number_cell('I'.$row, $inventoryRow[1], $numberStyle)
                . nt_xlsx_text_cell('J'.$row, 'kg', $style)
                . '</row>';
            $merges[] = 'A'.$row.':H'.$row;
            $row++;
        }
    }
    $note = 'Định mức: Sáng ' . ($meta['sang_grams'] ?? 0) . ' g/HS; Trưa ' . ($meta['trua_grams'] ?? 0) . ' g/HS; Tối ' . ($meta['toi_grams'] ?? 0) . ' g/HS.';
    $rows[] = '<row r="' . $row . '" ht="25">' . nt_xlsx_text_cell('A'.$row, $note, 11) . '</row>';
    $merges[] = 'A'.$row.':J'.$row;
    $row++;
    $rows[] = '<row r="' . $row . '" ht="20">' . nt_xlsx_text_cell('A'.$row, 'Xuất lúc: ' . ($meta['exported_at'] ?? '') . ' · Người xuất: ' . ($meta['exported_by'] ?? ''), 2) . '</row>';
    $merges[] = 'A'.$row.':J'.$row;

    $mergeXml = '<mergeCells count="' . count($merges) . '">';
    foreach ($merges as $merge) $mergeXml .= '<mergeCell ref="' . $merge . '"/>';
    $mergeXml .= '</mergeCells>';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="A1:J' . $row . '"/>'
        . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="7" topLeftCell="A8" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="20"/>'
        . '<cols><col min="1" max="1" width="6" customWidth="1"/><col min="2" max="2" width="28" customWidth="1"/><col min="3" max="9" width="12" customWidth="1"/><col min="10" max="10" width="15" customWidth="1"/></cols>'
        . '<sheetData>' . implode('', $rows) . '</sheetData>'
        . '<autoFilter ref="A7:J' . max(7, $tableEndRow) . '"/>'
        . $mergeXml
        . '<printOptions horizontalCentered="1"/>'
        . '<pageMargins left="0.25" right="0.25" top="0.35" bottom="0.35" header="0.1" footer="0.1"/>'
        . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0" horizontalDpi="300" verticalDpi="300"/>'
        . '</worksheet>';
}

function noitru_rice_excel(array $usage, array $meta) {
    $from = $meta['from'] ?? date('Y-m-01');
    $to = $meta['to'] ?? date('Y-m-d');
    $riceData = $meta['rice_data'] ?? noitru_rice_data();
    $detail = nt_rice_build_detail($from, $to, $riceData);
    $school = $meta['school'] ?? 'TRƯỜNG PTDTNT THCS&THPT XÍN MẦN';
    $period = $meta['period'] ?? ('Từ ngày ' . date('d/m/Y', strtotime($from)) . ' đến ngày ' . date('d/m/Y', strtotime($to)));

    $schoolTotal = ['sang'=>0,'trua'=>0,'toi'=>0,'sang_kg'=>0.0,'trua_kg'=>0.0,'toi_kg'=>0.0,'total'=>0,'total_kg'=>0.0];
    foreach ($detail['class_totals'] as $classTotal) {
        foreach (['sang','trua','toi','total'] as $key) $schoolTotal[$key] += (int)$classTotal[$key];
        foreach (['sang_kg','trua_kg','toi_kg','total_kg'] as $key) $schoolTotal[$key] = round($schoolTotal[$key] + (float)$classTotal[$key], 3);
    }

    $sheetNames = ['Tổng toàn trường'];
    $usedNames = ['tổng toàn trường'=>true];
    $sheetXml = [
        nt_rice_sheet_xml(
            'BÁO CÁO SỬ DỤNG GẠO TOÀN TRƯỜNG',
            $period,
            $school,
            $detail['class_totals'],
            'key',
            $schoolTotal,
            array_merge($meta, $detail['settings']),
            '',
            true
        )
    ];
    foreach ($detail['classes'] as $className => $students) {
        $sheetNames[] = nt_xlsx_safe_sheet_name($className, $usedNames);
        $sheetXml[] = nt_rice_sheet_xml(
            'BÁO CÁO SỬ DỤNG GẠO LỚP ' . $className,
            $period,
            $school,
            array_values($students),
            'name',
            $detail['class_totals'][$className] ?? [],
            array_merge($meta, $detail['settings']),
            'Lớp: ' . $className
        );
    }

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    foreach ($sheetNames as $i => $unused) $contentTypes .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    $contentTypes .= '</Types>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView activeTab="0"/></bookViews><sheets>';
    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    foreach ($sheetNames as $i => $sheetName) {
        $n = $i + 1;
        $workbook .= '<sheet name="' . nt_xlsx_xml($sheetName) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $workbookRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
    }
    $styleRelId = count($sheetNames) + 1;
    $workbook .= '</sheets><calcPr calcId="191029"/></workbook>';
    $workbookRels .= '<Relationship Id="rId' . $styleRelId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';

    $files = [
        '[Content_Types].xml'=>$contentTypes,
        '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml'=>$workbook,
        'xl/_rels/workbook.xml.rels'=>$workbookRels,
        'xl/styles.xml'=>nt_rice_styles_xml(),
    ];
    foreach ($sheetXml as $i => $xml) $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $xml;

    $tmp = tempnam(sys_get_temp_dir(), 'ntrice_');
    nt_xlsx_write_zip($files, $tmp);
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $meta['filename'] ?? 'bao-cao-gao.xlsx');
    if (substr(strtolower($filename), -5) !== '.xlsx') $filename = preg_replace('/\.xls$/i', '', $filename) . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: max-age=0');
    readfile($tmp);
    @unlink($tmp);
    exit;
}
}
