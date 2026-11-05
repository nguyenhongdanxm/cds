<?php
require __DIR__.'/../chuyenmon/includes/pccm_panel_view.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$tmp=sys_get_temp_dir().'/cds-pccm-panel-'.bin2hex(random_bytes(6));mkdir($tmp,0700);
try{
 $level=ob_get_level();file_put_contents($tmp.'/valid.php','<?php echo "<p>".htmlspecialchars($teacher)."</p>";');
 ob_start();pccm_render_panel($tmp.'/valid.php',['teacher'=>'GV A']);$html=ob_get_clean();check($html==='<p>GV A</p>','Context available in isolated panel');check(ob_get_level()===$level,'Success preserves buffer stack');
 // Reproduce the production failure: CM teachers are strings, not arrays of name records.
 file_put_contents($tmp.'/bad.php','<?php echo "<select><option>";$teacher="GV A";echo $teacher["name"];');
 ob_start();echo '<div id="board">Phân công đã có</div>';pccm_render_panel($tmp.'/bad.php');echo '<footer>complete</footer>';$html=ob_get_clean();
 check(str_contains($html,'id="board"')&&str_contains($html,'<footer>complete</footer>'),'TypeError in optional editor does not abort board or footer');
 check(!str_contains($html,'<select>')&&str_contains($html,'role="alert"'),'Broken partial markup is discarded and replaced with a notice');
 check(!str_contains($html,'Cannot access offset')&&!str_contains($html,$tmp),'Internal details are logged, not disclosed');check(ob_get_level()===$level,'Error preserves buffer stack');
 echo "PASS real string-teacher TypeError isolated: board/footer survive, partial markup discarded, context and buffers preserved\n";
}finally{foreach(glob($tmp.'/*')as$f)unlink($f);rmdir($tmp);}
