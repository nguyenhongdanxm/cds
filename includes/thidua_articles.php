<?php
/** Rich thi-dua article fragments; shared editor elsewhere is unchanged. */
function td_article_safe_link(string $url): string {
    $url=trim($url);
    return preg_match('~^(https?://|mailto:|tel:|/(?!/)|\#)~i',$url)&&!preg_match('/[\x00-\x20]/',$url)?$url:'';
}
function td_article_style(string $style): string {
    $allowed=explode(' ','color background-color font-family font-size font-weight font-style text-decoration text-align line-height letter-spacing vertical-align text-indent margin-left margin-right margin-top margin-bottom padding padding-left padding-right padding-top padding-bottom width height max-width min-width border border-width border-style border-color border-collapse border-spacing border-top border-bottom border-left border-right list-style-type');
    $out=[];foreach(explode(';',$style)as$part){$pair=explode(':',$part,2);if(count($pair)!==2)continue;[$name,$value]=array_map('trim',$pair);$name=strtolower($name);
        if(!in_array($name,$allowed,true)||$value===''||preg_match('/url\s*\(|expression|@|\\\\|[<>\x00-\x08\x0b\x0c\x0e-\x1f]/i',$value))continue;
        if(!preg_match('/^[\pL\pN\s#.,%()\'"+\/-]+$/u',$value))continue;
        $out[]=$name.':'.$value;
    }return implode(';',$out);
}
function td_article_clean(string $html): string {
    $html=trim($html);if($html==='')return '';
    if($html===strip_tags($html))return nl2br(htmlspecialchars(html_entity_decode($html,ENT_QUOTES|ENT_HTML5,'UTF-8'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
    if(!class_exists('DOMDocument'))return nl2br(htmlspecialchars(html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
    $doc=new DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="td-article-root">'.$html.'</div>',LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);
    $root=$doc->getElementById('td-article-root');if(!$root)return '';
    $allowed=explode(' ','div p br span h1 h2 h3 h4 h5 h6 strong b em i u s strike sub sup blockquote pre code ul ol li a img table thead tbody tfoot tr th td caption colgroup col hr figure figcaption mark');
    $drop=explode(' ','script style iframe object embed form input button textarea select option link meta base svg math noscript');
    $walk=function($node)use(&$walk,$allowed,$drop):void{
        foreach(iterator_to_array($node->childNodes)as$child){
            if($child instanceof DOMElement){
                $tag=strtolower($child->tagName);
                if(in_array($tag,$drop,true)){$node->removeChild($child);continue;}
                $walk($child);
                if(!in_array($tag,$allowed,true)){while($child->firstChild)$node->insertBefore($child->firstChild,$child);$node->removeChild($child);continue;}
                foreach(iterator_to_array($child->attributes)as$attribute){
                    $name=strtolower($attribute->name);$value=trim($attribute->value);$ok=false;
                    if(in_array($name,['title','alt'],true))$ok=true;
                    elseif($name==='style'){$value=td_article_style($value);$ok=$value!=='';}
                    elseif(in_array($name,['width','height'],true))$ok=preg_match('/^\d+(?:\.\d+)?(?:px|pt|%)?$/',$value)===1;
                    elseif(in_array($name,['colspan','rowspan','start'],true))$ok=preg_match('/^\d{1,4}$/',$value)===1;
                    elseif($name==='scope')$ok=in_array($value,['row','col','rowgroup','colgroup'],true);
                    elseif($name==='type'&&in_array($tag,['ol','ul','li'],true))$ok=in_array($value,['1','a','A','i','I','disc','circle','square'],true);
                    elseif($name==='href'&&$tag==='a'){$value=td_article_safe_link($value);$ok=$value!=='';}
                    elseif($name==='target'&&$tag==='a')$ok=in_array($value,['_blank','_self'],true);
                    elseif($name==='src'&&$tag==='img')$ok=preg_match('~^(https?://|/(?!/)|data:image/(?:png|jpeg|gif|webp);base64,[a-zA-Z0-9+/=\r\n]+$)~i',$value)===1&&!preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value);
                    if(!$ok)$child->removeAttribute($attribute->name);else$child->setAttribute($attribute->name,$value);
                }
                if($tag==='a'&&$child->getAttribute('target')==='_blank')$child->setAttribute('rel','noopener noreferrer');
            }elseif(!($child instanceof DOMText))$node->removeChild($child);
        }
    };$walk($root);$out='';foreach($root->childNodes as$node)$out.=$doc->saveHTML($node);return $out;
}
function td_article_body($html): string {
    $clean=td_article_clean((string)$html);
    return $clean===''?'<span class="text-muted">Bài viết chưa có nội dung.</span>':$clean;
}
function td_article_excerpt(string $html,int $width=180): string {
    $html=preg_replace('~</(?:p|div|h[1-6]|li|tr|blockquote|figure)>|<br\b[^>]*>~i',' ',$html);
    $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
    $text=trim(preg_replace('/[\s\x{00a0}]+/u',' ',$text));
    return mb_strimwidth($text,0,$width,'…','UTF-8');
}
