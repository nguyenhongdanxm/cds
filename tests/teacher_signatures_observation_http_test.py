import base64,http.cookiejar,json,os,pathlib,re,shutil,socket,subprocess,tempfile,time,urllib.request,urllib.error,urllib.parse
root=pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='signature-observation-') as temp:
 d=pathlib.Path(temp);(d/'includes').mkdir();(d/'data/teacher_signature_images').mkdir(parents=True);(d/'chuyenmon/includes').mkdir(parents=True);(d/'chuyenmon/data').mkdir()
 shutil.copy(root/'includes/teacher_signatures.php',d/'includes');shutil.copy(root/'chuyenmon/phieudugio.php',d/'chuyenmon');shutil.copy(root/'chuyenmon/includes/observation_form.php',d/'chuyenmon/includes')
 (d/'data/teachers.json').write_text(json.dumps([{'id':'a','name':'Nguyễn An'},{'id':'b','name':'Trần Bình'}]));p=d/'data/teacher_signature_images/sign.png';p.write_bytes(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5N8AAAAASUVORK5CYII='));(d/'data/teacher_signatures.json').write_text(json.dumps([{'teacher_id':t,'path':str(p),'version':'v1'} for t in ['a','b']]))
 import hashlib
 key=hashlib.sha1('trần bình'.encode()).hexdigest();records=[{'id':'fixture','teacher':'Nguyễn An','observers':['Trần Bình'],'date':'2026-11-24','evaluations':{key:{'completed':True,'scores':[1]*12,'total':12,'rating':'Khá'}}}];store=d/'chuyenmon/data/observations.json';store.write_text(json.dumps(records))
 stub="""<?php session_start();define('BASE_URL','/chuyenmon/');define('DATA_PATH',dirname(__DIR__).'/data');require_once '%s/includes/json_store.php';function load_json($f,$v=[]){return cds_json_load($f,$v);}function save_json($f,$v){return cds_json_save($f,$v);}function require_login(){}function cds_user(){$who=$_COOKIE['who']??'a';return ['id'=>$who,'teacher_id'=>$who,'teacher_name'=>$who==='a'?'Nguyễn An':($who==='b'?'Trần Bình':'Khác'),'role'=>'teacher'];}function cds_user_has_group($u,$g){return false;}function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function flash(...$args){}function cds_form_place(){return '';}function cds_form_department(){return '';}function cds_form_school_name(){return 'Fixture';}function cds_drive_csrf_token(){return 'drive';}function cds_drive_page_action(){return 'fixture';}"""%root.as_posix()
 (d/'chuyenmon/includes/functions.php').write_text(stub);(d/'chuyenmon/includes/header.php').write_text('<!doctype html><html><body>');(d/'chuyenmon/includes/footer.php').write_text('</body></html>')
 with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
 server=subprocess.Popen([os.environ.get('PHP_BINARY','php'),'-d','session.save_path='+temp,'-S',f'127.0.0.1:{port}','-t',temp],stdout=subprocess.DEVNULL,stderr=(d/'log').open('w'))
 class NoRedirect(urllib.request.HTTPRedirectHandler):
  def redirect_request(self,*args):return None
 jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar),NoRedirect())
 def request(who,form=None):
  headers={'Cookie':'; '.join(['who='+who]+[c.name+'='+c.value for c in jar])}
  try:
   with opener.open(urllib.request.Request(f'http://127.0.0.1:{port}/chuyenmon/phieudugio.php?id=fixture',data=None if form is None else urllib.parse.urlencode(form,doseq=True).encode(),headers=headers)) as r:return r.status,r.read().decode()
  except urllib.error.HTTPError as r:return r.code,r.read().decode()
 try:
  for _ in range(60):
   try:status,html=request('a');break
   except (OSError,urllib.error.URLError):time.sleep(.1)
  assert status==200,html;csrf=re.search('name="csrf" value="([^"]+)"',html).group(1)
  assert request('a',dict(csrf=csrf,action='sign_observation',signature_role='teacher'))[0]==302
  assert request('b',dict(csrf=csrf,action='sign_observation',signature_role='observer'))[0]==302
  result=json.loads(store.read_text())[0]['evaluations'][key];assert result['teacher_signature']['teacher_id']=='a' and result['observer_signature']['teacher_id']=='b'
  assert 'alt="Chữ ký người dạy"' in request('a')[1] and 'alt="Chữ ký người dự"' in request('b')[1]
  before=store.read_bytes();assert request('a',dict(csrf=csrf,action='sign_observation',signature_role='observer'))[0]==403;assert store.read_bytes()==before
  assert request('b',dict(csrf='wrong',action='sign_observation',signature_role='observer'))[0]==403
  assert request('b',{'csrf':csrf,'scores[]':[1]*12})[0]==302
  revised=json.loads(store.read_text())[0]['evaluations'][key];assert 'teacher_signature' not in revised and 'observer_signature' not in revised,'Editing evaluation requires fresh signatures'
  print('PASS actual observation controller: own role confirmation, two snapshot images, other-role/CSRF rejection, editing clears previous signatures')
 except Exception:print((d/'log').read_text());raise
 finally:server.terminate();server.wait()
