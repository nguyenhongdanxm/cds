import json, os, pathlib, shutil, socket, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error
root=pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='notice-timing-') as folder:
 d=pathlib.Path(folder);(d/'chuyenmon/includes').mkdir(parents=True);(d/'includes').mkdir()
 shutil.copy(root/'chuyenmon/kehoach.php',d/'chuyenmon/kehoach.php');shutil.copy(root/'includes/notice_timing.php',d/'includes/notice_timing.php')
 (d/'includes/push_notifications.php').write_text("<?php function cds_push_dashboard_source_id($r){return 'fixture';}function cds_push_publish(...$a){return ['saved'=>true,'devices'=>0];}")
 (d/'chuyenmon/includes/header.php').write_text('<!doctype html><html><meta charset="utf-8"><body>');(d/'chuyenmon/includes/footer.php').write_text('</body></html>');(d/'chuyenmon/includes/cm_docs.php').write_text('<?php')
 (d/'chuyenmon/includes/functions.php').write_text('''<?php
 session_start();$_SESSION['cds_user']=['name'=>'Fixture','role'=>'admin'];define('BASE_URL','/chuyenmon/');define('DATA_PATH',dirname(__DIR__,2));function require_login(){}function get_teachers_sorted(){return [];}function e($x){return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');}function flash(...$args){}function cds_storage_handle_upload(...$a){return '';}function cds_storage_file_url($p){return '#';}
 function cm_docs_all(){return is_file(DATA_PATH.'/rows.json')?json_decode(file_get_contents(DATA_PATH.'/rows.json'),true):[];}function cm_docs_by_section($s){return array_values(array_filter(cm_docs_all(),fn($r)=>$r['section']===$s));}function cm_doc_save($r){$rows=cm_docs_all();$found=false;foreach($rows as&$old)if($old['id']===$r['id']){$old=$r;$found=true;}unset($old);if(!$found)$rows[]=$r;file_put_contents(DATA_PATH.'/rows.json',json_encode($rows));}function cm_resolve_deadline(...$a){return null;}
 ''')
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 php=os.environ.get('PHP_BINARY','php');server=subprocess.Popen([php,'-d','session.save_path='+folder,'-S',f'127.0.0.1:{port}','-t',folder],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 class NoRedirect(urllib.request.HTTPRedirectHandler):
  def redirect_request(self,*args):return None
 opener=urllib.request.build_opener(NoRedirect());url=f'http://127.0.0.1:{port}/chuyenmon/kehoach.php?tab=thongbao'
 def post(values):
  try:return opener.open(urllib.request.Request(url,urllib.parse.urlencode(values).encode())).status
  except urllib.error.HTTPError as e:return e.code
 try:
  for _ in range(50):
   try:html=urllib.request.urlopen(url).read().decode();break
   except (OSError,urllib.error.URLError):time.sleep(.1)
  assert 'id="doc_event_date"' in html and 'id="doc_hide_after_days"' in html
  payload=dict(action='save',title='Sự kiện thử',date='2026-11-20',event_date='2026-11-26',hide_after_days='3')
  assert post(payload)==302;rows=json.loads((d/'rows.json').read_text());assert rows[0]['event_date']=='2026-11-26' and rows[0]['hide_after_days']==3
  identity=rows[0]['id'];payload['id']=identity;payload['hide_after_days']='0';assert post(payload)==302;assert json.loads((d/'rows.json').read_text())[0]['hide_after_days']==0
  del payload['event_date'];del payload['hide_after_days'];assert post(payload)==302;row=json.loads((d/'rows.json').read_text())[0];assert row['event_date']=='2026-11-26' and row['hide_after_days']==0,'Older form preserves saved timing'
  before=(d/'rows.json').read_bytes()
  for invalid in [dict(hide_after_days='-1'),dict(event_date='2026-02-30'),dict(hide_after_days='3651')]:
   assert post(payload|invalid)==302;assert (d/'rows.json').read_bytes()==before,'Invalid input must not change records'
  html=urllib.request.urlopen(url).read().decode();assert 'event_date' in html and 'hide_after_days' in html and 'editDoc(' in html
  print('PASS actual notice controller HTTP: form fields, create/edit persistence, zero retention, old-form preservation and invalid-input no-write')
 finally:server.terminate();server.wait()
