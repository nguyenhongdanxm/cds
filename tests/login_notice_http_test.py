"""Exercise the real login/gate/receipt endpoints against isolated fixture authentication."""
from pathlib import Path
import http.cookiejar, json, os, re, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.parse, urllib.request
ROOT=Path(__file__).resolve().parents[1]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
class Client:
    def __init__(self,base):
        self.base=base;self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
    def request(self,path,data=None,user=None,json_accept=False):
        headers={}
        if user:headers['X-Fixture-User']=user
        if json_accept:headers['Accept']='application/json'
        req=urllib.request.Request(self.base+path,data=urllib.parse.urlencode(data).encode() if data is not None else None,headers=headers)
        try:r=self.opener.open(req)
        except urllib.error.HTTPError as e:r=e
        return r.status,r.headers,r.read().decode()
with tempfile.TemporaryDirectory() as folder:
    tmp=Path(folder);(tmp/'includes').mkdir();(tmp/'data').mkdir()
    for name in ['login.php','login_notice.php']:shutil.copy(ROOT/name,tmp/name)
    for name in ['login_notice_store.php','login_notice_popup.php','login_notice_settings.php']:shutil.copy(ROOT/'includes'/name,tmp/'includes'/name)
    auth=r'''<?php
session_start();define('BASE_URL','/');define('SCHOOL_NAME','Trường kiểm thử');define('DATA_PATH',dirname(__DIR__).'/data');
require __VIEWER__;require __JSON__;
if(isset($_SERVER['HTTP_X_FIXTURE_USER'])){$id=$_SERVER['HTTP_X_FIXTURE_USER'];$_SESSION['cds_user']=['id'=>$id,'username'=>$id,'name'=>$id,'role'=>$id==='admin'?'admin':($id==='viewer'?'viewer':'teacher')];}
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function current_user(){return $_SESSION['cds_user']??null;}
function is_logged_in(){return !empty($_SESSION['cds_user']);}
function require_login(){if(!is_logged_in()){http_response_code(401);exit('Login required');}}
function require_admin(){require_login();if(current_user()['role']!=='admin'){http_response_code(403);exit('Admin only');}}
function flash($s,$t='success'){}
function show_flash(){}
function attempt_login($u,$p){if($p!=='fixture-password')return false;$_SESSION['cds_user']=['id'=>$u,'username'=>$u,'name'=>$u,'role'=>'teacher'];return true;}
if(is_logged_in()&&cds_is_viewer()&&!cds_viewer_request_allowed($_SERVER['REQUEST_METHOD'],basename($_SERVER['SCRIPT_FILENAME']),$_GET)){http_response_code(403);exit('Read only');}
'''
    auth=auth.replace('__VIEWER__',repr(str(ROOT/'includes/viewer_access.php'))).replace('__JSON__',repr(str(ROOT/'includes/json_store.php')))
    (tmp/'includes/auth.php').write_text(auth)
    (tmp/'admin.php').write_text("<?php require __DIR__.'/includes/auth.php';require __DIR__.'/includes/login_notice_settings.php';")
    config={'id':'notice-fixture','enabled':True,'title':'Lịch công tác','body':'<p>📌 Nội dung thông báo</p>','icon':'📢','theme':'blue','starts_at':'','ends_at':'','version':'v1','updated_at':''}
    def config_write(): (tmp/'data/login_notice.json').write_text(json.dumps(config,ensure_ascii=False))
    config_write()
    sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
    with open(tmp/'server.log','wb') as log:
        process=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(tmp)],stdout=log,stderr=log)
        try:
            base=f'http://127.0.0.1:{port}';a=Client(base);b=Client(base)
            for _ in range(100):
                try:a.request('/login_notice.php');break
                except OSError:time.sleep(.05)
            status,headers,body=a.request('/login.php?next=%2Fcsdl.php%3Ftab%3Dstatistics',{'username':'alice','password':'fixture-password'})
            assert status==302 and headers['Location']=='/login_notice.php',(status,body)
            status,_,body=a.request('/login_notice.php');assert status==200 and 'cdsLoginNotice' in body
            def hidden(body,name):
                match=re.search(r'name="'+name+r'" value="([^"]*)"',body);assert match,name;return match.group(1)
            csrf=hidden(body,'csrf');notice=hidden(body,'notice_id')
            status,_,body=a.request('/login_notice.php',{'action':'dismiss','csrf':'bad','notice_id':notice},json_accept=True);assert status==403
            status,_,body=a.request('/login_notice.php',{'action':'dismiss','csrf':csrf,'notice_id':'stale'},json_accept=True);assert status==409
            status,_,body=a.request('/login_notice.php',{'action':'dismiss','csrf':csrf,'notice_id':notice,'context':'gate','user_id':'bob'},json_accept=True)
            result=json.loads(body);assert status==200 and result['ok'] and result['next']=='/csdl.php?tab=statistics',(status,body)
            status,headers,body=a.request('/login_notice.php');assert status==302 and headers['Location']=='/admin.php'
            # A different session for the same account still sees the persisted receipt.
            another=Client(base);status,headers,body=another.request('/login_notice.php',user='alice');assert status==302
            status,_,body=b.request('/login_notice.php',user='bob');assert status==200 and 'cdsLoginNotice' in body
            # Read-only users can close only their own announcement preference.
            viewer=Client(base);status,_,body=viewer.request('/login_notice.php',user='viewer');assert status==200
            status,_,body=viewer.request('/login_notice.php',{'action':'dismiss','csrf':hidden(body,'csrf'),'notice_id':notice},json_accept=True);assert status==200 and json.loads(body)['ok']
            status,_,_=viewer.request('/admin.php?view=login_notice',{},user='viewer');assert status==403
            # Regular accounts cannot edit announcement settings; admin form renders safely.
            status,_,_=b.request('/admin.php?view=login_notice');assert status==403
            admin=Client(base);status,_,body=admin.request('/admin.php?view=login_notice',user='admin');assert status==200 and 'lnSettingsForm' in body and 'lnPreview' in body
            status,_,_=admin.request('/admin.php?view=login_notice',{'csrf':'bad'});assert status==403
            # New publication ID becomes visible again, disabled/expired notices are skipped.
            config['id']='notice-fixture-2';config_write();status,_,body=a.request('/login_notice.php');assert status==200
            config['ends_at']='2020-01-01T00:00:00+07:00';config_write();status,_,_=a.request('/login_notice.php');assert status==302
            config['ends_at']='';config['enabled']=False;config_write();status,_,_=b.request('/login_notice.php');assert status==302
            print('PASS: actual login redirect, deep-link resume, CSRF, stale notice, cross-account isolation, cross-session persistence, viewer receipt, admin access, expiration')
        finally:
            process.terminate();process.wait(timeout=10)
