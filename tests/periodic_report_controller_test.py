"""Exercise HTTP-controller actions in isolated PHP fixtures, without production data."""
import json, os, shutil, subprocess, tempfile
from pathlib import Path
root=Path(__file__).resolve().parents[1]
php=os.environ.get('PHP_BINARY','php')
args=json.loads(os.environ.get('PHP_ARGS','[]'))
with tempfile.TemporaryDirectory() as directory:
    work=Path(directory); inc=work/'includes';inc.mkdir()
    for f in ['periodic_report.php','periodic_report_page.php','periodic_report_docx.php','observation_form.php']:
        shutil.copy(root/'chuyenmon/includes'/f,inc/f)
    (inc/'header.php').write_text('<?php ?>HEADER',encoding='utf8')
    (inc/'footer.php').write_text('<?php ?>FOOTER',encoding='utf8')
    bootstrap=r'''<?php
    error_reporting(E_ALL); define('BASE_URL','/chuyenmon/');define('DATA_PATH','/fixture/cm');define('CM_DOCS_FILE','/fixture/cm/cm_docs.json');define('SCHOOL_NAME','Trường thử nghiệm');
    $case=json_decode(file_get_contents($argv[1]),true);$_GET=$case['get']??[];$_POST=$case['post']??[];$_SERVER['REQUEST_METHOD']=$_POST?'POST':'GET';$rows=$case['rows']??[];$actor=$case['user']??['name'=>'An','groups'=>['totruong']];
    function cds_user(){return $GLOBALS['actor'];}function cds_user_has_group($u,$g){return in_array($g,$u['groups']??[],true)||($u['role']??'')===$g;}
    function cds_can_feature($f,$l){return true;}function cds_drive_csrf_token(){return 'token';}function get_teachers_sorted(){return ['An','Bình','Chi'];}function get_teacher_group($n){return $n==='Chi'?'Tổ B':'Tổ A';}
    function cm_docs_by_section($section){return array_values(array_filter($GLOBALS['rows'],fn($r)=>($r['section']??'')===$section));}function cm_docs_all(){return $GLOBALS['rows'];}function cm_doc_uid(){return 'new_report';}
    function save_json($file,$data){$GLOBALS['rows']=$data;return true;}function load_json($file,$default=[]){return $default;}function lb_record_map_range($f,$t){return [];}
    function cm_doc_delete($id){$GLOBALS['rows']=array_values(array_filter($GLOBALS['rows'],fn($r)=>$r['id']!==$id));}function flash($m,$type='success'){$GLOBALS['flash']=$m;}
    function cds_drive_type_for_action($a,$fallback){return $fallback;}function cds_drive_page_action(){return 'page:/chuyenmon/baocao.php?tab=dinhky';}function cds_drive_upload_bytes($bytes,$name,$mime,$type){$GLOBALS['upload']=[$name,$mime,$type,substr($bytes,0,2)];return empty($GLOBALS['case']['drive_error'])?['ok'=>true,'path'=>'gdrive:test']:['ok'=>false,'message'=>'Drive unavailable'];}function cds_storage_file_url($p){return 'https://drive.google.com/file/d/test/view';}
    register_shutdown_function(function(){file_put_contents($GLOBALS['argv'][2],json_encode(['rows'=>$GLOBALS['rows'],'status'=>http_response_code(),'flash'=>$GLOBALS['flash']??'','upload'=>$GLOBALS['upload']??null],JSON_UNESCAPED_UNICODE));});
    require __DIR__.'/includes/periodic_report_page.php';
    '''
    (work/'run.php').write_text(bootstrap,encoding='utf8')
    def run(case):
        (work/'case.json').write_text(json.dumps(case),encoding='utf8')
        result=subprocess.run([php,*args,str(work/'run.php'),str(work/'case.json'),str(work/'result.json')],capture_output=True,text=True)
        assert result.returncode==0,(result.stdout,result.stderr)
        assert not result.stderr,result.stderr
        assert 'Warning:' not in result.stdout,result.stdout
        return json.loads((work/'result.json').read_text()),result.stdout
    post={'action':'periodic_save','csrf':'token','id':'','report_group':'Tổ B','month':'2026-10','next_month':'2026-11','date':'2026-10-07','school':'Trường thử nghiệm','place':'Xín Mần','number':'01/BC','recipient':'BGH','signer':'An','report_sections':{'results':'<p><b>Kết quả</b></p>'}}
    state,_=run({'post':post});saved=state['rows'][0]
    assert saved['report_group']=='Tổ A',saved
    assert saved['snapshot']['group']=='Tổ A'
    assert '<b>Kết quả</b>' in saved['report_sections']['results']
    state,out=run({'rows':[saved],'get':{'id':saved['id'],'view':'preview'}})
    assert 'iframe' in out and '&view=embed' in out and 'Lưu vào Google Drive' in out
    # Saved snapshot stays unchanged when editing content; refresh is explicit.
    snapshot=saved['snapshot'].copy();snapshot['at']='2020-01-01T00:00:00+00:00';saved['snapshot']=snapshot
    update={**post,'id':saved['id'],'revision':saved['report_revision']}
    state,_=run({'rows':[saved],'post':update});updated=state['rows'][0]
    assert updated['snapshot']==snapshot
    state,_=run({'rows':[saved],'post':{**update,'refresh_snapshot':'1'}})
    assert state['rows'][0]['snapshot']['at']!=snapshot['at']
    state,out=run({'rows':[updated],'post':update})
    assert state['rows'][0]==updated and 'phiên khác' in out and 'Kết quả' in out
    state,out=run({'rows':[saved],'get':{'id':saved['id']},'user':{'name':'Chi','groups':['totruong']}})
    assert state['status']==404 and 'Không tìm thấy' in out
    state,out=run({'post':{**post,'csrf':'bad'}})
    assert state['status']==403 and not state['rows']
    state,out=run({'post':post,'user':{'name':'An','groups':['gv']}})
    assert state['status']==403 and not state['rows']
    state,out=run({'post':{**post,'month':'2026-99'}})
    assert not state['rows'] and 'không hợp lệ' in out and 'Kết quả' in out
    state,out=run({'rows':[saved],'post':{'action':'periodic_drive','csrf':'token','id':saved['id'],'revision':saved['report_revision']}})
    assert state['upload'][1:]==['application/vnd.openxmlformats-officedocument.wordprocessingml.document','plans','PK']
    assert state['rows'][0]['drive_path']=='gdrive:test'
    state,out=run({'rows':[saved],'drive_error':True,'post':{'action':'periodic_drive','csrf':'token','id':saved['id'],'revision':saved['report_revision']}})
    assert state['rows'][0]==saved and 'Drive unavailable' in out
    state,out=run({'rows':[saved],'post':{'action':'periodic_delete','csrf':'token','id':saved['id']}})
    assert state['status']==403 and state['rows']==[saved]
    state,_=run({'rows':[saved],'user':{'role':'admin','name':'Boss'},'post':{'action':'periodic_delete','csrf':'token','id':saved['id']}})
    assert state['rows']==[]
    state,out=run({'rows':[saved],'post':{'action':'save','csrf':'token','id':saved['id']}})
    assert state['rows']==[saved] and 'không hợp lệ' in out
print('PASS: controller save/preview, enforced own team, snapshot retention/refresh, stale edit, 404/CSRF/roles, Drive success/failure, delete and legacy action guards.')
