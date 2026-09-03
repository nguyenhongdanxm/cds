<?php
/** Xuất báo cáo điểm danh dạng SpreadsheetML, mở trực tiếp bằng Microsoft Excel. */
if (!function_exists('noitru_att_excel')) {
function noitru_att_excel(array $rows, array $students, array $shiftLabels, array $meta) {
    $studentMap = [];
    foreach ($students as $student) $studentMap[$student['id'] ?? ''] = $student;
    $legacyPrefix='[Có phép sau thời gian đăng ký bữa ăn] ';
    $absenceType = function(array $row) use ($legacyPrefix) {
        $reason=trim((string)($row['reason']??''));$ex=trim((string)($row['excuse']??''));
        if($ex==='P_SAU_AN'||str_starts_with($reason,$legacyPrefix))return 'Có phép sau thời gian đăng ký bữa ăn';
        if($ex==='P'||($ex===''&&($row['status']??'')==='excused'))return 'Có phép';
        return 'Không phép';
    };
    usort($rows, function ($a, $b) use ($studentMap, $absenceType) {
        $sa=$studentMap[$a['student_id']??'']??[];$sb=$studentMap[$b['student_id']??'']??[];
        $da=(string)($a['date']??'');$db=(string)($b['date']??'');if($da!==$db)return strcmp($da,$db);
        $sha=(string)($a['shift']??'');$shb=(string)($b['shift']??'');if($sha!==$shb)return strcmp($sha,$shb);
        $order=['Có phép'=>0,'Có phép sau thời gian đăng ký bữa ăn'=>1,'Không phép'=>2];$ta=$order[$absenceType($a)]??9;$tb=$order[$absenceType($b)]??9;if($ta!==$tb)return $ta<=>$tb;
        $ca=(string)($sa['class_name']??'');$cb=(string)($sb['class_name']??'');$cmp=function_exists('csdl_compare_class_names')?csdl_compare_class_names($ca,$cb):strnatcasecmp($ca,$cb);if($cmp!==0)return $cmp;
        return strnatcasecmp((string)($sa['name']??''),(string)($sb['name']??''));
    });
    $xml = function ($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8'); };
    $cell = function ($value, $style = 'Cell', $type = 'String') use ($xml) { return '<Cell ss:StyleID="' . $style . '"><Data ss:Type="' . $type . '">' . $xml($value) . '</Data></Cell>'; };
    $school = $meta['school'] ?? 'TRƯỜNG';$period = $meta['period'] ?? '';$exportedAt = $meta['exported_at'] ?? date('d/m/Y H:i');$exportedBy = $meta['exported_by'] ?? '';$filename = $meta['filename'] ?? 'bao-cao-diem-danh.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($filename));header('Cache-Control: max-age=0');echo '<?xml version="1.0" encoding="UTF-8"?>';echo '<?mso-application progid="Excel.Sheet"?>';
    ?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Styles>
  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/></Style>
  <Style ss:ID="School"><Alignment ss:Horizontal="Center"/><Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/></Style><Style ss:ID="Motto"><Alignment ss:Horizontal="Center"/><Font ss:FontName="Arial" ss:Size="11" ss:Bold="1"/></Style>
  <Style ss:ID="Title"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="16" ss:Bold="1" ss:Color="#9B1C5A"/></Style><Style ss:ID="Subtitle"><Alignment ss:Horizontal="Center"/><Font ss:FontName="Arial" ss:Size="10" ss:Italic="1" ss:Color="#475569"/></Style>
  <Style ss:ID="Header"><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders><Interior ss:Color="#9B1C5A" ss:Pattern="Solid"/><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1" ss:Color="#FFFFFF"/></Style>
  <Style ss:ID="Cell"><Alignment ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>
  <Style ss:ID="Center"><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>
  <Style ss:ID="Absent"><Alignment ss:Horizontal="Center"/><Interior ss:Color="#FEE2E2" ss:Pattern="Solid"/><Font ss:Bold="1" ss:Color="#B91C1C"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/></Borders></Style>
  <Style ss:ID="Summary"><Interior ss:Color="#FCE7F3" ss:Pattern="Solid"/><Font ss:Bold="1" ss:Color="#831843"/></Style><Style ss:ID="Note"><Font ss:Italic="1" ss:Color="#475569"/></Style>
 </Styles>
 <Worksheet ss:Name="Điểm danh"><Table>
   <Column ss:Width="42"/><Column ss:Width="155"/><Column ss:Width="65"/><Column ss:Width="78"/><Column ss:Width="135"/><Column ss:Width="210"/><Column ss:Width="210"/><Column ss:Width="120"/>
   <Row ss:Height="22"><Cell ss:MergeAcross="3" ss:StyleID="School"><Data ss:Type="String"><?= $xml($school) ?></Data></Cell><Cell ss:MergeAcross="3" ss:StyleID="Motto"><Data ss:Type="String">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</Data></Cell></Row>
   <Row><Cell ss:MergeAcross="3" ss:StyleID="School"><Data ss:Type="String">QUẢN LÝ NỘI TRÚ</Data></Cell><Cell ss:MergeAcross="3" ss:StyleID="Motto"><Data ss:Type="String">Độc lập - Tự do - Hạnh phúc</Data></Cell></Row>
   <Row ss:Height="12"><Cell ss:MergeAcross="7"><Data ss:Type="String"></Data></Cell></Row><Row ss:Height="30"><Cell ss:MergeAcross="7" ss:StyleID="Title"><Data ss:Type="String">DANH SÁCH HỌC SINH VẮNG NỘI TRÚ</Data></Cell></Row>
   <Row><Cell ss:MergeAcross="7" ss:StyleID="Subtitle"><Data ss:Type="String"><?= $xml($period) ?></Data></Cell></Row><Row><Cell ss:MergeAcross="7" ss:StyleID="Subtitle"><Data ss:Type="String">Xuất ngày <?= $xml($exportedAt) ?> · Người xuất: <?= $xml($exportedBy) ?></Data></Cell></Row>
   <Row ss:Height="10"><Cell ss:MergeAcross="7"><Data ss:Type="String"></Data></Cell></Row><Row><Cell ss:MergeAcross="7" ss:StyleID="Summary"><Data ss:Type="String">Tổng lượt học sinh vắng: <?= count($rows) ?></Data></Cell></Row>
   <Row ss:Height="28"><?= $cell('STT','Header') ?><?= $cell('Họ và tên','Header') ?><?= $cell('Lớp','Header') ?><?= $cell('Ngày vắng','Header') ?><?= $cell('Thời gian / Buổi vắng','Header') ?><?= $cell('Loại vắng','Header') ?><?= $cell('Lý do / Ghi chú','Header') ?><?= $cell('Người báo cáo','Header') ?></Row>
   <?php foreach ($rows as $index => $row):$student=$studentMap[$row['student_id']??'']??[];$type=$absenceType($row);$reason=trim((string)($row['reason']??''));if(str_starts_with($reason,$legacyPrefix))$reason=trim(substr($reason,strlen($legacyPrefix))); ?>
   <Row><?= $cell($index+1,'Center','Number') ?><?= $cell($student['name']??($row['student_name']??'')) ?><?= $cell($student['class_name']??($row['class_name']??''),'Center') ?><?= $cell(($row['date']??'')!==''?date('d/m/Y',strtotime($row['date'])):'','Center') ?><?= $cell($shiftLabels[$row['shift']??'']??(($row['shift']??'')==='dot_xuat'?'Điểm danh đột xuất':($row['shift']??''))) ?><?= $cell($type,'Absent') ?><?= $cell(trim($reason.(($row['report_note']??'')!==''?' · '.$row['report_note']:''))) ?><?= $cell($row['by']??'') ?></Row>
   <?php endforeach; ?><?php if(!$rows):?><Row><Cell ss:MergeAcross="7" ss:StyleID="Note"><Data ss:Type="String">Không có học sinh vắng trong thời gian đã chọn.</Data></Cell></Row><?php endif; ?>
  </Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>9</SplitHorizontal><TopRowBottomPane>9</TopRowBottomPane><Print><ValidPrinterInfo/><HorizontalResolution>600</HorizontalResolution><VerticalResolution>600</VerticalResolution></Print><Selected/></WorksheetOptions></Worksheet>
</Workbook>
<?php exit;}
}
