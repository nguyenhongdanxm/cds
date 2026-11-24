import json, os, pathlib, shutil, socket, subprocess, tempfile, time, urllib.request, urllib.parse, urllib.error
root=pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='central-notice-dates-') as folder:
 d=pathlib.Path(folder);(d/'includes').mkdir();(d/'data').mkdir();(d/'cm_data').mkdir()
 for name in ['notices.php','public_notice.php']:shutil.copy(root/name,d/name)
 for name in ['admin_notices_v2.php','notice_timing.php']:shutil.copy(root/'includes'/name,d/'includes'/name)
 (d/'includes/push_notifications.php').write_text("<?php function cds_push_publish(...$a){return ['saved'=>true];}")
 (d/'includes/auth.php').write_text('''<?php
 session_start();define('BASE_URL','/');define('DATA_PATH',dirname(__DIR__).'/data');define('PCCM_DATA_PATH',dirname(__DIR__).'/cm_data');
 function require_login(){}function current_user(){return ['role'=>$_COOKIE['test_role']??'admin','name'=>'Fixture'];}function can_module($m,$p){return (current_user()['role']??'')==='teacher'&&$m==='chuyenmon'&&$p==='edit';}function e($x){return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');}function flash(...$a){}function show_flash(){}function cds_json_load($p,$fallback=[]){return is_file($p)?json_decode(file_get_contents($p),true):$fallback;}function cds_json_save($p,$rows){return file_put_contents($p,json_encode($rows))!==false;}function cds_drive_token(...$a){throw new RuntimeException('No live Drive calls in fixture');}
 ''')
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 server=subprocess.Popen([os.environ.get('PHP_BINARY','php'),'-d','session.save_path='+folder,'-S',f'127.0.0.1:{port}','-t',folder],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 base=f'http://127.0.0.1:{port}';url=base+'/notices.php'
 def request(path='/notices.php',values=None,role='admin'):
  req=urllib.request.Request(base+path,None if values is None else urllib.parse.urlencode(values).encode(),headers={'Cookie':'test_role='+role})
  try:
   with urllib.request.urlopen(req) as response:return response.status,response.read().decode()
  except urllib.error.HTTPError as response:return response.code,response.read().decode()
 try:
  for _ in range(50):
   try:status,html=request();break
   except (OSError,urllib.error.URLError):time.sleep(.1)
  assert status==200
  for marker in ['id="n_event_date"','id="n_hide_date"','name="notice_timing_version"','Ngày ban hành','Ngày diễn ra / chính thức áp dụng','Ngày ẩn']:assert marker in html,marker
  assert 'Ngày ban hành / sự kiện' not in html and 'id="n_deadline"' not in html
  payload=dict(notice_action='save',notice_ajax='1',field='chung',title='Thông báo chung ba ngày',date='2026-11-20',event_date='2026-11-23',hide_date='2026-11-30',notice_timing_version='2',content='Nội dung gốc')
  status,body=request(values=payload);assert status==200 and json.loads(body)['ok'];rows=json.loads((d/'data/cm_docs.json').read_text());row=rows[0];identity=row['id'];assert row['event_date']=='2026-11-23' and row['date']=='2026-11-20' and row['hide_date']=='2026-11-30'
  feed=json.loads((d/'cm_data/dashboard_notices.json').read_text());assert feed[0]['section']=='central_notice' and feed[0]['event_date']=='2026-11-23' and feed[0]['hide_date']=='2026-11-30','Root notices sync preserves all three dates'
  status,html=request();assert status==200 and '2026-11-23' in html and '2026-11-30' in html and 'n_event_date.value=r.event_date' in html
  status,public=request('/public_notice.php?id='+identity);assert status==200 and 'Diễn ra / áp dụng: 2026-11-23' in public and 'Ẩn từ: 2026-11-30' in public
  payload['id']=identity;payload['event_date']='2026-11-24';payload['hide_date']='';status,body=request(values=payload);assert status==200 and json.loads(body)['ok'];row=json.loads((d/'data/cm_docs.json').read_text())[0];assert row['event_date']=='2026-11-24' and row['hide_date']=='' and row['content']=='Nội dung gốc'
  before=(d/'data/cm_docs.json').read_bytes()
  for bad in [dict(event_date=''),dict(event_date='2026-02-30'),dict(hide_date='2026-11-19')]:
   status,body=request(values=payload|bad);assert status==400 and not json.loads(body)['ok'];assert (d/'data/cm_docs.json').read_bytes()==before
  status,_=request(role='none');assert status==403
  status,body=request(values=payload,role='teacher');assert status==400 and not json.loads(body)['ok'];assert (d/'data/cm_docs.json').read_bytes()==before
  print('PASS actual notices.php HTTP: correct form, three-date create/edit/clear, sync-to-dashboard preservation, public view, invalid-input no-write and field permissions')
 finally:server.terminate();server.wait()
