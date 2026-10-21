<?php
/** Export already-filtered rows, preserving the same order as the register. */
function vb_number_csv_cell($value): string {
    $value=(string)$value;
    // Spreadsheet applications must treat user content as text, never formulas.
    if(preg_match('/^[\x00-\x20]*[=+@-]/',$value)||preg_match('/^[\t\r\n]/',$value))return "'".$value;
    return $value;
}
function vb_number_csv_write($stream,array $rows): void {
    fwrite($stream,"\xEF\xBB\xBF");
    fputcsv($stream,['STT','Sổ văn bản','Năm','Số','Số, ký hiệu','Ngày phát hành','Tên / trích yếu văn bản','Đơn vị phát hành','Người soạn','Người ký','Trạng thái'],',','"','');
    foreach($rows as$i=>$r){
        $date=trim((string)($r['issued_date']??''));$parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        $date=$parsed&&$parsed->format('Y-m-d')===$date?$parsed->format('d/m/Y'):$date;
        $status=match($r['status']??'reserved'){'published'=>'Đã phát hành','cancelled'=>'Đã hủy',default=>'Đang giữ số'};
        $values=[$i+1,($r['book']??'')==='decision'?'Quyết định':'Văn bản khác',$r['year']??'',$r['number']??'',$r['symbol']??'',$date,$r['title']??'',$r['issuer']??'',$r['drafter']??'',$r['signer']??'',$status];
        fputcsv($stream,array_map('vb_number_csv_cell',$values),',','"','');
    }
}
