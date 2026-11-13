"""Execute real export entrypoints only on disposable source/data copies."""
import hashlib,json,os,shutil,subprocess,tempfile,datetime,zipfile,io,base64
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
    # A deterministic synthetic signature image (no real student/teacher data).
    signature=site/'chuyenmon/data/test-signature.png'
    signature.write_bytes(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='))
    reviews=site/'chuyenmon/data/lesson_book_weekly_reviews.json'
    reviews.write_text(json.dumps([{'id':'fixture-review','school_year_key':year['id'],'week_key':'1','class':'6A','type':'homeroom','content':'Nhận xét tuần đã lưu của GVCN'}],ensure_ascii=False))
    signed=[]
    for class_name,title in [('6A','Bài lớp sáu'),('7B','Bài lớp bảy')]:
        signed.append({'slot_id':'fixture-'+class_name,'school_year_key':year['id'],'week_key':'1','week_label':'Tuần 1','date':monday.isoformat(),'day':2,'period':1,'session':'Sáng','class':class_name,'subject':'Toán','actual_teacher':'Giáo viên thử','scheduled_teacher':'Giáo viên thử','book_type':'main','manual_slot':True,'signature_path':str(signature),'ppct_period':1,'signed_at':monday.isoformat()+'T10:00:00+07:00','signed_snapshot':{'lesson_title':title,'teacher_comment':'Nhận xét đã ký '+class_name,'rating':'Tốt'}})
    source.write_text(json.dumps(signed,ensure_ascii=False));signed_before=source.read_bytes()
    for class_name,title,other in [('6A','Bài lớp sáu','Bài lớp bảy'),('7B','Bài lớp bảy','Bài lớp sáu')]:
        payload.write_text(json.dumps({'owner_id':'u1','report_id':'lb_weekly','params':{'week':'1','class':class_name,'school_year':year['id']},'meta':str(private/'meta.json'),'site_host':'fixture.example','https':True}))
        p=subprocess.run([PHP,str(site/'tools/excel_export_worker.php'),str(payload)],env=ENV,capture_output=True)
        assert p.returncode==0,(class_name,p.stdout[:200],p.stderr[-2000:])
        text=p.stdout.decode()
        assert 'data-cds-lesson-print="1"' in text and title in text and other not in text
        assert 'Nhận xét đã ký '+class_name in text and '@page{size:A3 landscape' in text
        if os.environ.get('CDS_PDF_PRINT_FIXTURE') and class_name=='6A':
            Path(os.environ['CDS_PDF_PRINT_FIXTURE']).write_text(text)
        assert users.read_bytes()==before and source.read_bytes()==signed_before
    if os.environ.get('CDS_TEST_PDF_RENDER')=='1':
        config=json.loads((private/'instance.json').read_text())
        config['pdf']={'node_binary':shutil.which('node'),'node_modules':str(ROOT/'tools/lesson-book-pdf/node_modules'),'no_sandbox':True,'chromium_path':os.environ.get('CDS_PDF_CHROMIUM','')}
        (private/'instance.json').write_text(json.dumps(config))
        work=private/'pdf-work';work.mkdir(mode=0o700)
        php_render="require 'includes/instance_config.php'; require 'includes/excel_schedule_runner.php'; $p=json_decode(file_get_contents($argv[1]),true); $f=xlsched_export(['owner_id'=>'u1','site_host'=>'fixture.example','https'=>1],xlsched_report('lb_weekly'),$p,$argv[2],90);readfile($f);unlink($f);"
        for paper,dimensions in [('a3',(1191,842)),('a4_pair',(595,842))]:
            params=private/'pdf-params.json';params.write_text(json.dumps({'week':'1','class':'6A','school_year':year['id'],'paper':paper,'include_cover':'1'}))
            exported=subprocess.run([PHP,'-r',php_render,str(params),str(work)],cwd=site,env=ENV,capture_output=True)
            assert exported.returncode==0 and exported.stdout.startswith(b'%PDF-'),(exported.stdout[:200],exported.stderr[-2000:])
            pdf=private/(paper+'.pdf');pdf.write_bytes(exported.stdout)
            info=subprocess.check_output(['pdfinfo',str(pdf)],text=True)
            import re
            size=re.search(r'Page size:\s+([\d.]+) x ([\d.]+)',info)
            assert size and all(abs(float(v)-wanted)<2 for v,wanted in zip(size.groups(),dimensions)),info
            text=subprocess.check_output(['pdftotext','-layout',str(pdf),'-'],text=True)
            assert 'Bài lớp sáu' in text and 'Bài lớp bảy' not in text and 'Nhận xét đã ký 6A' in text
            assert 'Nhận xét tuần đã lưu của GVCN' in text and 'In / Lưu PDF' not in text
            images=subprocess.check_output(['pdfimages','-list',str(pdf)],text=True)
            assert len(images.splitlines())>2,'Signature must be embedded in rendered PDF'
            if os.environ.get('CDS_PDF_OUTPUT_FIXTURE') and paper=='a3':
                Path(os.environ['CDS_PDF_OUTPUT_FIXTURE']).write_bytes(exported.stdout)
            assert not list(work.iterdir()),'Render temporary HTML/PDF/session files must be cleaned'
            assert source.read_bytes()==signed_before and users.read_bytes()==before
        # Empty scope is a failed export, not a PDF carrying an error message.
        params.write_text(json.dumps({'week':'1','class':'NO-SUCH-CLASS','school_year':year['id']}))
        failed=subprocess.run([PHP,'-r',php_render,str(params),str(work)],cwd=site,env=ENV,capture_output=True)
        assert failed.returncode!=0 and not failed.stdout.startswith(b'%PDF-') and not list(work.iterdir())
        print('PASS real PHP → print route → local Chromium → PDF: A3/A4 dimensions, signed content, signature images, weekly reviews, no toolbar, empty scope blocked, cleanup and unchanged source')
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
print('PASS actual per-class weekly lesson-book print HTML (signed contents/year guard), Excel/CSV, duty DOCX, daily Word ZIP and original Word/PDF archive workers: isolated module paths, no HTML injection, source accounts/signed snapshots unchanged, revoked owner blocked')
