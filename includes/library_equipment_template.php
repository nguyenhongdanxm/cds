<?php
/** Populate the existing import template; reading/exporting never changes inventory. */
function lib_equipment_template_file(array $equipment): string {
    if(!class_exists('ZipArchive')||!class_exists('DOMDocument'))throw new RuntimeException('Hosting cần bật ZipArchive và DOM để xuất mẫu Excel.');
    $template=dirname(__DIR__).'/assets/mau-nhap-danh-muc-thiet-bi.xlsx';$tmp=tempnam(sys_get_temp_dir(),'eqtemplate_');
    if($tmp===false)throw new RuntimeException('Không tạo được file Excel tạm.');$zip=new ZipArchive();$opened=false;
    try{
        if(!copy($template,$tmp)||$zip->open($tmp)!==true)throw new RuntimeException('Không mở được mẫu Excel.');$opened=true;
        $dom=new DOMDocument();if(!$dom->loadXML((string)$zip->getFromName('xl/worksheets/sheet1.xml'),LIBXML_NONET))throw new RuntimeException('Mẫu Excel không hợp lệ.');
        $ns='http://schemas.openxmlformats.org/spreadsheetml/2006/main';$xp=new DOMXPath($dom);$xp->registerNamespace('s',$ns);$sheetData=$xp->query('//s:sheetData')->item(0);$prototype=$xp->query('//s:sheetData/s:row[@r="4"]')->item(0);
        if(!$sheetData||!$prototype)throw new RuntimeException('Mẫu Excel thiếu dòng dữ liệu.');
        $styles=[];foreach($xp->query('./s:c',$prototype)as$c)$styles[preg_replace('/\d/','',$c->getAttribute('r'))]=$c->getAttribute('s');
        foreach(iterator_to_array($xp->query('./s:row',$sheetData))as$row)if((int)$row->getAttribute('r')>=4)$sheetData->removeChild($row);
        $setText=static function(DOMElement $cell,string $value)use($dom,$ns){while($cell->firstChild)$cell->removeChild($cell->firstChild);$cell->setAttribute('t','inlineStr');$is=$dom->createElementNS($ns,'is');$t=$dom->createElementNS($ns,'t');$t->setAttribute('xml:space','preserve');$t->appendChild($dom->createTextNode(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$value)));$is->appendChild($t);$cell->appendChild($is);};
        $setText($xp->query('//s:c[@r="A1"]')->item(0),'MẪU NHẬP DANH MỤC THIẾT BỊ – CÓ DỮ LIỆU');
        $setText($xp->query('//s:c[@r="A2"]')->item(0),'Dữ liệu số tồn hiện tại. Khi nhập lại, thiết bị trùng sẽ được CỘNG THÊM số lượng. Chỉ giữ các dòng cần nhập và sửa số lượng thành số BỔ SUNG, tránh cộng trùng.');
        foreach(['J3'=>'Số lượng tốt','K3'=>'Số lượng xuống cấp','L3'=>'Số lượng hỏng']as$ref=>$label)$setText($xp->query('//s:c[@r="'.$ref.'"]')->item(0),$label);
        $equipment=array_values(array_filter($equipment,'is_array'));
        foreach($equipment as$i=>$eq){$n=$i+4;$status=lib_equipment_status_quantities($eq);$values=[$i+1,$eq['grade']??'',$eq['subject']??'',$eq['name']??'',$eq['code']??'',$status['total'],$eq['unit']??'',$eq['production_year']??'',$eq['provider']??$eq['source']??'',$status['good'],$status['degraded'],$status['broken'],$eq['note']??''];
            $row=$dom->createElementNS($ns,'row');$row->setAttribute('r',(string)$n);$row->setAttribute('ht','30');$row->setAttribute('customHeight','1');
            foreach($values as$j=>$value){$col=chr(65+$j);$cell=$dom->createElementNS($ns,'c');$cell->setAttribute('r',$col.$n);if(isset($styles[$col]))$cell->setAttribute('s',$styles[$col]);
                if(in_array($col,['A','F','J','K','L'],true)){if($col==='F')$cell->appendChild($dom->createElementNS($ns,'f','SUM(J'.$n.':L'.$n.')'));$cell->appendChild($dom->createElementNS($ns,'v',(string)(int)$value));}
                else $setText($cell,(string)$value);$row->appendChild($cell);
            }$sheetData->appendChild($row);
        }
        $last=max(4,count($equipment)+3);$filter=$xp->query('//s:autoFilter')->item(0);if(!$filter){$filter=$dom->createElementNS($ns,'autoFilter');$sheetData->parentNode->insertBefore($filter,$sheetData->nextSibling);}$filter->setAttribute('ref','A3:M'.$last);
        if(!$zip->addFromString('xl/worksheets/sheet1.xml',$dom->saveXML())||!$zip->close())throw new RuntimeException('Không ghi được file Excel.');$opened=false;return$tmp;
    }catch(Throwable$e){if($opened)$zip->close();@unlink($tmp);throw$e;}
}
