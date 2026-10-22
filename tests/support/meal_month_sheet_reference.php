<?php
// Pre-optimization sheet generators: regression oracle; never load operational data.
function reference_meal_sheet_xml($className, array $students, $month, $type, $schoolYear, $homeroomTeacherName = '') {
    $start = $month . '-01';
    $daysInMonth = (int)date('t', strtotime($start));
    $monthNumber = (int)date('m', strtotime($start));
    $yearNumber = (int)date('Y', strtotime($start));
    $dayData = [];
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%s-%02d', $month, $day);
        $sangActive = (noitru_meal_state($date, 'sang')['status'] ?? 'open') !== 'off';
        $truaActive = (noitru_meal_state($date, 'trua')['status'] ?? 'open') !== 'off';
        $toiActive = (noitru_meal_state($date, 'toi')['status'] ?? 'open') !== 'off';
        $dayData[$day] = [
            'date' => $date,
            'map' => noitru_meals_for_date($date),
            'sang_report' => $sangActive && noitru_meal_report_for($date, $className, 'sang') !== null,
            'trua_report' => $truaActive && noitru_meal_report_for($date, $className, 'trua') !== null,
            'toi_report' => $toiActive && noitru_meal_report_for($date, $className, 'toi') !== null,
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
                $canCount = nt_xlsx_student_can_count_on($student, $info['date']);
                if ($canCount && $type === 'breakfast' && $info['sang_report']) {
                    if (($meal['sang'] ?? '') === 'yes') { $symbol = 'x'; $valueCount = 1; }
                } elseif ($canCount && $type === 'lunch_dinner') {
                    $lunchYes = $info['trua_report'] && (($meal['trua'] ?? '') === 'yes');
                    $dinnerYes = $info['toi_report'] && (($meal['toi'] ?? '') === 'yes');
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

function reference_meal_summary_xml(array $classes, $month, $type, $schoolYear) {
    $start = $month . '-01';
    $daysInMonth = (int)date('t', strtotime($start));
    $monthNumber = (int)date('m', strtotime($start));
    $yearNumber = (int)date('Y', strtotime($start));
    $mealKeys = $type === 'breakfast' ? ['sang'=>'Sáng'] : ['trua'=>'Trưa','toi'=>'Tối'];
    $dayMaps = [];$reportedMap = [];$offMap = [];
    foreach (noitru_meal_report_rows() as $report) {
        $reportDate=(string)($report['date']??'');
        if (substr($reportDate,0,7)!==$month) continue;
        $reportedMap[$reportDate.'|'.(string)($report['class_name']??'').'|'.(string)($report['meal']??'')]=true;
    }
    for($day=1;$day<=$daysInMonth;$day++) {
        $date=sprintf('%s-%02d',$month,$day);$dayMaps[$date]=noitru_meals_for_date($date);
        foreach(array_keys($mealKeys) as $mealKey)$offMap[$date.'|'.$mealKey]=(noitru_meal_state($date,$mealKey)['status']??'open')==='off';
    }
    $lastDayCol = nt_xlsx_col(2 + 31);
    $totalCol = nt_xlsx_col(34);
    $rows = [];
    $rows[] = '<row r="1" ht="22">' . nt_xlsx_text_cell('A1', 'Trường PTDT nội trú THCS&THPT Xín Mần', 1) . nt_xlsx_text_cell('K1', 'CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM', 3) . '</row>';
    $rows[] = '<row r="2" ht="20">' . nt_xlsx_text_cell('K2', 'Độc lập - Tự do - Hạnh phúc', 3) . '</row>';
    $rows[] = '<row r="3" ht="8"></row>';
    $title = $type === 'breakfast' ? 'TỔNG HỢP SUẤT ĂN BỮA SÁNG TOÀN TRƯỜNG' : 'TỔNG HỢP SUẤT ĂN BỮA TRƯA, BỮA TỐI TOÀN TRƯỜNG';
    $rows[] = '<row r="4" ht="26">' . nt_xlsx_text_cell('A4', $title, 2) . '</row>';
    $rows[] = '<row r="5" ht="22">' . nt_xlsx_text_cell('A5', 'Tháng ' . $monthNumber . ' năm ' . $yearNumber . ' · Năm học ' . $schoolYear, 3) . '</row>';
    $rows[] = '<row r="6" ht="36">' . nt_xlsx_text_cell('A6', 'Lớp', 6) . nt_xlsx_text_cell('B6', 'Bữa', 6) . nt_xlsx_text_cell('C6', 'Số suất ăn theo từng ngày trong tháng', 6) . nt_xlsx_text_cell($totalCol . '6', 'Tổng tháng', 6) . '</row>';
    $dayHeader = '<row r="7" ht="22">' . nt_xlsx_text_cell('A7', '', 6) . nt_xlsx_text_cell('B7', '', 6);
    for ($day=1;$day<=31;$day++) $dayHeader .= nt_xlsx_text_cell(nt_xlsx_col(2+$day).'7', $day<=$daysInMonth?sprintf('%02d',$day):'', 6);
    $rows[] = $dayHeader . nt_xlsx_text_cell($totalCol.'7', '', 6) . '</row>';
    $rowNumber = 8;
    $totals = [];
    foreach ($mealKeys as $mealKey=>$unused) $totals[$mealKey] = array_fill(1,31,0);
    $merges = ['A1:J1','K1:AH1','K2:AH2','A4:AH4','A5:AH5','A6:A7','B6:B7','C6:AG6','AH6:AH7'];
    foreach ($classes as $className=>$classStudents) {
        $classStartRow = $rowNumber;
        foreach ($mealKeys as $mealKey=>$mealLabel) {
            $cells = nt_xlsx_text_cell('A'.$rowNumber, $className, 5) . nt_xlsx_text_cell('B'.$rowNumber, $mealLabel, 4);
            $rowTotal = 0;
            for ($day=1;$day<=31;$day++) {
                $ref = nt_xlsx_col(2+$day).$rowNumber;
                if ($day>$daysInMonth) { $cells .= nt_xlsx_text_cell($ref, '', 4); continue; }
                $date = sprintf('%s-%02d',$month,$day);
                $isOff = !empty($offMap[$date.'|'.$mealKey]);
                $reported = $isOff || isset($reportedMap[$date.'|'.$className.'|'.$mealKey]);
                if (!$reported) { $cells .= nt_xlsx_text_cell($ref, '', 4); continue; }
                $mealMap = $dayMaps[$date]??[];
                $count = 0;
                if (!$isOff) foreach ($classStudents as $student) {
                    if (!nt_xlsx_student_can_count_on($student, $date)) continue;
                    if (($mealMap[$student['id']??''][$mealKey]??'')==='yes') $count++;
                }
                $rowTotal += $count;$totals[$mealKey][$day] += $count;
                $cells .= nt_xlsx_number_cell($ref,$count,4);
            }
            $cells .= nt_xlsx_number_cell($totalCol.$rowNumber,$rowTotal,8);
            $rows[] = '<row r="'.$rowNumber.'" ht="20">'.$cells.'</row>';
            $rowNumber++;
        }
        if (count($mealKeys)>1) $merges[] = 'A'.$classStartRow.':A'.($rowNumber-1);
    }
    foreach ($mealKeys as $mealKey=>$mealLabel) {
        $cells = nt_xlsx_text_cell('A'.$rowNumber,'TOÀN TRƯỜNG',8) . nt_xlsx_text_cell('B'.$rowNumber,$mealLabel,8);
        for ($day=1;$day<=31;$day++) $cells .= nt_xlsx_number_cell(nt_xlsx_col(2+$day).$rowNumber,$totals[$mealKey][$day],8);
        $cells .= nt_xlsx_number_cell($totalCol.$rowNumber,array_sum($totals[$mealKey]),8);
        $rows[] = '<row r="'.$rowNumber.'" ht="22">'.$cells.'</row>';$rowNumber++;
    }
    if (count($mealKeys)>1) $merges[] = 'A'.($rowNumber-count($mealKeys)).':A'.($rowNumber-1);
    $noteRow = $rowNumber;
    $rows[] = '<row r="'.$noteRow.'" ht="24">'.nt_xlsx_text_cell('A'.$noteRow,'Ghi chú: Ô trống là lớp chưa gửi báo ăn; số 0 là lớp đã báo nhưng không có suất ăn.',10).'</row>';
    $merges[] = 'A'.$noteRow.':AH'.$noteRow;
    $mergeXml = '<mergeCells count="'.count($merges).'">';foreach($merges as $merge)$mergeXml.='<mergeCell ref="'.$merge.'"/>';$mergeXml.='</mergeCells>';
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="A1:AH'.$noteRow.'"/><sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane xSplit="2" ySplit="7" topLeftCell="C8" activePane="bottomRight" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="20"/><cols><col min="1" max="1" width="12" customWidth="1"/><col min="2" max="2" width="9" customWidth="1"/><col min="3" max="33" width="4.2" customWidth="1"/><col min="34" max="34" width="13" customWidth="1"/></cols>'
        . '<sheetData>'.implode('',$rows).'</sheetData>'.$mergeXml.'<printOptions horizontalCentered="1"/><pageMargins left="0.2" right="0.2" top="0.35" bottom="0.35" header="0.1" footer="0.1"/><pageSetup paperSize="8" orientation="landscape" fitToWidth="1" fitToHeight="0" horizontalDpi="300" verticalDpi="300"/></worksheet>';
}

