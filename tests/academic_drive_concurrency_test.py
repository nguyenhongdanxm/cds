import concurrent.futures,http.cookiejar,json,os,pathlib,signal,socket,subprocess,tempfile,time,urllib.request
root=pathlib.Path(__file__).resolve().parents[1];php=os.environ.get('PHP_BINARY','php')
with tempfile.TemporaryDirectory(prefix='academic-concurrency-') as temp:
 d=pathlib.Path(temp);subprocess.run([php,str(root/'tests/academic_drive_fixture.php'),temp],check=True)
 with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
 server=subprocess.Popen([php,'-d','session.save_path='+temp,'-S',f'127.0.0.1:{port}','-t',temp],env=os.environ|{'PHP_CLI_SERVER_WORKERS':'3'},start_new_session=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 base=f'http://127.0.0.1:{port}'
 try:
  jar=http.cookiejar.CookieJar();opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
  for _ in range(60):
   try:opener.open(base+'/hoso.php').read();break
   except OSError:time.sleep(.1)
  cookie='; '.join(c.name+'='+c.value for c in jar)
  def get(slow=False):
   headers={'Cookie':cookie}
   if slow:headers['X-Fixture-Slow']='1'
   start=time.monotonic()
   with urllib.request.urlopen(urllib.request.Request(base+'/hoso.php?api=list',headers=headers),timeout=10) as response:data=json.loads(response.read())
   assert data['ok'];return time.monotonic()-start
  with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
   slow=pool.submit(get,True)
   for _ in range(150):
    if (d/'data/slow-started').exists():break
    time.sleep(.01)
   assert (d/'data/slow-started').exists(),'Delayed remote operation started'
   fast=pool.submit(get,False).result();duration=slow.result()
  assert fast<1.3 and duration>=2,(fast,duration)
  print(f'PASS listing releases PHP session while remote request is pending: concurrent request {fast:.3f}s vs delayed request {duration:.3f}s')
 finally:os.killpg(server.pid,signal.SIGTERM);server.wait()
