<?php
/**
 * Xuất sổ theo dõi bữa ăn theo tháng dạng XLSX thật, mỗi lớp một sheet.
 * Dữ liệu đầu vào: $exportStudents, $exportMonth, $exportType, $exportUser.
 */

function nt_xlsx_xml($value) {
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function nt_xlsx_col($number) {
    $name = '';
    while ($number > 0) {
        $number--;
        $name = chr(65 + ($number % 26)) . $name;
        $number = intdiv($number, 26);
    }
    return $name;
}

function nt_xlsx_text_cell($ref, $value, $style = 0) {
    return '<c r="' . $ref . '" t="inlineStr" s="' . $style . '"><is><t xml:space="preserve">' . nt_xlsx_xml($value) . '</t></is></c>';
}

function nt_xlsx_number_cell($ref, $value, $style = 0) {
    return '<c r="' . $ref . '" s="' . $style . '"><v>' . (float)$value . '</v></c>';
}

function nt_xlsx_formula_cell($ref, $formula, $cached, $style = 0) {
    return '<c r="' . $ref . '" s="' . $style . '"><f>' . nt_xlsx_xml($formula) . '</f><v>' . (float)$cached . '</v></c>';
}

function nt_xlsx_safe_sheet_name($name, array &$used) {
    $name = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/u', '-', trim((string)$name)) ?: 'Lớp';
    $name = mb_substr($name, 0, 31);
    $base = $name;
    $i = 2;
    while (isset($used[mb_strtolower($name, 'UTF-8')])) {
        $suffix = '-' . $i++;
        $name = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
    }
    $used[mb_strtolower($name, 'UTF-8')] = true;
    return $name;
}

function nt_xlsx_styles_xml() {
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="5">'
        . '<font><sz val="10"/><name val="Times New Roman"/></font>'
        . '<font><b/><sz val="10"/><name val="Times New Roman"/></font>'
        . '<font><b/><sz val="14"/><name val="Times New Roman"/></font>'
        . '<font><b/><sz val="11"/><name val="Times New Roman"/></font>'
        . '<font><i/><sz val="10"/><name val="Times New Roman"/></font>'
        . '</fonts>'
        . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9EAF7"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="3"><border/><border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/></border>'
        . '<border><left style="medium"/><right style="medium"/><top style="medium"/><bottom style="medium"/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="11">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="2" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function nt_xlsx_write_zip(array $files, $target) {
    $body = '';
    $central = '';
    $offset = 0;
    $timestamp = getdate();
    $dosTime = (($timestamp['hours'] & 0x1f) << 11) | (($timestamp['minutes'] & 0x3f) << 5) | (($timestamp['seconds'] >> 1) & 0x1f);
    $dosDate = ((($timestamp['year'] - 1980) & 0x7f) << 9) | (($timestamp['mon'] & 0x0f) << 5) | ($timestamp['mday'] & 0x1f);
    foreach ($files as $name => $content) {
        $name = str_replace('\\', '/', $name);
        $size = strlen($content);
        $crc = crc32($content);
        $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, strlen($name), 0) . $name . $content;
        $body .= $local;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        $offset += strlen($local);
    }
    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($body), 0);
    if (file_put_contents($target, $body . $central . $end) === false) throw new RuntimeException('Không thể ghi tệp Excel tạm.');
}

function nt_xlsx_sheet_xml($className, array $students, $month, $type, $schoolYear, $homeroomTeacherName = '') {
    $start = $month . '-01';
    $daysInMonth = (int)date('t', strtotime($start));
    $monthNumber = (int)date('m', strtotime($start));
    $yearNumber = (int)date('Y', strtotime($start));
    $dayData = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%s-%02d', $month, $day);
        $dayData[$day] = [
            'date' => $date,
            'map' => noitru_meals_for_date($date),
            'sang_report' => noitru_meal_report_for($date, $className, 'sang') !== null,
            'trua_report' => noitru_meal_report_for($date, $className, 'trua') !== null,
            'toi_report' => noitru_meal_report_for($date, $className, 'toi') !== null,
        ];
    }
    $lastDayCol = nt_xlsx_col(2 + 31);
    $totalCol = nt_xlsx_col(34);
    $signCol = nt_xlsx_col(35);
    $title = $type === 'breakfast'
        ? 'BẢNG THEO DÕI HỌC SINH NỘI TRÚ ĂN TẬP TRUNG (BỮA SÁNG) TẠI TRƯỜNG'
        : 'BẢNG THEO DÕI HỌC SINH NỘI TRÚ ĂN TẬP TRUNG (BỮA TRƯA, BỮA TỐI) TẠI TRƯỜNG';
    $totalLabel = $type === 'breakfast' ? 'Tổng số bữa ăn sáng trong tháng' : 'Tổng số bữa ăn trưa, tối trong tháng';
    $rows = [];
    $rows[] = '<row r="1" ht="22">' . nt_xlsx_text_cell('A1', 'Trường PTDT nội trú THCS&THPT Xín Mần', 1) . nt_xlsx_text_cell('K1', 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', 3) . nt_xlsx_text_cell('AH1', 'Mẫu số: 04', 3) . '</row>';
    $rows[] = '<row r="2" ht="20">' . nt_xlsx_text_cell('A2', 'Lớp: ' . $className, 1) . nt_xlsx_text_cell('K2', 'Độc lập - Tự do - Hạnh phúc', 3) . '</row>';
    $rows[] = '<row r="3" ht="8"></row>';
    $rows[] = '<row r="4" ht="26">' . nt_xlsx_text_cell('A4', $title, 2) . '</row>';
    $rows[] = '<row r="5" ht="22">' . nt_xlsx_text_cell('A5', 'THEO TTLT 109/2009/TTLT/BTC-BGDĐT  NĂM HỌC ' . $schoolYear, 3) . '</row>';
    $rows[] = '<row r="6" ht="22">' . nt_xlsx_text_cell('A6', 'Tháng ' . $monthNumber . '   Năm ' . $yearNumber, 3) . '</row>';
    $rows[] = '<row r="7" ht="42">' . nt_xlsx_text_cell('A7', 'TT', 6) . nt_xlsx_text_cell('B7', 'Họ và tên', 6) . nt_xlsx_text_cell('C7', 'Số ngày ăn tại trường trong tháng (Ngày trên, thứ dưới)', 6) . nt_xlsx_text_cell($totalCol . '7', $totalLabel, 6) . nt_xlsx_text_cell($signCol . '7', 'Ký nhận của học sinh', 6) . '</row>';
    $dayHeader = '<row r="8" ht="20">';
    $weekdayHeader = '<row r="9" ht="20">';
    for ($day = 1; $day <= 31; $day++) {
        $col = nt_xlsx_col(2 + $day);
        $dayHeader .= nt_xlsx_text_cell($col . '8', $day <= $daysInMonth ? sprintf('%02d', $day) : '', 6);
        $weekday = '';
        if ($day <= $daysInMonth) {
            $n = (int)date('N', strtotime($dayData[$day]['date']));
            $weekday = $n === 7 ? 'CN' : (string)($n + 1);
        }
        $weekdayHeader .= nt_xlsx_text_cell($col . '9', $weekday, 6);
    }
    $rows[] = $dayHeader . '</row>';
    $rows[] = $weekdayHeader . '</row>';

    $firstStudentRow = 10;
    $dayTotals = array_fill(1, 31, 0);
    foreach ($students as $index => $student) {
        $rowNumber = $firstStudentRow + $index;
        $mealCount = 0;
        $cells = nt_xlsx_number_cell('A' . $rowNumber, $index + 1, 4) . nt_xlsx_text_cell('B' . $rowNumber, $student['name'] ?? '', 5);
        for ($day = 1; $day <= 31; $day++) {
            $symbol = '';
            $valueCount = 0;
            if ($day <= $daysInMonth) {
                $info = $dayData[$day];
                $meal = $info['map'][$student['id'] ?? ''] ?? [];
                if ($type === 'breakfast' && $info['sang_report']) {
                    if (($meal['sang'] ?? 'yes') === 'yes') { $symbol = 'x'; $valueCount = 1; }
                } elseif ($type === 'lunch_dinner') {
                    $lunchYes = $info['trua_report'] && (($meal['trua'] ?? 'yes') === 'yes');
                    $dinnerYes = $info['toi_report'] && (($meal['toi'] ?? 'yes') === 'yes');
                    if ($lunchYes && $dinnerYes) { $symbol = 'x'; $valueCount = 2; }
                    elseif ($lunchYes) { $symbol = '\\'; $valueCount = 1; }
                    elseif ($dinnerYes) { $symbol = '/'; $valueCount = 1; }
                }
            }
            $mealCount += $valueCount;
            $dayTotals[$day] += $valueCount;
            $cells .= nt_xlsx_text_cell(nt_xlsx_col(2 + $day) . $rowNumber, $symbol, 4);
        }
        $cells .= nt_xlsx_number_cell($totalCol . $rowNumber, $mealCount, 4) . nt_xlsx_text_cell($signCol . $rowNumber, '', 4);
        $rows[] = '<row r="' . $rowNumber . '" ht="20">' . $cells . '</row>';
    }
    $lastStudentRow = max($firstStudentRow, $firstStudentRow + count($students) - 1);
    $totalRow = $firstStudentRow + count($students);
    $totalCells = nt_xlsx_text_cell('A' . $totalRow, '', 8) . nt_xlsx_text_cell('B' . $totalRow, 'Cộng', 8);
    for ($day = 1; $day <= 31; $day++) $totalCells .= nt_xlsx_number_cell(nt_xlsx_col(2 + $day) . $totalRow, $dayTotals[$day], 8);
    $grandTotal = array_sum($dayTotals);
    $totalCells .= nt_xlsx_number_cell($totalCol . $totalRow, $grandTotal, 8) . nt_xlsx_text_cell($signCol . $totalRow, '', 8);
    $rows[] = '<row r="' . $totalRow . '" ht="22">' . $totalCells . '</row>';
    $noteRow = $totalRow + 1;
    $signRow = $totalRow + 2;
    $teacherRow = $totalRow + 3;
    $note = $type === 'breakfast'
        ? 'Ghi chú: Học sinh ăn sáng đánh dấu x; học sinh không ăn để trống.'
        : 'Ký hiệu: x - Ăn cả bữa trưa và tối; \\ - Chỉ ăn bữa trưa; / - Chỉ ăn bữa tối; để trống - Không ăn.';
    $rows[] = '<row r="' . $noteRow . '" ht="22">' . nt_xlsx_text_cell('A' . $noteRow, $note, 10) . nt_xlsx_text_cell('S' . $noteRow, 'Pà Vầy Sủ, ngày ..... tháng ..... năm ' . $yearNumber, 9) . '</row>';
    $rows[] = '<row r="' . $signRow . '" ht="24">' . nt_xlsx_text_cell('A' . $signRow, 'Giáo viên chủ nhiệm', 3) . nt_xlsx_text_cell('S' . $signRow, 'Thủ trưởng đơn vị', 3) . '</row>';
    $rows[] = '<row r="' . $teacherRow . '" ht="22">' . nt_xlsx_text_cell('A' . $teacherRow, trim((string)$homeroomTeacherName), 3) . '</row>';

    $merges = [
        'A1:J1','K1:AG1','AH1:AI1','A2:J2','K2:AG2','A4:AI4','A5:AI5','A6:AI6',
        'A7:A9','B7:B9','C7:AG7','AH7:AH9','AI7:AI9',
        'A'.$noteRow.':Q'.$noteRow,'S'.$noteRow.':AI'.$noteRow,
        'A'.$signRow.':Q'.$signRow,'S'.$signRow.':AI'.$signRow,
        'A'.$teacherRow.':Q'.$teacherRow,
    ];
    $mergeXml = '<mergeCells count="' . count($merges) . '">';
    foreach ($merges as $merge) $mergeXml .= '<mergeCell ref="' . $merge . '"/>';
    $mergeXml .= '</mergeCells>';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="A1:AI' . $teacherRow . '"/>'
        . '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane xSplit="2" ySplit="9" topLeftCell="C10" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="20"/>'
        . '<cols><col min="1" max="1" width="4" customWidth="1"/><col min="2" max="2" width="24" customWidth="1"/><col min="3" max="33" width="4.2" customWidth="1"/><col min="34" max="34" width="13" customWidth="1"/><col min="35" max="35" width="16" customWidth="1"/></cols>'
        . '<sheetData>' . implode('', $rows) . '</sheetData>' . $mergeXml
        . '<printOptions horizontalCentered="1" verticalCentered="0"/>'
        . '<pageMargins left="0.2" right="0.2" top="0.35" bottom="0.35" header="0.1" footer="0.1"/>'
        . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0" horizontalDpi="300" verticalDpi="300"/>'
        . '</worksheet>';
}

function nt_export_meal_month_xlsx(array $students, $month, $type, $exportedBy = '') {
    if (!preg_match('/^\\d{4}-\\d{2}$/', $month)) $month = date('Y-m');
    if (!in_array($type, ['breakfast','lunch_dinner'], true)) $type = 'breakfast';
    $classes = [];
    foreach ($students as $student) {
        $class = trim($student['class_name'] ?? '');
        if ($class !== '') $classes[$class][] = $student;
    }
    ksort($classes, SORT_NATURAL);
    if (!$classes) throw new RuntimeException('Không có lớp học sinh phù hợp để xuất báo cáo.');
    $teachersById = [];
    foreach (csdl_teachers_all() as $teacher) {
        $teacherId = trim((string)($teacher['id'] ?? ''));
        if ($teacherId !== '') $teachersById[$teacherId] = trim((string)($teacher['name'] ?? ''));
    }
    $homeroomTeachers = [];
    foreach (csdl_classes_all() as $classRow) {
        $className = trim((string)($classRow['name'] ?? ''));
        if ($className === '') continue;
        $teacherName = trim((string)($classRow['homeroom_teacher_name'] ?? ''));
        $teacherId = trim((string)($classRow['homeroom_teacher_id'] ?? ''));
        if ($teacherName === '' && $teacherId !== '') $teacherName = $teachersById[$teacherId] ?? '';
        $homeroomTeachers[$className] = $teacherName;
    }
    $schoolYear = defined('SCHOOL_YEAR') ? str_replace('–', '-', SCHOOL_YEAR) : (date('Y') . '-' . (date('Y') + 1));
    $tmp = tempnam(sys_get_temp_dir(), 'ntmeal_');
    $sheetNames = [];
    $usedNames = [];
    $sheetXml = [];
    foreach ($classes as $className => $classStudents) {
        $sheetNames[] = nt_xlsx_safe_sheet_name($className, $usedNames);
        $sheetXml[] = nt_xlsx_sheet_xml($className, $classStudents, $month, $type, $schoolYear, $homeroomTeachers[$className] ?? '');
    }
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
        . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
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
    $workbook .= '</sheets><calcPr calcId="191029" fullCalcOnLoad="1" forceFullCalc="1"/></workbook>';
    $workbookRels .= '<Relationship Id="rId' . $styleRelId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $files = [
        '[Content_Types].xml' => $contentTypes,
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
        'xl/workbook.xml' => $workbook,
        'xl/_rels/workbook.xml.rels' => $workbookRels,
        'xl/styles.xml' => nt_xlsx_styles_xml(),
        'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>Báo cáo bữa ăn tháng ' . nt_xlsx_xml($month) . '</dc:title><dc:creator>' . nt_xlsx_xml($exportedBy) . '</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\\TH:i:s\\Z') . '</dcterms:created></cp:coreProperties>',
        'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>CDS Nội trú</Application><TitlesOfParts><vt:vector size="' . count($sheetNames) . '" baseType="lpstr">' . implode('', array_map(fn($name) => '<vt:lpstr>' . nt_xlsx_xml($name) . '</vt:lpstr>', $sheetNames)) . '</vt:vector></TitlesOfParts></Properties>',
    ];
    foreach ($sheetXml as $i => $xml) $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $xml;
    nt_xlsx_write_zip($files, $tmp);
    $typeName = $type === 'breakfast' ? 'bua-sang' : 'bua-trua-toi';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="bao-cao-' . $typeName . '-thang-' . $month . '.xlsx"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: max-age=0');
    readfile($tmp);
    @unlink($tmp);
    exit;
}
