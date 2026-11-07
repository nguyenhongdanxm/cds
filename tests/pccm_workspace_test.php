<?php
function check($v,$m){if(!$v)throw new RuntimeException($m);}
$root=dirname(__DIR__);$src=file_get_contents($root.'/chuyenmon/them.php');$gdp=file_get_contents($root.'/chuyenmon/includes/program_assignment_view.php');
check(!str_contains($src,'href="?program=1')&&!str_contains($gdp,'href="?program=1'),'No query-only program links under base href');
check(!str_contains($src,"if(isset(\$_GET['program'])){require"),'Program no longer exits the workspace');
check(str_contains($src,'id="pccmCompose"')&&str_contains($src,'id="board"'),'One board and contextual composer');
$baseline=getenv('CDS_WORKSPACE_BASELINE');if($baseline){$old=file_get_contents($baseline);$old=str_replace("require_once __DIR__.'/includes/pccm_release_controller.php';\n",'',$old);$marker="require_once 'includes/header.php';";$src=str_replace("require_once __DIR__.'/includes/pccm_release_controller.php';\n",'',$src);check(str_replace("\$pccmWorkspaceNative = true;\n",'',explode($marker,$src)[0])===str_replace("\$pccmWorkspaceNative = true;\n",'',explode($marker,$old)[0]),'All existing POST handlers, actions and checks unchanged');}
check(str_contains(file_get_contents($root.'/chuyenmon/danhsach.php'),"===\x27GET\x27"),'Legacy list GET redirects; POST remains present');
check(strpos($src,'id="board"')<strpos($src,'id="pccmCompose"'),'Main board must render before optional editors');
echo "PASS workspace source: explicit routing, one work screen, preserved handlers and legacy list entry\n";
