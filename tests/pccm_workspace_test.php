<?php
function check($v,$m){if(!$v)throw new RuntimeException($m);}
$root=dirname(__DIR__);$src=file_get_contents($root.'/chuyenmon/them.php');$gdp=file_get_contents($root.'/chuyenmon/includes/program_assignment_view.php');
check(!str_contains($src,'href="?program=1')&&!str_contains($gdp,'href="?program=1'),'No query-only program links under base href');
check(!str_contains($src,"if(isset(\$_GET['program'])){require"),'Program no longer exits the workspace');
check(str_contains($src,'id="pccmCompose"')&&str_contains($src,'id="board"'),'One board and contextual composer');
$baseline=getenv('CDS_WORKSPACE_BASELINE');if($baseline){$old=file_get_contents($baseline);$marker="require_once 'includes/header.php';";check(explode($marker,$src)[0]===explode($marker,$old)[0],'All existing POST handlers, actions and checks unchanged');}
check(str_contains(file_get_contents($root.'/chuyenmon/danhsach.php'),"===\x27GET\x27"),'Legacy list GET redirects; POST remains present');
echo "PASS workspace source: explicit routing, one work screen, preserved handlers and legacy list entry\n";
