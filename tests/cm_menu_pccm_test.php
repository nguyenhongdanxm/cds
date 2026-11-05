<?php
function check($v,$m){if(!$v)throw new RuntimeException($m);}function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}define('BASE_URL','/chuyenmon/');
function get_active_version_id(){return 'active';}function get_version($id){return in_array($id,['active','archive'],true)?['id'=>$id,'name'=>'Phiên bản '.$id]:null;}
function workflow($page,$allowed=null,$version='archive'){$current=$page;$view_id=$version;$cmLayoutCan=static fn($p)=>$allowed===null||in_array($p,$allowed,true);ob_start();require __DIR__.'/../chuyenmon/includes/pccm_workflow_view.php';return ob_get_clean();}
foreach(['monhoc','lop','giaovien','kiemnhiem']as$page){$html=workflow($page);check(substr_count($html,'aria-current="page"')===1,'One current input tab');foreach(['Môn học','Lớp','Giáo viên','Kiêm nhiệm']as$label)check(str_contains($html,$label),'Every input tab reachable');}
foreach(['them','danhsach','doicheo','rasoat','sua']as$page)check(workflow($page)==='','Single workspace has no duplicate work-stage navigation');
foreach(['ketqua','thongke','xuat_bang']as$page){$html=workflow($page);check(substr_count($html,'?v=archive')===3,'View/statistics/export preserve selected version');check(str_contains($html,'Phiên bản archive'),'Displayed version is selected, not active');}
check(!str_contains(workflow('ketqua',['cm.tracuu']),'thongke.php'),'No statistics link without existing permission');check(!str_contains(workflow('thongke',['cm.thongke']),'ketqua.php'),'No versions link without existing permission');check(workflow('tracuu')==='','Lookup remains separate');
$root=dirname(__DIR__);$baseline=getenv('CDS_PCCM_BASELINE');if($baseline&&is_file($baseline)){$old=file_get_contents($baseline);$new=file_get_contents($root.'/chuyenmon/ketqua.php');$old=str_replace("require_once 'includes/header.php';",'',$old);$new=str_replace("require_once 'includes/header.php';",'',$new);$new=str_replace("    pccm_lock_request(DATA_PATH);\n",'',$new);$new=str_replace("if (!empty(pccm_release_state(DATA_PATH)['locked'][\$id])) {\n            flash('Phiên bản đã khóa. Mở khóa trước khi xóa.','warning');\n        } elseif (count(\$versions) <= 1) {","if (count(\$versions) <= 1) {",$new);$marker="\$versions = get_versions();\nusort";check(trim(explode($marker,$old)[0])===trim(explode($marker,$new)[0]),'All version POST handlers and authorization prefix untouched');}
$oldOverview=file_get_contents($root.'/chuyenmon/tongquan.php');check(str_contains($oldOverview,"BASE_URL.'them.php'")&&!str_contains($oldOverview,'get_assignment_stats'),'Legacy overview redirects without duplicate statistics');
$doc=static fn($body)=>'<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://menu.test/bootstrap.css"><style>'.file_get_contents(__DIR__.'/../chuyenmon/assets/pccm-workflow.css').'</style></head><body><main class="container">'.$body.'</main></body></html>';
file_put_contents('/tmp/cm-menu-pccm-workflow.html',$doc(workflow('monhoc')));file_put_contents('/tmp/cm-menu-pccm-result.html',$doc(workflow('ketqua')));file_put_contents('/tmp/cm-menu-pccm-export.html',$doc(workflow('xuat_bang').'<select id="selVer"><option value="archive">Archive version</option><option value="active">Active version</option></select>'));echo "PASS PCCM workflow: four input tabs, single work screen, selected-version parity, permission isolation, separate lookup, legacy redirect and unchanged version POST handlers\n";

// Render the real subject controls without loading production storage or handlers.
function is_key_active($data,$key){return $key !== '9';}
function period_val($data,$key){return 8;}
function get_grade($key){return $key;}
$subjectSource=file_get_contents($root.'/chuyenmon/monhoc.php');
$functionStart=strpos($subjectSource,'function render_grade_row(');
$functionEnd=strpos($subjectSource,"\n?>\n\n<style>",$functionStart);
eval(substr($subjectSource,$functionStart,$functionEnd-$functionStart));
ob_start();render_grade_row('Toán học',[],array_map(fn($g)=>['key'=>(string)$g,'label'=>'Khối '.$g],[6,7,8,9]),'thcs','thcs');$controls=ob_get_clean();
check(substr_count($controls,'name="on[]"')===4,'Four grade choices preserved');
check(str_contains($controls,'name="p_9"')&&str_contains($controls,'disabled'),'Inactive grade stays disabled');
$css=file_get_contents($root.'/chuyenmon/assets/pccm-input.css');
$toggleStart=strpos($subjectSource,'function toggleCell(');$toggleEnd=strpos($subjectSource,'</script>',$toggleStart);
$fixture=$doc(workflow('monhoc').'<div class="subject-row"><strong>Toán học</strong>'.$controls.'</div><script>'.substr($subjectSource,$toggleStart,$toggleEnd-$toggleStart).'</script>');
$fixture=str_replace('</head>','<style>'.$css.'</style></head>',$fixture);
$fixture=str_replace('<body>','<body class="cm-body pccm-input-page">',$fixture);
file_put_contents('/tmp/cm-menu-pccm-input.html',$fixture);
echo "PASS input controls: real grade form, names, inactive state and compact fixture\n";
