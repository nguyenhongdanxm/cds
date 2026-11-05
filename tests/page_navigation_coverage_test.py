from pathlib import Path
import re
root=Path(__file__).resolve().parent.parent; files=list(root.rglob('*.php')); graph={}
for f in files:
 if '.git' in f.parts:continue
 deps=[]
 for expr in re.findall(r'(?:require|include)(?:_once)?\s*([^;]+);',f.read_text(errors='ignore')):
  for quoted in re.findall(r"['\"]([^'\"]+\.php)['\"]",expr):
   for base in [f.parent,f.parent.parent,root,root/'chuyenmon']:
    target=(base/quoted.lstrip('/')).resolve()
    if target.is_file() and target.is_relative_to(root):deps.append(target)
 graph[f.resolve()]=deps
hooks={root/'includes/config.php',root/'chuyenmon/includes/config.php',root/'includes/page_navigation.php'}
def reaches(f,seen):
 if f in hooks:return True
 if f in seen:return False
 seen.add(f)
 return any(reaches(d,seen) for d in graph.get(f,[]))
entries=[f for f in files if f.parent in [root,root/'chuyenmon'] and re.search(r'<!doctype|<html',f.read_text(errors='ignore'),re.I)]
uncovered=[str(f.relative_to(root)) for f in entries if not reaches(f.resolve(),set())]
assert not uncovered, 'Missing shared navigation hook: '+', '.join(uncovered)
preview=(root/'olympia_san_khau_preview.html').read_text()
assert 'id="cdsPageNavigation"' in preview and 'page-navigation.js' in preview
print('HTML entrypoints',len(entries),'covered',sum(reaches(f.resolve(),set()) for f in entries))
