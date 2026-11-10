<?php
// Render the actual catalog template without bootstrapping the production database.
define('BASE_URL', '/cds/');
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$root = dirname(__DIR__);
$source = file_get_contents($root.'/hoclieu.php');
$start = strpos($source, '$games = [');
$end = strpos($source, '<?php else: ?>', $start);
if ($start === false || $end === false) throw new RuntimeException('Catalog template missing');
$template = substr($source, $start, $end-$start);
$render = function ($isAdmin, $gameVisibility) use ($template) {
    ob_start(); eval($template); return [ob_get_clean(), $games];
};
[$html, $games] = $render(false, []);
if (count($games)!==14 || substr_count($html, '<article ')!==14) throw new RuntimeException('Incomplete gallery');
$bytes = 0;
foreach ($games as $game) {
    $key = $game['key'];
    $file = $root.'/assets/game-covers/illustrated-v2/'.$key.'.webp';
    $size = getimagesize($file);
    if (!$size || $size[0]!==720 || $size[1]!==405 || $size['mime']!=='image/webp') throw new RuntimeException('Invalid illustrated cover: '.$key);
    if(!str_contains($html,'assets/game-covers/illustrated-v2/'.$key.'.webp')) throw new RuntimeException('Catalog does not use illustrated cover: '.$key);
    $bytes += filesize($file);
    if (!is_file($root.'/'.$game['url']) || substr_count($html, 'href="/cds/'.e($game['url']).'"')!==2) throw new RuntimeException('Broken game route: '.$key);
    if (!str_contains($html, 'id="game-title-'.$key.'"') || !str_contains($html, e($game['name']))) throw new RuntimeException('Missing accessible title: '.$key);
}
if(str_contains($html,'class="game-cover-title"')||str_contains($html,'class="game-cover-shade"')) throw new RuntimeException('Duplicate title/shade over illustrated cover');
if ($bytes>1500000) throw new RuntimeException('Cover budget exceeded');
[$hidden] = $render(false, ['quiz'=>false, 'cards'=>false]);
[$admin] = $render(true, ['quiz'=>false, 'cards'=>false]);
if (substr_count($hidden, '<article ')!==12 || str_contains($hidden, 'id="game-title-quiz"') || str_contains($hidden, 'id="game-title-cards"') || substr_count($admin, '<article ')!==14) throw new RuntimeException('Visibility regression');
if (substr_count($html, 'loading="lazy"')!==13) throw new RuntimeException('Lazy loading regression');
echo "PASS: 14 integrated-title illustrated covers and game routes; teacher visibility and admin access preserved; lazy loading; ".$bytes." image bytes\n";
