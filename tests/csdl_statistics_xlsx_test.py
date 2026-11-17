"""Run the real PHP exporter with fixture dependencies and open its exact download bytes."""
from pathlib import Path
import io, json, subprocess, tempfile, zipfile, xml.etree.ElementTree as ET
from openpyxl import load_workbook
ROOT=Path(__file__).resolve().parents[1]
source=(ROOT/'csdl_statistics_export_xlsx.php').read_text()
fixture=r'''<?php
// Deliberately noisy bootstrap: the exporter must discard this before its ZIP bytes.
echo "\xEF\xBB\xBF\nbootstrap noise\n";
function require_login(){}
function require_perm($p){}
function allowed_classes(){return ['6A','10A'];}
function csdl_teachers_all(){return [['id'=>'t1','active'=>true,'code'=>'001','name'=>'Nguyễn <Lan> & An','cccd'=>'001234567890','phone'=>'0123456789','teaching_level'=>'THCS','specialty'=>'Toán'],['id'=>'t2','active'=>true,'code'=>'002','name'=>'Giáo viên THPT','teaching_level'=>'THPT']];}
function csdl_classes_all(){return [['id'=>'c6','name'=>'6A','grade'=>6],['id'=>'c10','name'=>'10A','grade'=>10],['id'=>'private','name'=>'11B','grade'=>11]];}
function csdl_students_all(){return [['id'=>'s1','active'=>true,'code'=>'0001','name'=>'Học sinh Việt','class_id'=>'c6','boarder'=>true,'gender'=>'Nữ','cccd'=>'000123456789','parent_phone'=>'0987654321'],['id'=>'s2','active'=>true,'code'=>'0002','name'=>'Học sinh THPT','class_id'=>'c10'],['id'=>'secret','active'=>true,'code'=>'SECRET','name'=>'Excluded class','class_id'=>'private']];}
function csdl_io_teacher_flat($r){return $r;}
function csdl_io_student_flat($r,$classes){foreach($classes as $c)if($c['id']===$r['class_id'])$r['class_name']=$c['name'];return $r;}
$_GET=json_decode(getenv('XLSX_TEST_QUERY'),true)?:[];
'''
with tempfile.TemporaryDirectory() as d:
    tmp=Path(d); (tmp/'fixture.php').write_text(fixture)
    # Exercise sheet names containing Excel-forbidden punctuation and long Vietnamese text.
    funcs=source[source.index('function sx_xml'):source.index('$sheets=[];')]
    name_test="<?php\nrequire "+repr(str(ROOT/'includes/csdl_statistics_filters.php'))+";\n"+funcs+"\n$used=[];foreach(['A/B:C?D*E[F]G',str_repeat('Tiếng Việt ',8),\"'Tên sheet'\",'Sheet','sheet'] as $name)echo sx_sheet_name($name,$used),PHP_EOL;"
    (tmp/'names.php').write_text(name_test)
    names=subprocess.run(['php',str(tmp/'names.php')],capture_output=True,check=True)
    assert not names.stderr,names.stderr.decode(errors='replace')
    titles=names.stdout.decode().splitlines()
    assert len(set(t.casefold() for t in titles))==len(titles)
    assert all(len(t)<=31 and not any(c in t for c in '\\/?*[]:') for t in titles)
    code=source
    for name in ['auth.php','csdl_store.php','csdl_io.php']:
        code=code.replace("__DIR__ . '/includes/"+name+"'",repr(str(tmp/'fixture.php')))
    code=code.replace("__DIR__ . '/includes/csdl_statistics_filters.php'",repr(str(ROOT/'includes/csdl_statistics_filters.php')))
    (tmp/'export.php').write_text(code)
    import os
    for query,expected in [({},5),({'stat_scope':'teachers'},3),({'stat_scope':'students'},3),({'stat_level':'THPT'},5),({'stat_q':'no-matches'},5)]:
        proc=subprocess.run(['php',str(tmp/'export.php')],env={**os.environ,'XLSX_TEST_QUERY':json.dumps(query)},capture_output=True,check=True)
        data=proc.stdout
        assert data.startswith(b'PK\x03\x04'), (data[:200],proc.stderr)
        assert not proc.stderr, proc.stderr.decode(errors='replace')
        with zipfile.ZipFile(io.BytesIO(data)) as z:
            assert z.testzip() is None
            for name in z.namelist():
                if name.endswith(('.xml','.rels')): ET.fromstring(z.read(name))
        book=load_workbook(io.BytesIO(data),data_only=False)
        assert len(book.sheetnames)==expected,book.sheetnames
        assert len({s.casefold() for s in book.sheetnames})==expected
        assert all(len(s)<=31 and not any(c in s for c in '\\/?*[]:') for s in book.sheetnames)
        if not query:
            assert book['Chi tiết giáo viên']['B2'].value=='001'
            assert book['Chi tiết giáo viên']['C2'].value=='Nguyễn <Lan> & An'
            assert book['Chi tiết giáo viên']['D2'].value=='001234567890'
            assert book['Chi tiết giáo viên']['G2'].value=='0123456789'
            assert book['Chi tiết học sinh']['B2'].value=='0001'
            assert book['Chi tiết học sinh'].max_row==3, 'Class permissions were lost'
        if query.get('stat_level')=='THPT':
            assert book['Chi tiết giáo viên'].max_row==2
            assert book['Chi tiết học sinh']['E2'].value=='10A'
        if query.get('stat_q')=='no-matches':
            assert book['Chi tiết giáo viên'].max_row==1
            assert book['Chi tiết học sinh'].max_row==1
        print('PASS',query or 'all',len(data),'bytes; ZIP, XML and openpyxl load')
