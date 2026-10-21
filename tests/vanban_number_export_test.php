<?php
require __DIR__.'/../includes/vanban_number_export.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$rows=[['book'=>'decision','year'=>2026,'number'=>12,'symbol'=>'12/QĐ','issued_date'=>'2026-10-21','title'=>"Trích yếu có dấu, dấu phẩy và \"ngoặc\"\nxuống dòng",'drafter'=>'Nguyễn A','status'=>'published'],['book'=>'other','symbol'=>'=1+1','title'=>" \t@SUM(1)",'issued_date'=>'','status'=>'cancelled']];
$before=serialize($rows);$stream=fopen('php://temp','w+b');vb_number_csv_write($stream,$rows);rewind($stream);check(fread($stream,3)==="\xEF\xBB\xBF",'UTF8 BOM for Excel');$parsed=[];while(($r=fgetcsv($stream,0,',','"',''))!==false)$parsed[]=$r;fclose($stream);
check(count($parsed)===3&&count($parsed[0])===11,'Headers and all rows');
check($parsed[1][4]==='12/QĐ'&&$parsed[1][5]==='21/10/2026'&&$parsed[1][6]===$rows[0]['title'],'Vietnamese, dates, multiline and quotes round-trip');
check($parsed[2][4]==="'=1+1"&&str_starts_with($parsed[2][6],"'"),'Spreadsheet formulas neutralized');
check($parsed[1][10]==='Đã phát hành'&&$parsed[2][10]==='Đã hủy','Statuses retained including cancelled records');
check(serialize($rows)===$before,'Does not change source records');
$empty=fopen('php://temp','w+b');vb_number_csv_write($empty,[]);rewind($empty);fread($empty,3);check(count(fgetcsv($empty,0,',','"',''))===11&&fgetcsv($empty)===false,'Empty result still downloads headers');fclose($empty);
echo "PASS number CSV: UTF8, dates, exact order, multiline escaping, formula neutralization, statuses, empty results and unchanged source\n";
foreach(['desc'=>['2/QĐ','1/QĐ'],'asc'=>['1/QĐ','2/QĐ']]as$dir=>$expected){
    $pipes=[];$p=proc_open([PHP_BINARY,__DIR__.'/support/vanban_number_export_fixture.php','allowed',$dir],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($p)===0&&$err==='','Real export route '.$err);
    $stream=fopen('php://temp','w+b');fwrite($stream,substr($out,3));rewind($stream);fgetcsv($stream,0,',','"','');$symbols=[];while(($r=fgetcsv($stream,0,',','"',''))!==false)$symbols[]=$r[4];fclose($stream);check($symbols===$expected,'Actual page filter/book/sort matches download');
}
$p=proc_open([PHP_BINARY,__DIR__.'/support/vanban_number_export_fixture.php','denied'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($p)===0&&str_contains($out,'Không có quyền')&&!str_contains($out,'1/QĐ'),'User without number permission denied before data loading');
echo "PASS actual page export: decision book/search filters, ascending/descending order and permission denial, no school data\n";
