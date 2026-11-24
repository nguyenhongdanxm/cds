import http.cookiejar,json,os,pathlib,re,shutil,socket,subprocess,tempfile,time,urllib.request,urllib.parse,urllib.error
root=pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='equipment-ledgers-') as folder:
 d=pathlib.Path(folder);(d/'includes').mkdir();(d/'data').mkdir()
 for name in ['thuvien.php','thietbi_phieu.php']:shutil.copy(root/name,d/name)
 for name in ['library_public_catalog.php','library_equipment_ledgers.php','library_equipment_ledgers_view.php']:shutil.copy(root/'includes'/name,d/'includes'/name)
 for name in ['csdl_store.php','library_book_excel.php','library_maintenance.php','module_switcher.php']:(d/'includes'/name).write_text('<?php')
 (d/'includes/library_equipment_excel.php').write_text("<?php function lib_equipment_status_quantities($e){return ['total'=>$e['quantity']??0,'good'=>$e['good_quantity']??0,'degraded'=>0,'broken'=>0];}")
 (d/'includes/form_profile.php').write_text("<?php function cds_form_school_name(){return 'Trường thử nghiệm';}")
 (d/'includes/auth.php').write_text('''<?php session_start();define('BASE_URL','/');define('DATA_PATH',dirname(__DIR__).'/data');define('SCHOOL_YEAR','2026 - 2027');define('SCHOOL_NAME','Fixture');function current_user(){return ['role'=>$_COOKIE['role']??'admin','name'=>'Vương Văn Quân'];}function require_login(){}function can_perm_level($p,$l){return ($_COOKIE['role']??'admin')==='teacher'&&$p==='tb.muontra'&&$l==='view';}function load_json($p,$d=[]){return is_file($p)?json_decode(file_get_contents($p),true):$d;}function save_json($p,$d){file_put_contents($p,json_encode($d));return true;}function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function flash(...$a){}function show_flash(){}
 ''')
 data={'books':[],'loans':[],'equipment':[{'id':'eq','name':'Đệm nhảy cao','code':'EQ01','quantity':10,'good_quantity':10}],'equipment_loans':[],'maintenance':[]};store=d/'data/library_equipment.json';store.write_text(json.dumps(data))
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 server=subprocess.Popen([os.environ.get('PHP_BINARY','php'),'-d','session.save_path='+folder,'-S',f'127.0.0.1:{port}','-t',folder],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 class NoRedirect(urllib.request.HTTPRedirectHandler):
  def redirect_request(self,*args):return None
 jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect());base=f'http://127.0.0.1:{port}'
 def request(path='/thuvien.php?tab=equipment_loans',form=None,role='admin'):
  req=urllib.request.Request(base+path,None if form is None else urllib.parse.urlencode(form).encode(),headers={'Cookie':'; '.join(['role='+role]+[c.name+'='+c.value for c in jar])})
  try:
   with opener.open(req) as r:return r.status,r.read().decode(),r.headers
  except urllib.error.HTTPError as r:return r.code,r.read().decode(),r.headers
 try:
  for _ in range(50):
   try:status,html,_=request();break
   except (OSError,urllib.error.URLError):time.sleep(.1)
  assert status==200,html;csrf=re.search('name="csrf" value="([^"]+)"',html).group(1)
  payload=dict(action='save_equipment_loan',csrf=csrf,equipment_borrower='Vương Văn Quân',equipment_subject='GDTC',equipment_school_year='2026 - 2027',equipment_borrowed_at='2026-11-09',equipment_due_at='2026-11-12',**{'equipment_id[]':'eq','equipment_quantity[]':'1','teaching_grade[]':'8,9','lesson_period[]':'24'})
  assert request(form=payload)[0]==302;first=json.loads(store.read_text())['equipment_loans'][0];assert first['ledger_id'].startswith('eqbook_');assert request(form=payload|{'equipment_borrowed_at':'2026-11-23'})[0]==302;loans=json.loads(store.read_text())['equipment_loans'];assert len(loans)==2 and loans[0]['id']!=loans[1]['id'] and loans[0]['batch_id']==loans[1]['batch_id'],'Second loan appends into same ledger'
  status,html,_=request();assert status==200 and html.count('data-equipment-ledger=')==1 and html.count('data-equipment-loan=')==2
  before=store.read_bytes();status,printed,_=request('/thietbi_phieu.php?ledger='+first['ledger_id']);assert status==200 and '09/11/2026' in printed and '23/11/2026' in printed;assert store.read_bytes()==before
  assert request(form={'action':'return_equipment_loan','csrf':csrf,'id':first['id'],'returned_at':'2026-11-12','return_condition':'Tốt'})[0]==302;loans=json.loads(store.read_text())['equipment_loans'];assert loans[0]['returned_at']=='2026-11-12' and loans[1]['returned_at']=='','Return touches only selected row'
  assert request(form=payload|{'equipment_subject':'Sinh học'})[0]==302;assert request(form=payload|{'equipment_school_year':'2025 - 2026'})[0]==302;status,html,_=request();assert html.count('data-equipment-ledger=')==3
  before=store.read_bytes();assert request(form=payload|{'equipment_quantity[]':'99'})[0]==302;assert store.read_bytes()==before,'Availability guard still prevents over-borrowing'
  assert request(form={'action':'delete_equipment_loan','csrf':csrf,'id':first['id']})[0]==302;assert len(json.loads(store.read_text())['equipment_loans'])==3
  assert request('/thietbi_phieu.php?ledger='+first['ledger_id'],role='none')[0]==403
  assert request(form=payload|{'csrf':'bad'})[0]==403
  print('PASS actual equipment controller HTTP: append same ledger, grouped screen/print, individual return/delete, different subjects/years, availability, CSRF and print permission')
 finally:server.terminate();server.wait()
