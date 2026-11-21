<?php
require __DIR__.'/../includes/thidua_articles.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
$png='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jJFAAAAAASUVORK5CYII=';
$html='<h1 style="text-align:center">Hồ sơ thi đua</h1><p style="font-family:Times New Roman;font-size:14pt;color:#ff0000;background-color:#ffff00;line-height:1.5"><strong>Đậm</strong> <em>Nghiêng</em> <u>Gạch chân</u> <s>Gạch ngang</s> H<sub>2</sub>O m<sup>2</sup></p><ul><li>Mục 1</li></ul><ol start="2"><li>Mục 2</li></ol><table style="border-collapse:collapse;width:100%"><tbody><tr><th scope="col">Lớp</th><td colspan="2" style="border:1px solid #333;padding:6px">6A</td></tr></tbody></table><figure><img src="'.$png.'" width="120" alt="Ảnh học sinh"><figcaption>Chú thích ảnh</figcaption></figure><a href="https://example.org/doc" target="_blank">Văn bản</a><hr><blockquote>Trích dẫn</blockquote>';
$clean=td_article_clean($html);
foreach(['<h1','font-family:Times New Roman','font-size:14pt','color:#ff0000','background-color:#ffff00','line-height:1.5','<sub>','<sup>','<table','colspan="2"','<img','<figcaption>','noopener noreferrer','<hr']as$part)check(str_contains($clean,$part),'Rich feature survives: '.$part);
check(td_article_clean($clean)===$clean,'Edit/save/reopen round trip does not grow wrappers or change formatting');
$legacy="SỞ GD&amp;ĐT TUYÊN QUANG\nNội dung cũ";check(str_contains(td_article_body($legacy),'GD&amp;ĐT')&&str_contains(td_article_body($legacy),'<br'),'Plain legacy text and line breaks preserved');
check(td_article_excerpt('<p>SỞ GD&amp;ĐT&nbsp;TUYÊN QUANG</p><p>Trường THCS&amp;THPT Xín Mần</p>')==='SỞ GD&ĐT TUYÊN QUANG Trường THCS&THPT Xín Mần','Excerpt decodes entities and separates paragraphs');
check(mb_strwidth(td_article_excerpt(str_repeat('Đ ',200),30),'UTF-8')<=30,'Excerpt is bounded');
$attack='<p onclick="alert(1)" style="position:fixed;z-index:99999;color:red;background-image:url(https://evil.test/a);width:expression(alert(1))">Giữ chữ</p><script>alert(1)</script><iframe src="https://evil.test"></iframe><img src="data:image/svg+xml;base64,PHN2Zy8+" onerror="alert(1)"><a href="java&#x73;cript:alert(1)">Liên kết</a><style>body{display:none}</style><div id="bad" class="overlay">Nội dung</div>';
$safe=td_article_clean($attack);
foreach(['onclick','onerror','<script','<iframe','<style','javascript:','svg+xml','position:','z-index:','background-image','expression','id="bad"','class="overlay"']as$bad)check(!str_contains(strtolower($safe),strtolower($bad)),'Removed active/unscoped markup: '.$bad);
check(str_contains($safe,'Giữ chữ')&&str_contains($safe,'color:red')&&str_contains($safe,'Nội dung'),'Text and allowed style retained during cleanup');
foreach(['javascript:alert(1)','data:text/html,hi','//evil.test','https://a.test/ bad']as$bad)check(td_article_safe_link($bad)==='','Unsafe related URL rejected');
check(td_article_safe_link('https://school.test/file')!==''&&td_article_safe_link('/file.pdf')!=='','Safe URLs retained');
$source=file_get_contents(__DIR__.'/../thidua.php');
check(str_contains($source,'$content=td_article_clean($content)')&&str_contains($source,"$"."articleOwner!==''"),'Sanitize on save without removing existing owner checks');
check(str_contains($source,'e(td_article_excerpt(')&&str_contains($source,'assets/thidua-article-editor.js'),'Actual page uses corrected excerpts and full editor');
check(!str_contains($source,'<textarea hidden name="content"'),'Native textarea remains available if editor download fails');
echo "PASS thi-dua rich articles: text/color/fonts/tables/images/links round trip, legacy content, entity excerpts, safe HTML/URLs and page integration\n";
