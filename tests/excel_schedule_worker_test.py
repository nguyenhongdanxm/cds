"""Execute real export entrypoints only on disposable source/data copies."""
import hashlib,json,os,shutil,subprocess,tempfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PHP=os.environ.get('PHP_BINARY','php')
ENV={k:v for k,v in os.environ.items() if not k.startswith('CDS_')}
with tempfile.TemporaryDirectory(prefix='cds-excel-worker-') as directory:
    base=Path(directory);site=base/'site';private=base/'private';private.mkdir()
    paths=subprocess.check_output(['git','-C',str(ROOT),'ls-files','-z'],text=True).split('\0')
    for rel in paths:
        if not rel or not (ROOT/rel).is_file():continue
        target=site/rel;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(ROOT/rel,target)
    env=dict(ENV,CDS_SETUP_PASSWORD='Synthetic-worker-only-password')
    setup=subprocess.run([PHP,str(site/'tools/setup_school.php'),'--config='+str(private/'instance.json'),'--code=TEST','--name=Trường kiểm thử','--short=Test','--year=2026-2027'],env=env,capture_output=True,text=True)
    assert setup.returncode==0,(setup.stdout,setup.stderr)
    users=site/'data/users.json';before=users.read_bytes()
    source=site/'chuyenmon/data/lesson_book_records.json';source.write_text('[{"signed_at":"keep","snapshot":{"lesson":"unchanged"}}]')
    original=source.read_bytes()
    catalog=json.loads(subprocess.check_output([PHP,'-r',"require 'includes/excel_schedule_catalog.php'; echo json_encode(xlsched_catalog());"],cwd=site,env=ENV,text=True))
    asset=next(key for key,row in catalog.items() if row.get('asset'))
    (site/'data/uploads/vanban').mkdir(parents=True,exist_ok=True)
    (site/'data/uploads/vanban/fixture.pdf').write_bytes(b'%PDF-1.4\noriginal fixture bytes')
    for report in [asset,'pccm','equipment','duty_word','duty_reports_word','original_documents']:
        payload=private/'request.json';payload.write_text(json.dumps({'owner_id':'u1','report_id':report,'params':{'month':'2026-11','from':'2026-11-01','to':('2027-12-31' if report=='original_documents' else '2026-11-01')},'meta':str(private/'meta.json'),'site_host':'fixture.example','https':True}))
        p=subprocess.run([PHP,str(site/'tools/excel_export_worker.php'),str(payload)],env=ENV,capture_output=True)
        assert p.returncode==0,(report,p.stdout[:200],p.stderr[-1000:])
        file=private/'export.bin';file.write_bytes(p.stdout)
        validate=subprocess.run([PHP,'-r',"require 'includes/excel_schedule_runner.php'; xlsched_validate_file($argv[1],$argv[2]);",str(file),catalog[report]['format']],cwd=site,env=ENV,capture_output=True,text=True)
        assert validate.returncode==0,(report,validate.stdout,validate.stderr)
        assert b'cdsPageNavigation' not in p.stdout and b'excel-schedule-links' not in p.stdout
        assert users.read_bytes()==before and source.read_bytes()==original
    if os.environ.get('CDS_SCHEDULE_UI_FIXTURE'):
        script="$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/excel_schedules.php';$_SERVER['HTTP_HOST']='fixture.example';session_start();require 'includes/session_user.php';$u=json_decode(file_get_contents('data/users.json'),true)[0];$_SESSION['cds_user']=cds_session_user_from_record($u);require 'excel_schedules.php';"
        page=subprocess.run([PHP,'-r',script],cwd=site,env=ENV,capture_output=True,text=True)
        assert page.returncode==0 and 'id="scheduleForm"' in page.stdout,(page.stdout[-800:],page.stderr)
        Path(os.environ['CDS_SCHEDULE_UI_FIXTURE']).write_text(page.stdout)
        assert users.read_bytes()==before and source.read_bytes()==original
    revoked=json.loads(before);revoked[0]['active']=False;users.write_text(json.dumps(revoked))
    p=subprocess.run([PHP,str(site/'tools/excel_export_worker.php'),str(payload)],env=ENV,capture_output=True)
    assert p.returncode!=0 and not p.stdout and b'OWNER_NOT_ACTIVE_ADMIN' in p.stderr
print('PASS actual Excel/CSV, duty DOCX, daily Word ZIP and original Word/PDF archive workers: isolated module paths, no HTML injection, source accounts/signed snapshots unchanged, revoked owner blocked')
