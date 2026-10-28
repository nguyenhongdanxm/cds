<?php
// Disposable fixtures only: never bootstrap the application or its real data.
require_once __DIR__.'/../includes/library_public_catalog.php';
function check($ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$data=['books'=>[], 'loans'=>[
 ['book_id'=>'b0','quantity'=>2,'status'=>'approved','borrower_name'=>'PRIVATE_STUDENT'],
 ['book_id'=>'b0','quantity'=>1,'status'=>'borrowed'],
 ['book_id'=>'b0','quantity'=>99,'status'=>'pending'],
 ['book_id'=>'b0','quantity'=>99,'status'=>'rejected'],
 ['book_id'=>'b0','quantity'=>99,'returned_at'=>'2026-10-01'],
 ['book_id'=>'b1','quantity'=>1],
], 'equipment'=>[['name'=>'PRIVATE_EQUIPMENT']]];
for($i=0;$i<65;$i++)$data['books'][]=['id'=>'b'.$i,'title'=>$i===0?'Toán <script>alert(1)</script>':'Sách '.$i,'code'=>'CODE'.$i,'barcode'=>'000'.($i+1),'category'=>$i%2?'Sách truyện':'Sách giáo khoa','subject'=>'Toán','grade'=>$i%2?'7':'6','publisher'=>'Giáo dục','publication_year'=>'2026','shelf'=>'Kệ A','quantity'=>5,'price'=>10000,'note'=>'PRIVATE_NOTE','source'=>'PRIVATE_SOURCE','private_custom'=>'PRIVATE_CUSTOM'];
$before=serialize($data);$result=lib_public_catalog($data,[]);
check($result['rows'][0]['available']===2,'Existing availability rules: only active approved/borrowed');
check($result['rows'][1]['available']===4,'Legacy status defaults approved');
check($result['count']===65&&count($result['rows'])===30&&$result['pages']===3,'Bounded page size');
check(count(lib_public_catalog($data,['page'=>'999'])['rows'])===5,'Clamp last page');
check(lib_public_catalog($data,['page'=>'-9'])['page']===1,'Clamp first page');
check(lib_public_catalog($data,['q'=>'00065'])['rows'][0]['barcode']==='00065','ISBN retains leading zeros');
check(lib_public_catalog($data,['q'=>'TOÁN','category'=>'Sách giáo khoa','grade'=>'6'])['count']===33,'Unicode case and combined filters');
check(lib_public_catalog($data,['q'=>'no-match'])['count']===0,'Empty result');
check(!str_contains(json_encode($result),'PRIVATE_'),'Explicit public field projection');
check(serialize($data)===$before,'No data mutation');
check(lib_public_catalog_request('GET',['tab'=>'library_books','q'=>'abc','page'=>'2']),'Public read route');
foreach(['POST','PUT','DELETE','HEAD']as$m)check(!lib_public_catalog_request($m,['tab'=>'library_books']),'Only GET');
foreach(['library_qr','book_export','action','confirm_import','manual_code','user_id']as$key)check(!lib_public_catalog_request('GET',['tab'=>'library_books',$key=>'x']),'No alternate actions '.$key);
check(!lib_public_catalog_request('GET',['tab'=>'library_loans']),'Other tabs protected');
check(!lib_public_catalog_request('GET',['tab'=>'library_books','q'=>['x']]),'Array inputs rejected');

$tmp=sys_get_temp_dir().'/library_public_'.bin2hex(random_bytes(6));mkdir($tmp);mkdir($tmp.'/includes');mkdir($tmp.'/data');
try {
 foreach(['thuvien.php','includes/library_public_catalog.php','includes/library_public_catalog_view.php']as$p)copy(__DIR__.'/../'.$p,$tmp.'/'.$p);
 file_put_contents($tmp.'/data/library_equipment.json',json_encode($data,JSON_UNESCAPED_UNICODE));
 $hash=hash_file('sha256',$tmp.'/data/library_equipment.json');
 file_put_contents($tmp.'/includes/auth.php', <<<'PHP'
<?php
define('DATA_PATH',dirname(__DIR__).'/data');define('BASE_URL','/');define('SCHOOL_NAME','Trường thử <script>');
function current_user(){return !empty($GLOBALS['signed_in'])?['username'=>'teacher']:null;}
function require_login(){exit(current_user()?'SIGNED_IN_MANAGEMENT':'LOGIN_REQUIRED');}
function load_json($path,$default){if($path!==DATA_PATH.'/library_equipment.json')throw new RuntimeException('Unexpected read');return json_decode(file_get_contents($path),true);}
function save_json(){throw new RuntimeException('Public route must never write');}
PHP);
 foreach(['csdl_store.php','library_book_excel.php','library_equipment_excel.php']as$p)file_put_contents($tmp.'/includes/'.$p,'<?php ');
 file_put_contents($tmp.'/worker.php', '<?php $_SERVER["REQUEST_METHOD"]=$argv[1];$_GET=json_decode($argv[2],true);$_POST=["action"=>"delete_books_bulk","book_ids"=>["b0"]];$GLOBALS["signed_in"]=($argv[3]??"")==="yes";require __DIR__."/thuvien.php";');
 $render=static function(string $method,array $query,bool $signed=false)use($tmp):string{
  $proc=proc_open([PHP_BINARY,$tmp.'/worker.php',$method,json_encode($query),$signed?'yes':'no'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0,'Route render '.$err);return $out;
 };
 $html=$render('GET',['tab'=>'library_books']);
 check(str_contains($html,'Tra cứu sách thư viện')&&!str_contains($html,'LOGIN_REQUIRED'),'Actual route anonymous catalogue');
 check(str_contains($html,'Toán &lt;script&gt;alert(1)&lt;/script&gt;')&&!str_contains($html,'<script>'),'Stored XSS escaped');
 check(!str_contains($html,'PRIVATE_'),'No private metadata, equipment or borrower names');
 check(!str_contains($html,'method="post"')&&!str_contains($html,'delete_books')&&!str_contains($html,'csrf'),'No management forms or credentials');
 $dom=new DOMDocument();@$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);$xp=new DOMXPath($dom);
 check($xp->query('//tbody/tr')->length===30,'Rendered pagination bounded');
 $next=$xp->query('//a[@rel="next"]')->item(0);parse_str(parse_url($next->getAttribute('href'),PHP_URL_QUERY),$params);check($params['page']==='2'&&$params['tab']==='library_books','Pagination preserves public route');
 check(str_contains($render('GET',['tab'=>'library_books','q'=>'no-match']),'Không tìm thấy sách phù hợp'),'Rendered empty state');
 foreach(['POST','PUT','DELETE']as$m)check($render($m,['tab'=>'library_books'])==='LOGIN_REQUIRED','Anonymous writes require login');
 foreach(['dashboard','library_loans','library_statistics','equipment_catalog','maintenance']as$t)check($render('GET',['tab'=>$t])==='LOGIN_REQUIRED','Protected tab '.$t);
 foreach(['library_qr','manual_code','book_export','action','confirm_import']as$key)check($render('GET',['tab'=>'library_books',$key=>'x'])==='LOGIN_REQUIRED','Protected alternate handler '.$key);
 check($render('GET',['tab'=>'library_books'],true)==='SIGNED_IN_MANAGEMENT','Signed-in management route unchanged');
 check(hash_file('sha256',$tmp.'/data/library_equipment.json')===$hash,'All requests preserve stored data');
 echo "PASS public catalogue: actual anonymous route, authenticated isolation, forbidden actions, privacy, XSS, availability, search and pagination; unchanged synthetic data\n";
} finally {
 $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);
}
