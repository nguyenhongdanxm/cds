"""Execute real export entrypoints only on disposable source/data copies."""
import hashlib,json,os,shutil,subprocess,tempfile,datetime,zipfile,io
from zoneinfo import ZoneInfo
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
    # The exact lesson-book worker must export separate XLSX files, including saved signatures,
    # while leaving the signed source byte-for-byte unchanged.
    today=datetime.datetime.now(ZoneInfo('Asia/Ho_Chi_Minh')).date()
    monday=today-datetime.timedelta(days=today.weekday())
    year={'id':'weekly-fixture-year','label':'Năm học kiểm thử','is_current':True,'start':monday.isoformat(),'end':(monday+datetime.timedelta(days=6)).isoformat()}
    (site/'data/school_years.json').write_text(json.dumps([year]))
    (site/'data/classes.json').write_text(json.dumps([{'id':'fixture-6a','name':'6A','active':True},{'id':'fixture-7b','name':'7B','active':True}]))
    signed=[]
    for class_name,title in [('6A','Bài lớp sáu'),('7B','Bài lớp bảy')]:
        signed.append({'slot_id':'fixture-'+class_name,'school_year_key':year['id'],'week_key':'1','week_label':'Tuần 1','date':monday.isoformat(),'day':2,'period':1,'session':'Sáng','class':class_name,'subject':'Toán','actual_teacher':'Giáo viên thử','scheduled_teacher':'Giáo viên thử','book_type':'main','manual_slot':True,'ppct_period':1,'signed_at':monday.isoformat()+'T10:00:00+07:00','signed_snapshot':{'lesson_title':title,'teacher_comment':'Nhận xét đã ký '+class_name,'rating':'Tốt'}})
    source.write_text(json.dumps(signed,ensure_ascii=False));signed_before=source.read_bytes()
    for class_name,title,other in [('6A','Bài lớp sáu','Bài lớp bảy'),('7B','Bài lớp bảy','Bài lớp sáu')]:
        payload.write_text(json.dumps({'owner_id':'u1','report_id':'lb_weekly','params':{'week':'1','class':class_name,'school_year':year['id']},'meta':str(private/'meta.json'),'site_host':'fixture.example','https':True}))
        p=subprocess.run([PHP,str(site/'tools/excel_export_worker.php'),str(payload)],env=ENV,capture_output=True)
        assert p.returncode==0,(class_name,p.stdout[:200],p.stderr[-2000:])
        with zipfile.ZipFile(io.BytesIO(p.stdout)) as workbook:
            text=''.join(workbook.read(name).decode() for name in workbook.namelist() if name.endswith('.xml'))
            assert title in text and other not in text,(class_name,text[-1000:])
            assert 'Nhận xét đã ký '+class_name in text
        assert users.read_bytes()==before and source.read_bytes()==signed_before
    original=signed_before
    # Reject a stale year before the source exporter can silently fall back to another school week.
    bad=json.loads(payload.read_text());bad['params']['school_year']='old-year';payload.write_text(json.dumps(bad))
    rejected=subprocess.run([PHP,str(site/'tools/excel_export_worker.php'),str(payload)],env=ENV,capture_output=True)
    assert rejected.returncode!=0 and not rejected.stdout.startswith(b'PK')
    if os.environ.get('CDS_SCHEDULE_UI_FIXTURE'):
        script="$_SERVER['REQUEST_METHOD']='GET';$_SERVER['SCRIPT_NAME']='/excel_schedules.php';$_SERVER['HTTP_HOST']='fixture.example';session_start();require 'includes/session_user.php';$u=json_decode(file_get_contents('data/users.json'),true)[0];$_SESSION['cds_user']=cds_session_user_from_record($u);require 'excel_schedules.php';"
        page=subprocess.run([PHP,'-r',script],cwd=site,env=ENV,capture_output=True,text=True)
        assert page.returncode==0 and 'id="scheduleForm"' in page.stdout,(page.stdout[-800:],page.stderr)
        Path(os.environ['CDS_SCHEDULE_UI_FIXTURE']).write_text(page.stdout)
        assert users.read_bytes()==before and source.read_bytes()==original
    revoked=json.loads(before);revoked[0]['active']=False;users.write_text(json.dumps(revoked))
    p=subprocess.run([PHP,str(site/'tools/excel_export_worker.php'),str(payload)],env=ENV,capture_output=True)
    assert p.returncode!=0 and not p.stdout and b'OWNER_NOT_ACTIVE_ADMIN' in p.stderr
print('PASS actual per-class weekly lesson-book XLSX (signed contents/year guard), Excel/CSV, duty DOCX, daily Word ZIP and original Word/PDF archive workers: isolated module paths, no HTML injection, source accounts/signed snapshots unchanged, revoked owner blocked')
