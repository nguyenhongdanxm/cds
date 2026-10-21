"""Validate the actual endpoint XLSX with synthetic records; stdlib only."""
import io, os, subprocess, zipfile, xml.etree.ElementTree as ET
from pathlib import Path
PHP=os.environ.get('PHP_BINARY','php')
FIXTURE=str(Path(__file__).parent/'support/vanban_number_export_fixture.php')
NS={'s':'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
def run(role='allowed',direction='desc',query='match'):
    return subprocess.check_output([PHP,FIXTURE,role,direction,'xlsx',query])
def read(data):
    z=zipfile.ZipFile(io.BytesIO(data)); assert z.testzip() is None
    for path in z.namelist(): ET.fromstring(z.read(path))
    return z
z=read(run())
wb=ET.fromstring(z.read('xl/workbook.xml'))
assert [x.attrib['name'] for x in wb.findall('s:sheets/s:sheet',NS)]==['Quyết định','Văn bản khác']
assert len(wb.findall('s:definedNames/s:definedName',NS))==4
sheets=[ET.fromstring(z.read('xl/worksheets/sheet%d.xml'%i)) for i in [1,2]]
def texts(sheet,column):
    return [''.join(c.itertext()) for c in sheet.findall('s:sheetData/s:row/s:c',NS) if c.attrib['r'].startswith(column) and int(c.attrib['r'][1:])>=6]
assert texts(sheets[0],'D')==['2/QĐ','1/QĐ']
assert texts(sheets[1],'D')==['1/KH'], 'Selected decision tab must still export the other book to its own sheet'
for s in sheets:
    assert not s.findall('.//s:f',NS), 'User content is never a formula'
    assert s.find('s:pageSetup',NS).attrib=={'paperSize':'9','orientation':'landscape','fitToWidth':'1','fitToHeight':'0'}
    assert s.find('s:sheetViews/s:sheetView/s:pane',NS).attrib['ySplit']=='5'
    assert s.find('s:autoFilter',NS) is not None
    assert s.find('s:pageMargins',NS) is not None
    assert s.find('s:sheetData/s:row/s:c',NS) is not None
styles=ET.fromstring(z.read('xl/styles.xml'))
assert styles.find('s:numFmts/s:numFmt',NS).attrib['formatCode']=='dd/mm/yyyy'
assert len(styles.find('s:cellXfs',NS))==10
first=sheets[0].find("s:sheetData/s:row[@r='6']/s:c[@r='F6']",NS)
second=sheets[0].find("s:sheetData/s:row[@r='7']/s:c[@r='F7']",NS)
assert first.attrib['s']!=second.attrib['s'], 'Alternating fill styles'
assert sheets[0].find("s:sheetData/s:row[@r='6']/s:c[@r='E6']/s:v",NS).text.isdigit(), 'Real Excel date'
zasc=read(run(direction='asc'));assert texts(ET.fromstring(zasc.read('xl/worksheets/sheet1.xml')),'D')==['1/QĐ','2/QĐ']
empty=read(run(query='no match anywhere'))
for i in [1,2]:assert 'Không có văn bản phù hợp.' in empty.read('xl/worksheets/sheet%d.xml'%i).decode()
assert not run(role='denied').startswith(b'PK'), 'Permission enforced for XLSX too'
if os.environ.get('XLSX_QA_PATH'):Path(os.environ['XLSX_QA_PATH']).write_bytes(run())
print('PASS XLSX: valid ZIP/XML, exact two sheets, filters/order, A4 landscape, print areas/titles, zebra rows, numeric dates, freeze/filter and permissions')
