"""Fresh-school isolation and source-only deployment against disposable fixtures."""
import hashlib
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import zipfile
import socket
import time
from urllib.request import urlopen
from urllib.error import HTTPError

REPO = Path(__file__).resolve().parents[1]
PHP = os.environ.get('PHP_BINARY', 'php')
ENV = {k: v for k, v in os.environ.items() if not k.startswith('CDS_')}
PASSWORD = 'Synthetic-setup-only-2026!'

def command(args, *, env=None, ok=True, cwd=None):
    p = subprocess.run(args, cwd=cwd, env=env or ENV, capture_output=True, text=True)
    if ok and p.returncode:
        raise AssertionError(f'{args[0]} failed: {p.stdout[-1500:]} {p.stderr[-1500:]}')
    return p

def php(root, code, *, ok=True, env=None):
    sessions = root.parent / 'sessions'
    sessions.mkdir(exist_ok=True)
    code = 'ini_set("session.save_path", ' + json.dumps(str(sessions)) + ');' + code
    return command([PHP, '-r', code], cwd=root, ok=ok, env=env)

def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

with tempfile.TemporaryDirectory(prefix='cds-schools-') as scratch:
    tmp = Path(scratch)
    paths = command(['git', '-C', str(REPO), 'ls-files', '-z']).stdout.split('\0')
    paths = [p for p in paths if p]
    # Copy tracked source only; no operating-school data/config/credentials.
    schools = [tmp / 'school-a', tmp / 'school-b']
    for school in schools:
        for rel in paths:
            source = REPO / rel
            if not source.is_file() or source.is_symlink():
                continue
            dest = school / rel
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.copyfile(source, dest)
    defaults = [php(s, "require 'includes/instance_config.php'; echo cds_instance_config_path();").stdout for s in schools]
    assert defaults[0] != defaults[1], 'fresh sibling installs must not share config'
    assert php(schools[0], "require 'includes/school_config.php'; require 'chuyenmon/includes/seed_defaults.php'; echo json_encode(array_map('count',cds_load_chuyenmon_seed_defaults()));").stdout == '{"teachers":0,"classes":0,"groups":0,"subjects":0,"roles":0}'
    assert not (schools[0] / 'data/users.json').exists()
    anonymous = php(schools[0], "define('CDS_AUTH_LOGIN_ONLY',true); define('CDS_SKIP_DRIVE_ACTION_REGISTRATION',true); require 'includes/auth.php'; echo is_file(USERS_FILE)?'BAD':'NO_ACCOUNT';")
    assert anonymous.stdout == 'NO_ACCOUNT', repr(anonymous.stdout + anonymous.stderr)
    # Config must be outside web root; invalid setup must not create an account.
    invalid = command([PHP, str(schools[0]/'tools/setup_school.php'), '--config='+str(schools[0]/'private/instance.json'), '--code=A', '--name=Bad', '--short=Bad', '--year=2026-2027'], ok=False)
    assert invalid.returncode and not (schools[0]/'data/users.json').exists()
    for i, school in enumerate(schools):
        label = ['Trường A <&> "Thử"', 'Trường B độc lập'][i]
        cfg = tmp / ('private-'+str(i)) / 'instance.json'
        env = dict(ENV, CDS_SETUP_PASSWORD=PASSWORD)
        args = [PHP, str(school/'tools/setup_school.php'), '--config='+str(cfg), '--code=SCHOOL'+str(i), '--name='+label, '--short=S'+str(i), '--year=2026-2027', '--department=Cơ quan '+str(i), '--place=Địa danh '+str(i)]
        out = command(args, env=env)
        assert PASSWORD not in out.stdout + out.stderr, 'chosen password must never be echoed'
        assert 'CDS_SCHOOL_SETUP_OK' in out.stdout
        data = json.loads(cfg.read_text())
        assert data['deployment']['profile'] == 'school'
        assert data['school']['chuyenmon']['seed_profile'] == 'empty'
        users_file = school/'data/users.json'
        users = json.loads(users_file.read_text())
        assert len(users) == 1 and users[0]['role'] == 'admin' and 'password' not in users[0]
        before = {p: digest(p) for p in [cfg, users_file, school/'includes/instance_binding.php']}
        repeat = command(args, env=env, ok=False)
        assert repeat.returncode and before == {p:digest(p) for p in before}, 'setup must not overwrite existing data'
        profile = json.loads(php(school, "require 'includes/school_config.php'; echo json_encode([school_name(),cds_form_department(),cds_form_place(),cds_instance_legacy_install()]);").stdout)
        assert profile == [label, 'Cơ quan '+str(i), 'Địa danh '+str(i), False]
        login = php(school, "define('CDS_AUTH_LOGIN_ONLY',true); define('CDS_SKIP_DRIVE_ACTION_REGISTRATION',true); require 'includes/auth.php'; echo attempt_login('admin',getenv('CDS_SETUP_PASSWORD'))?'LOGIN_OK':'BAD';", env=env)
        assert login.stdout == 'LOGIN_OK', login.stdout
        assert digest(users_file) == before[users_file], 'login must not rewrite account records'
        xml = php(school, "require 'includes/noitru_meal_month_export.php'; echo nt_xlsx_sheet_xml('6A',[],'2026-11','breakfast','2026-2027','',['off'=>[],'maps'=>[],'reported'=>[]]);").stdout
        assert 'Xín Mần' not in xml and 'Địa danh '+str(i) in xml
        assert ('Trường A &lt;&amp;&gt; &quot;Thử&quot;' if i == 0 else label) in xml, 'XLSX uses escaped school branding'
        meta = php(school, "require 'chuyenmon/includes/lesson_book_excel.php'; $files=lb_xlsx_base_files('Title','Sheet1','<worksheet/>','<styleSheet/>'); echo $files['docProps/core.xml'];").stdout
        assert 'Xín Mần' not in meta and ('Trường A &lt;&amp;&gt;' if i == 0 else label) in meta
        js = php(school, "require 'includes/form_profile.php'; echo cds_form_js('</script><script>alert(1)</script>');").stdout
        assert '</script>' not in js and json.loads(js) == '</script><script>alert(1)</script>'
        assert (users_file.stat().st_mode & 0o777) == 0o600
        assert (cfg.stat().st_mode & 0o777) == 0o600
        # Explicit broken binding fails closed, never falls back to the sibling's config.
        backup = cfg.read_bytes()
        cfg.write_text('{broken')
        assert php(school, "require 'includes/school_config.php'; echo school_name();", ok=False).returncode
        again = php(school, "require 'includes/instance_config.php'; $caught=0; for($i=0;$i<2;$i++){try{cds_instance_config();}catch(Throwable $e){$caught++;}} echo $caught;")
        assert again.stdout=='2', 'invalid configuration must fail closed on repeated reads'
        cfg.write_bytes(backup)
    # A/B branding and database settings remain independent after edits.
    cfg_b = tmp/'private-1/instance.json'
    before_b = digest(cfg_b)
    cfg_a = tmp/'private-0/instance.json'
    a = json.loads(cfg_a.read_text())
    a['school']['forms'].update({'school_name':'Tên trên phiếu A','department':'Cơ quan phiếu A','footer':'Ghi chú A'})
    cfg_a.write_text(json.dumps(a,ensure_ascii=False))
    assert php(schools[0], "require 'includes/form_profile.php'; echo cds_form_school_name().'|'.cds_form_department().'|'.cds_form_footer();").stdout == 'Tên trên phiếu A|Cơ quan phiếu A|Ghi chú A'
    assert digest(cfg_b) == before_b
    # Preserve current-school compatibility without rewriting any source data.
    legacy = tmp/'legacy';(legacy/'includes').mkdir(parents=True);(legacy/'data').mkdir()
    for name in ['instance_config.php','school_config.php','form_profile.php']:
        shutil.copyfile(REPO/'includes'/name, legacy/'includes'/name)
    (legacy/'data/users.json').write_text('[]')
    assert php(legacy,"require 'includes/school_config.php'; echo school_name().'|'.cds_form_place();").stdout == 'Trường PTDTNT THCS&THPT Xín Mần|Pà Vầy Sủ'
    assert (legacy/'data/users.json').read_text() == '[]'
    uploads=schools[0]/'data/uploads'
    uploads.mkdir()
    (uploads/'sample.pdf').write_bytes(b'%PDF-1.4 synthetic')
    (uploads/'secrets.json').write_text('{"private":true}')
    (uploads/'linked.pdf').symlink_to(tmp/'private-1/instance.json')
    resolver = php(schools[0], "require 'includes/local_download.php'; echo json_encode([cds_local_upload_path('data','uploads/sample.pdf')!=='',cds_local_upload_path('data','../private-1/instance.json'),cds_local_upload_path('data','uploads/secrets.json'),cds_local_upload_path('data','uploads/linked.pdf')]);").stdout
    assert json.loads(resolver)==[True,'','',''], 'private file resolver rejects traversal, stores and symlinks'
    # Actual media endpoint: range/HEAD support and no arbitrary store access.
    media=schools[0]/'data/game_assets/wheel_music'
    media.mkdir(parents=True)
    (media/'fixture.mp3').write_bytes(b'0123456789')
    with socket.socket() as probe:
        probe.bind(('127.0.0.1',0))
        port=probe.getsockname()[1]
    server=subprocess.Popen([PHP,'-d','session.save_path='+str(tmp/'sessions'),'-S','127.0.0.1:'+str(port),'-t',str(schools[0])],env=ENV,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    origin='http://127.0.0.1:'+str(port)
    from urllib.request import Request
    try:
        for attempt in range(40):
            try:
                with urlopen(origin+'/game_asset.php?group=wheel_music&name=fixture.mp3',timeout=2) as response:
                    assert response.read()==b'0123456789'
                    assert response.headers['Content-Type']=='audio/mpeg'
                break
            except (OSError,HTTPError):
                if attempt==39: raise
                time.sleep(.05)
        with urlopen(Request(origin+'/game_asset.php?group=wheel_music&name=fixture.mp3',headers={'Range':'bytes=2-5'}),timeout=2) as response:
            assert response.status==206 and response.read()==b'2345'
        with urlopen(Request(origin+'/game_asset.php?group=wheel_music&name=fixture.mp3',method='HEAD'),timeout=2) as response:
            assert response.read()==b'' and response.headers['Content-Length']=='10'
        for query in ['group=wheel_music&name=../users.json','group=other&name=fixture.mp3','group=wheel_music&name=users.json']:
            try: urlopen(origin+'/game_asset.php?'+query,timeout=2); raise AssertionError('unsafe media request accepted')
            except HTTPError as error: assert error.code==404
        # First admin login goes directly to configuration, even with an admin-page next URL.
        from http.cookiejar import CookieJar
        from urllib.request import build_opener, HTTPCookieProcessor
        from urllib.parse import urlencode
        browser=build_opener(HTTPCookieProcessor(CookieJar()))
        with browser.open(Request(origin+'/login.php?next=/admin.php', data=urlencode({'username':'admin','password':PASSWORD}).encode()),timeout=10) as response:
            page=response.read().decode()
            assert response.geturl()==origin+'/instance_settings.php', response.geturl()
            assert 'Biểu mẫu, bản in và file xuất' in page and 'Tên trên phiếu A' in page
            assert 'form_place' in page and 'form_signer_name' in page
    finally:
        server.terminate();server.wait(timeout=5)
    # Build a real Git release from school A; dirty runtime files stay out.
    source=schools[0]
    command(['git','init','-q'],cwd=source)
    command(['git','add','.'],cwd=source)
    command(['git','-c','user.name=Fixture','-c','user.email=fixture@example.invalid','commit','-qm','Synthetic source'],cwd=source)
    listing=php(source,"require 'includes/release_manifest.php'; echo json_encode(cds_release_manifest(getcwd()));").stdout
    allowed=json.loads(listing)
    assert 'includes/instance_binding.php' not in allowed and not any(p.startswith(('data/','chuyenmon/data/')) for p in allowed)
    assert not any(p.startswith(('tests/','.github/')) for p in allowed), 'release excludes executable test fixtures and CI files'
    assert '.htaccess' in allowed and 'includes/form_profile.php' in allowed
    target=tmp/'hosting';(target/'data').mkdir(parents=True);(target/'includes').mkdir()
    (target/'.htaccess').write_text('# Hosting-specific rules\nOptions -Indexes\n')
    sentinels={'data/users.json':'existing accounts','includes/instance_binding.php':'<?php return "/private/other-school.json";','includes/database.conf':'private database'}
    for rel,body in sentinels.items():(target/rel).write_text(body)
    env=dict(ENV,CDS_DEPLOY_PATH=str(target))
    dry=command([PHP,str(source/'tools/deploy_cds.php'),'--dry-run'],env=env)
    assert 'CDS_DEPLOY_PLAN' in dry.stdout and not (target/'admin.php').exists()
    deployed=command([PHP,str(source/'tools/deploy_cds.php')],env=env)
    assert 'CDS_DEPLOY_OK' in deployed.stdout
    assert (target/'includes/form_profile.php').is_file()
    apache=(target/'.htaccess').read_text()
    assert 'Options -Indexes' in apache and apache.count('# BEGIN CDS PRIVATE DATA')==1
    command([PHP,str(source/'tools/deploy_cds.php')],env=env)
    assert (target/'.htaccess').read_text().count('# BEGIN CDS PRIVATE DATA')==1
    assert '?group=$1&name=$2' in (target/'.htaccess').read_text(), 'Apache rewrite backreferences must survive repeated deployments'
    for rel,body in sentinels.items():assert (target/rel).read_text()==body, rel+' changed'
    # The new-source account and SQL markers were never copied into the target.
    assert (target/'data/users.json').read_text()!=''+(source/'data/users.json').read_text()
    if php(source,"echo class_exists('ZipArchive')?'yes':'no';").stdout=='yes':
        archive=tmp/'cds-release.zip'
        command([PHP,str(source/'tools/build_school_release.php'),'--output='+str(archive)])
        with zipfile.ZipFile(archive) as z:
            names=z.namelist(); manifest=json.loads(z.read('release-manifest.json'))
            assert 'data/users.json' not in names and 'includes/instance_binding.php' not in names
            assert 'tools/setup_school.php' in names
            for rel,sha in manifest['files'].items():assert hashlib.sha256(z.read(rel)).hexdigest()==sha
        print('PASS release ZIP: source only and all file checksums match')
    else:
        print('SKIP ZIP construction: local PHP zip extension unavailable; CI requires zip')
print('PASS two fresh schools: setup, isolation, login, unchanged accounts, escaped XLSX/JS branding, legacy compatibility, source manifest, dry-run and runtime-preserving deployment')
