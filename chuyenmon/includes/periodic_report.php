<?php
/** Báo cáo tháng: phạm vi tổ, HTML an toàn và phụ lục chốt tại thời điểm lưu. */
function pr_norm($value): string { $value=trim((string)preg_replace('/\s+/u',' ',(string)$value)); return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value); }
function pr_upper(string $value): string {return function_exists('mb_strtoupper')?mb_strtoupper($value,'UTF-8'):strtoupper($value);}
function pr_escape($value): string { return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function pr_scope(array $user, array $teachers): array {
    $wide=($user['role']??'')==='admin'||cds_user_has_group($user,'bgh');
    $name=trim((string)($user['teacher_name']??$user['name']??''));
    $own=$name!==''?trim((string)get_teacher_group($name)):'';
    $groups=[];foreach($teachers as $teacher){$group=trim((string)get_teacher_group($teacher));if($group!==''&&($wide||pr_norm($group)===pr_norm($own)))$groups[pr_norm($group)]=$group;}
    if($own!==''&&!isset($groups[pr_norm($own)]))$groups[pr_norm($own)]=$own;
    natcasesort($groups);
    return ['wide'=>$wide,'name'=>$name,'own'=>$own,'groups'=>array_values($groups),'write'=>($wide||cds_user_has_group($user,'totruong'))&&cds_can_feature('cm.baocao.dinhky','edit')];
}
function pr_visible(array $row,array $scope): bool {
    if(!in_array($row['section']??'',['bc_dinhky','bc_thang'],true))return false;
    if($scope['wide'])return true;
    return !empty($row['report_group'])&&$scope['own']!==''&&pr_norm($row['report_group'])===pr_norm($scope['own']);
}
function pr_date(string $date): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date); return $d&&$d->format('Y-m-d')===$date; }
function pr_month(string $month): bool { return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',$month)===1 && (int)substr($month,0,4)>=2000 && (int)substr($month,0,4)<=2100; }
function pr_styles(string $style): string {
    $out=[];foreach(explode(';',$style)as$part){$pair=explode(':',$part,2);if(count($pair)!==2)continue;[$key,$value]=array_map('trim',$pair);$key=strtolower($key);$value=strtolower($value);
        if($key==='text-align'&&in_array($value,['left','center','right','justify'],true))$out[]=$key.':'.$value;
        if($key==='font-size'&&preg_match('/^(1[0-8]|20)(pt|px)$/',$value))$out[]=$key.':'.$value;
        if(in_array($key,['color','background-color'],true)&&preg_match('/^(#[a-f0-9]{3,6}|black|red|blue|green|yellow|white)$/',$value))$out[]=$key.':'.$value;
        if($key==='font-weight'&&in_array($value,['bold','700'],true))$out[]='font-weight:bold';
        if($key==='font-style'&&$value==='italic')$out[]='font-style:italic';
        if($key==='text-decoration'&&in_array($value,['underline','line-through'],true))$out[]=$key.':'.$value;
    }return implode(';',$out);
}
function pr_dom(string $html) {
    if(!class_exists('DOMDocument'))return null;
    $doc=new DOMDocument('1.0','UTF-8');$old=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="pr-root">'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
    libxml_clear_errors();libxml_use_internal_errors($old);return $doc;
}
function pr_clean_node($node): string {
    if($node->nodeType===XML_TEXT_NODE)return pr_escape($node->nodeValue);
    if($node->nodeType!==XML_ELEMENT_NODE)return '';
    $tag=strtolower($node->nodeName);
    if(in_array($tag,['script','style','iframe','object','embed','svg','math','form','input','textarea','button','img','video','audio'],true))return '';
    $inner='';foreach($node->childNodes as$child)$inner.=pr_clean_node($child);
    if(!in_array($tag,['p','div','br','strong','b','em','i','u','s','span','ul','ol','li','table','thead','tbody','tr','td','th','h3','h4','blockquote','sup','sub','a','font'],true))return $inner;
    if($tag==='font'){$tag='span';$sizeMap=['1'=>'10pt','2'=>'11pt','3'=>'12pt','4'=>'14pt','5'=>'16pt','6'=>'18pt','7'=>'20pt'];$fontStyle=isset($sizeMap[$node->getAttribute('size')])?'font-size:'.$sizeMap[$node->getAttribute('size')].';':'';$fontStyle.='color:'.$node->getAttribute('color');}else $fontStyle='';
    $attributes='';$style=pr_styles($node->getAttribute('style').';'.$fontStyle);if($style!=='')$attributes.=' style="'.pr_escape($style).'"';
    if($tag==='a'){$url=trim($node->getAttribute('href'));if(preg_match('~^https?://~i',$url))$attributes.=' href="'.pr_escape($url).'" rel="noopener"';}
    if(in_array($tag,['td','th'],true))foreach(['colspan','rowspan']as$key){$n=(int)$node->getAttribute($key);if($n>1&&$n<=12)$attributes.=' '.$key.'="'.$n.'"';}
    return '<'.$tag.$attributes.'>'.($tag==='br'?'':$inner.'</'.$tag.'>');
}
function pr_clean(string $html): string {
    if(strlen($html)>300000)throw new RuntimeException('Mỗi mục tối đa 300 KB. Vui lòng rút gọn nội dung.');
    $doc=pr_dom($html);if(!$doc)return '<p>'.nl2br(pr_escape(strip_tags($html))).'</p>';
    $root=$doc->getElementById('pr-root');$result='';if($root)foreach($root->childNodes as$child)$result.=pr_clean_node($child);return $result;
}
function pr_sections(): array {return ['implementation'=>'Triển khai các kế hoạch tháng','results'=>'1. Các nội dung đã tổ chức thực hiện','issues'=>'2. Tồn tại, nguyên nhân, giải pháp','next_plan'=>'II. Kế hoạch thực hiện nhiệm vụ tháng tiếp theo','manual_appendix'=>'Phụ lục bổ sung / biểu nhập tay'];}
function pr_snapshot(string $group,string $month,array $teachers): array {
    $from=$month.'-01';$to=date('Y-m-t',strtotime($from));$team=[];$summary=[];
    foreach($teachers as$name)if(pr_norm(get_teacher_group($name))===pr_norm($group)){$key=pr_norm($name);$team[$key]=true;$summary[$key]=['name'=>$name,'saved'=>0,'signed'=>0,'taught'=>0,'observations'=>0,'rated'=>0,'checks'=>0,'attendance'=>0];}
    $details=['observations'=>[],'checks'=>[],'attendance'=>[]];
    foreach(lb_record_map_range($from,$to)as$row){$name=trim((string)($row['actual_teacher']??''))?:trim((string)($row['scheduled_teacher']??''));$key=pr_norm($name);if(!isset($team[$key]))continue;$summary[$key]['saved']++;if(!empty($row['signed_at'])){$summary[$key]['signed']++;if(in_array($row['status']??'',['taught','substitute','makeup','online'],true))$summary[$key]['taught']+=(float)($row['actual_periods']??1);}}
    foreach(load_json(DATA_PATH.'/observations.json',[])as$row){if(!is_array($row))continue;$key=pr_norm($row['teacher']??'');$date=(string)($row['date']??$row['start_date']??'');if(!isset($team[$key])||$date<$from||$date>$to)continue;if(function_exists('cm_observation_form_recalculate'))cm_observation_form_recalculate($row);$summary[$key]['observations']++;if(($row['rating']??'')!=='')$summary[$key]['rated']++;$details['observations'][]=[$date,$row['teacher']??'',$row['class']??'',$row['subject']??'',$row['lesson_title']??'',$row['rating']??'Chưa đánh giá'];}
    foreach(load_json(DATA_PATH.'/professional_file_checks.json',[])as$row){if(!is_array($row))continue;$key=pr_norm($row['teacher_name']??'');$date=(string)($row['date']??'');if(!isset($team[$key])||$date<$from||$date>$to)continue;$summary[$key]['checks']++;$details['checks'][]=[$date,$row['teacher_name']??'',$row['inspector_name']??'',$row['content']??'',$row['result']??'',$row['rating']??''];}
    $attendance=load_json(dirname(__DIR__,2).'/data/thidua.json',['records'=>[]]);
    foreach($attendance['records']??[]as$row){if(!is_array($row)||($row['type']??'')!=='teacher_attendance')continue;$key=pr_norm($row['person_name']??'');$start=(string)($row['from_date']??$row['date']??'');$end=(string)($row['to_date']??$start);if(!isset($team[$key])||$start===''||$end<$from||$start>$to)continue;$summary[$key]['attendance']++;$details['attendance'][]=[max($start,$from),min($end,$to),$row['person_name']??'',$row['permission']??'',$row['reason']??'',$row['period']??'',$row['note']??''];}
    foreach($details as&$rows)usort($rows,fn($a,$b)=>strcmp($a[0],$b[0]));unset($rows);
    return ['group'=>$group,'month'=>$month,'from'=>$from,'to'=>$to,'at'=>date('c'),'teachers'=>array_values($summary),'details'=>$details];
}
function pr_table(array $headers,array $rows): string {
    $widths=count($headers)===9?[6,22,10,10,12,10,10,10,10]:[];$html='<table class="pr-data" data-pr-widths="'.implode(',',$widths).'">';if($widths){$html.='<colgroup>';foreach($widths as$width)$html.='<col style="width:'.$width.'%">';$html.='</colgroup>';}$html.='<thead><tr>';foreach($headers as$head)$html.='<th>'.pr_escape($head).'</th>';$html.='</tr></thead><tbody>';
    if(!$rows)$html.='<tr><td colspan="'.count($headers).'">Chưa có dữ liệu được ghi nhận trong kỳ.</td></tr>';
    foreach($rows as$row){$html.='<tr>';foreach($row as$cell)$html.='<td>'.pr_escape($cell).'</td>';$html.='</tr>';}
    return $html.'</tbody></table>';
}
function pr_body(array $r): string {
    $month=substr($r['month'],5,2).'/'.substr($r['month'],0,4);$next=substr($r['next_month'],5,2).'/'.substr($r['next_month'],0,4);$date=explode('-',$r['date']);$sections=$r['report_sections']??[];
    $html='<table class="pr-letterhead pr-national" data-pr-widths="38,62"><tr><td style="text-align:center;width:38%"><p style="font-size:13pt">'.pr_escape(pr_upper($r['school'])).'</p><p><strong>'.pr_escape(pr_upper($r['report_group'])).'</strong></p><p>__________</p><p>Số: '.pr_escape($r['number']?:'…/BC-TCM').'</p></td><td style="text-align:center;width:62%"><p style="font-size:12pt;white-space:nowrap"><strong>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</strong></p><p><strong>Độc lập - Tự do - Hạnh phúc</strong></p><p>_______________________</p><p><em>'.pr_escape($r['place']).', ngày '.(int)$date[2].' tháng '.(int)$date[1].' năm '.$date[0].'</em></p></td></tr></table>';
    $html.='<p style="text-align:center"><strong>BÁO CÁO</strong><br><strong>Kết quả thực hiện nhiệm vụ chuyên môn tháng '.$month.'<br>và kế hoạch thực hiện nhiệm vụ tháng '.$next.'</strong></p>';
    $html.='<p style="text-align:center">Kính gửi: '.pr_escape($r['recipient']).'</p>';
    $html.='<h3>Triển khai các kế hoạch tháng '.$month.'</h3>'.pr_clean($sections['implementation']??'');
    $html.='<h3>I. Kết quả thực hiện nhiệm vụ tháng '.$month.'</h3><h4>1. Các nội dung đã tổ chức thực hiện</h4>'.pr_clean($sections['results']??'');
    $html.='<h4>2. Tồn tại, nguyên nhân, giải pháp</h4>'.pr_clean($sections['issues']??'');
    $html.='<h3>II. Kế hoạch thực hiện nhiệm vụ tháng '.$next.'</h3>'.pr_clean($sections['next_plan']??'');
    $html.='<table class="pr-letterhead"><tr><td><p><strong><em>Nơi nhận:</em></strong><br>- '.pr_escape($r['recipient']).';<br>- Lưu: Tổ chuyên môn.</p></td><td style="text-align:center"><p><strong>TỔ TRƯỞNG CHUYÊN MÔN</strong></p><p><em>(Ký, ghi rõ họ tên)</em></p><p><br><br></p><p><strong>'.pr_escape($r['signer']).'</strong></p></td></tr></table>';
    $snap=$r['snapshot']??[];$html.='<div class="pr-page-break"></div><p style="text-align:center"><strong>PHỤ LỤC BÁO CÁO THÁNG '.$month.'</strong><br>'.pr_escape($r['report_group']).'</p><p><em>Số liệu từ '.pr_escape($snap['from']??'').' đến '.pr_escape($snap['to']??'').'; chốt lúc '.pr_escape(isset($snap['at'])?date('d/m/Y H:i',strtotime($snap['at'])):'').'.</em></p>';
    $rows=[];foreach($snap['teachers']??[]as$i=>$t)$rows[]=[$i+1,$t['name'],$t['saved'],$t['signed'],$t['taught'],$t['observations'],$t['rated'],$t['checks'],$t['attendance']];
    $html.='<h4>1. Tổng hợp theo giáo viên</h4>'.pr_table(['STT','Giáo viên','Tiết đã nhập','Tiết đã ký','Tiết dạy đã ký','ĐK dự giờ','Đã đánh giá','Lượt kiểm tra','Bản ghi công'],$rows);
    $html.='<p><em>Tiết dạy chỉ tính sổ đầu bài đã ký (kể cả dạy thay, dạy bù, trực tuyến). Chấm công là số bản ghi trong kỳ, không suy ra số ngày đi làm. Không có bản ghi không đồng nghĩa không thực hiện.</em></p>';
    foreach(['observations'=>['2. Dự giờ',['Ngày','Giáo viên','Lớp','Môn','Bài dạy','Xếp loại']],'checks'=>['3. Kiểm tra hồ sơ và nội dung khác',['Ngày','Giáo viên','Người kiểm tra','Nội dung','Kết quả','Xếp loại']],'attendance'=>['4. Chấm công / nghỉ / đi muộn',['Từ','Đến','Giáo viên','Phép','Lý do','Buổi / tiết','Ghi chú']]]as$key=>$definition)$html.='<h4>'.$definition[0].'</h4>'.pr_table($definition[1],$snap['details'][$key]??[]);
    if(trim(strip_tags($sections['manual_appendix']??''))!=='')$html.='<h4>5. Phụ lục bổ sung / biểu nhập tay</h4>'.pr_clean($sections['manual_appendix']);
    return $html;
}
function pr_html(array $r,bool $print=false): string {
    return '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>'.pr_escape($r['title']).'</title><style>@page{size:A4;margin:20mm 15mm 20mm 30mm}body{font:14pt/1.3 "Times New Roman",serif;color:#000;background:#eef2f6;margin:0}.pr-paper{box-sizing:border-box;width:210mm;margin:20px auto;padding:20mm 15mm 20mm 30mm;background:white;min-height:297mm}p{margin:0 0 6pt;text-align:justify;text-indent:10mm}p[style*="center"],td p{ text-indent:0 }h3,h4{font-size:14pt;margin:12pt 0 6pt;break-after:avoid}table{width:100%;border-collapse:collapse;table-layout:fixed}td,th{overflow-wrap:anywhere;vertical-align:top;padding:4pt}.pr-letterhead td{width:50%;border:0}.pr-letterhead p{text-align:inherit}.pr-data{font-size:11pt;margin-bottom:10pt}table:not(.pr-letterhead) th,table:not(.pr-letterhead) td{border:1px solid #000}.pr-national td:first-child{width:38%}.pr-national td:last-child{width:62%}.pr-data th{font-weight:bold;text-align:center;background:#eee}thead{display:table-header-group}tr{break-inside:avoid}ul,ol{margin:0 0 6pt;padding-left:24pt}.pr-page-break{break-before:page}.pr-printbar{font:14px system-ui;padding:12px;text-align:center;position:sticky;top:0;background:#fff;border-bottom:1px solid #ddd}.pr-printbar button,.pr-printbar a{display:inline-block;padding:10px;margin:0 4px;color:#123e60}@media print{body{background:white}.pr-printbar{display:none}.pr-paper{padding:0;width:auto;min-height:0;margin:0}}@media screen and (max-width:800px){.pr-paper{width:100%;padding:20px;min-height:0}.pr-letterhead{font-size:11pt}.pr-data{font-size:9pt}}</style></head><body>'.($print?'<div class="pr-printbar"><button onclick="window.print()">In / Lưu PDF</button><a href="'.pr_escape(BASE_URL.'baocao.php?tab=dinhky&id='.rawurlencode($r['id'])).'">Quay lại báo cáo</a></div>':'').'<main class="pr-paper">'.pr_body($r).'</main></body></html>';
}

function pr_store(array $data, ?string $expectedRevision=null): string {
    $rows=cm_docs_all();$id=(string)($data['id']??'');$found=false;
    foreach($rows as &$row)if(($row['id']??'')===$id){if($expectedRevision!==null&&$expectedRevision!==(string)($row['report_revision']??$row['updated_at']??$row['created_at']??''))throw new RuntimeException('Báo cáo đã thay đổi. Hãy tải lại bản mới trước khi lưu.');$row=array_merge($row,$data,['updated_at'=>date('c')]);$found=true;break;}unset($row);
    if(!$found){$id=$id?:cm_doc_uid();$data['id']=$id;$data['created_at']=date('c');$rows[]=$data;}
    if(!save_json(CM_DOCS_FILE,array_values($rows)))throw new RuntimeException('Không lưu được báo cáo. Vui lòng thử lại; nội dung nhập vẫn được giữ trên trang.');
    return $id;
}
