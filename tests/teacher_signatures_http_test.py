"""Exercise real multipart upload, registry lookup and image endpoint under both module data roots."""
import base64,json,os,pathlib,shutil,socket,struct,subprocess,tempfile,time,urllib.request,urllib.error,urllib.parse,zlib
root=pathlib.Path(__file__).resolve().parents[1]
def png(w,h):
 def chunk(t,d):return struct.pack('!I',len(d))+t+d+struct.pack('!I',zlib.crc32(t+d)&0xffffffff)
 rows=[]
 for y in range(h):rows.append(b'\x00'+b''.join(b'\x00\x00\x00\xff' if 10<x<w-10 and 10<y<h-10 else b'\xff\xff\xff\xff' for x in range(w)))
 return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('!IIBBBBB',w,h,8,6,0,0,0))+chunk(b'IDAT',zlib.compress(b''.join(rows)))+chunk(b'IEND',b'')
with tempfile.TemporaryDirectory(prefix='teacher-signatures-http-') as temp:
 d=pathlib.Path(temp);(d/'includes').mkdir();(d/'data').mkdir();(d/'chuyenmon/data/lesson_book_signatures').mkdir(parents=True)
 shutil.copy(root/'includes/teacher_signatures.php',d/'includes');shutil.copy(root/'includes/csdl_modal_teacher.php',d/'includes');shutil.copy(root/'teacher_signature.php',d)
 auth="""<?php session_start();define('DATA_PATH',dirname(__DIR__).'/data');define('BASE_URL','/');require_once '%s/includes/json_store.php';function load_json($f,$v=[]){return cds_json_load($f,$v);}function current_user(){return ['id'=>$_COOKIE['who']??'ua','role'=>$_COOKIE['role']??'teacher','teacher_id'=>($_COOKIE['who']??'ua')==='ua'?'a':'b','name'=>($_COOKIE['who']??'ua')==='ua'?'Nguyễn An':'Trần Bình'];}function require_login(){}function can_perm($p){return ($_COOKIE['role']??'teacher')==='admin';}function can_perm_level($p,$l){return can_perm($p);}function get_users(){return [['id'=>'ua','teacher_id'=>'a'],['id'=>'ub','teacher_id'=>'b']];}function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');} """%root.as_posix()
 (d/'includes/auth.php').write_text(auth);(d/'includes/csdl_store.php').write_text("<?php function csdl_teachers_all(){return load_json(DATA_PATH.'/teachers.json',[]);}")
 (d/'data/teachers.json').write_text(json.dumps([{'id':'a','name':'Nguyễn An'},{'id':'b','name':'Trần Bình'}]));(d/'chuyenmon/data/teachers.json').write_text('["Wrong module teacher list"]')
 legacy_img=d/'chuyenmon/data/lesson_book_signatures/legacy.png';legacy_img.write_bytes(png(50,30));(d/'chuyenmon/data/lesson_book_signatures.json').write_text(json.dumps([{'user_id':'ua','teacher':'Nguyễn An','path':str(legacy_img),'version':'old','created_at':'2026-01-01'}]))
 (d/'manage.php').write_text("""<?php require 'includes/auth.php';require 'includes/csdl_store.php';require 'includes/teacher_signatures.php';header('Content-Type: application/json');if(($_POST['csrf']??'')!=='fixture'){http_response_code(403);exit;}if(($_POST['action']??'')==='migrate')echo json_encode(cds_ts_migrate_legacy());elseif(($_POST['action']??'')==='link')echo json_encode(cds_ts_link_legacy((string)($_POST['teacher_id']??''),(string)($_POST['source_key']??''),current_user()));else echo json_encode(cds_ts_upload((string)($_POST['teacher_id']??''),$_FILES['signature']??[],current_user()));""")
 (d/'cm_lookup.php').write_text("<?php define('DATA_PATH',__DIR__.'/chuyenmon/data');require '"+root.as_posix()+"/includes/json_store.php';function load_json($f,$v=[]){return cds_json_load($f,$v);}require 'includes/teacher_signatures.php';header('Content-Type: application/json');echo json_encode(cds_ts_for_user(['teacher_id'=>'a','name'=>'Nguyễn An']));")
 (d/'modal.php').write_text("<?php require 'includes/auth.php';require 'includes/csdl_store.php';require 'includes/teacher_signatures.php';$editing=['id'=>($_GET['teacher']??'a')==='b'?'b':'a','name'=>'Fixture'];$_SESSION['teacher_signature_csrf']='fixture';require 'includes/csdl_modal_teacher.php';")
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 server=subprocess.Popen([os.environ.get('PHP_BINARY','php'),'-d','session.save_path='+temp,'-S',f'127.0.0.1:{port}','-t',str(d)],stdout=subprocess.DEVNULL,stderr=(d/'server.log').open('w'))
 base=f'http://127.0.0.1:{port}'
 def request(path,body=None,headers=None):
  try:
   with urllib.request.urlopen(urllib.request.Request(base+path,data=body,headers=headers or {})) as r:return r.status,r.read(),r.headers
  except urllib.error.HTTPError as r:return r.code,r.read(),r.headers
 def upload(image,tid='a',who='ua',role='teacher',csrf='fixture',filename='signature.png'):
  boundary='TeacherSignatureFixture';parts=[]
  for k,v in {'csrf':csrf,'teacher_id':tid}.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
  parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="signature"; filename="{filename}"\r\nContent-Type: image/png\r\n\r\n'.encode()+image+b'\r\n');parts.append(f'--{boundary}--\r\n'.encode())
  return request('/manage.php',b''.join(parts),{'Content-Type':'multipart/form-data; boundary='+boundary,'Cookie':f'who={who}; role={role}'})
 try:
  for _ in range(60):
   try:status,body,_=request('/cm_lookup.php');break
   except (OSError,urllib.error.URLError):time.sleep(.1)
  assert status==200 and json.loads(body)['version']=='old',body
  assert not (d/'data/teacher_signatures.json').exists(),'Lookup must not write on GET'
  # Reproduce the live teacher modal: CSDL suffix, old account has empty teacher_name.
  (d/'data/teachers.json').write_text(json.dumps([{'id':'a','name':'Nguyễn An (t)'},{'id':'b','name':'Trần Bình'}]))
  assert json.loads(request('/cm_lookup.php')[1])['version']=='old','Legacy source resolves short teacher suffix'

  status,body,_=request('/manage.php',urllib.parse.urlencode({'csrf':'fixture','action':'migrate'}).encode());assert body.startswith(b'{'),body;assert json.loads(body)['count']==1,body
  assert (d/'data/teacher_signatures.json').exists() and not (d/'chuyenmon/data/teacher_signatures.json').exists(),'Always root CSDL registry'
  before=(d/'data/teacher_signatures.json').read_bytes()
  assert not json.loads(upload(b'invalid')[1])['ok']
  assert not json.loads(upload(png(80,40),tid='b')[1])['ok'],'Cannot replace another teacher'
  assert not json.loads(upload(png(80,40),role='viewer')[1])['ok'],'Viewer blocked'
  assert upload(png(80,40),csrf='bad')[0]==403
  assert (d/'data/teacher_signatures.json').read_bytes()==before,'Rejected uploads leave registry untouched'
  status,body,_=upload(png(100,50),filename='hostile.php');result=json.loads(body);assert status==200 and result['ok'],body
  signature=result['signature'];raw=pathlib.Path(signature['path']).read_bytes();assert struct.unpack('!II',raw[16:24])==(480,160),'Normalized PNG canvas'
  assert pathlib.Path(signature['path']).suffix=='.png','Original filename ignored'
  assert json.loads(request('/cm_lookup.php')[1])['version']==signature['version'],'CM resolves updated root signature'
  status,body,headers=request('/teacher_signature.php?id=a');assert status==200 and body==raw and headers.get('Cache-Control')=='private, no-store'
  assert request('/teacher_signature.php?id=a',headers={'Cookie':'who=ub'})[0]==403,'Other teacher cannot download raw image'
  html=request('/modal.php')[1].decode();assert 'name="teacher_signature"' in html and 'teacherSignaturePreview' in html and 'name="signature_csrf"' in html
  legacyfile=d/'chuyenmon/data/lesson_book_signatures.json';sources=json.loads(legacyfile.read_text());sources.append({'user_id':'unresolved','teacher':'Chữ ký chưa ghép <script>','path':str(legacy_img),'version':'unmatched'});legacyfile.write_text(json.dumps(sources))
  missing_html=request('/modal.php?teacher=b')[1].decode();assert 'Chưa ghép được chữ ký' in missing_html and 'name="legacy_signature_key"' in missing_html and 'data-preview="data:image/png;base64,' in missing_html
  assert 'Chữ ký chưa ghép <script>' not in missing_html,'Legacy labels escaped'
  import re
  source_key=re.search(r'<option value="([a-f0-9]{64})" data-preview=',missing_html).group(1)
  form=urllib.parse.urlencode({'csrf':'fixture','action':'link','teacher_id':'b','source_key':source_key}).encode()
  assert not json.loads(request('/manage.php',form)[1])['ok'],'Teacher cannot assign another profile'
  linked=json.loads(request('/manage.php',form,{'Cookie':'role=admin'})[1]);assert linked['ok'],linked
  assert 'teacherSignaturePreview' in request('/modal.php?teacher=b')[1].decode(),'Manual link immediately shows existing image'
  oldpath=signature['path'];again=json.loads(upload(png(140,60))[1]);assert again['ok'] and again['signature']['path']!=oldpath and pathlib.Path(oldpath).read_bytes()==raw,'Replacement is immutable new version'
  print('PASS real signature uploads, file validation, normalized output, root vs CM stores, legacy import, preview/endpoint permissions and immutable replacement')
 except Exception:
  print((d/'server.log').read_text());raise
 finally:server.terminate();server.wait()
