<?php
/** Allow formatted article content while rejecting active HTML. */
function lh_article_clean(string $html): string {
    if (!class_exists('DOMDocument')) return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="article-root">' . $html . '</div>', LIBXML_NONET);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $allowed = explode(' ', 'div p br span h1 h2 h3 h4 h5 h6 strong b em i u s strike sub sup blockquote pre code ul ol li a img table thead tbody tfoot tr th td caption colgroup col hr');
    $clean = function ($node) use (&$clean, $allowed): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                if (!in_array(strtolower($child->tagName), $allowed, true)) { $node->removeChild($child); continue; }
                foreach (iterator_to_array($child->attributes) as $attribute) {
                    $name = strtolower($attribute->name); $value = trim($attribute->value);
                    $ok = in_array($name, ['style','title','alt','width','height','colspan','rowspan','scope','start','type'], true);
                    if ($name === 'href') $ok = preg_match('#^https?://#i', $value) === 1;
                    if ($name === 'src' && strtolower($child->tagName) === 'img') $ok = preg_match('#^(https?://|data:image/(png|jpeg|gif|webp);base64,)#i', $value) === 1;
                    if ($name === 'style' && preg_match('/url\s*\(|expression\s*\(|@import|behavior|binding/i', $value)) $ok = false;
                    if (!$ok) $child->removeAttribute($attribute->name);
                }
                $clean($child);
            } elseif (!($child instanceof DOMText)) $node->removeChild($child);
        }
    };
    $body = $doc->getElementsByTagName('body')->item(0);
    if (!$body) return '';
    $clean($body); $result = '';
    foreach ($body->childNodes as $child) $result .= $doc->saveHTML($child);
    return $result;
}
