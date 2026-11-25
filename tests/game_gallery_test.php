<?php
// Render the actual catalog template without bootstrapping the production database.
define('BASE_URL', '/cds/');
function e($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$root = dirname(__DIR__);
require_once $root.'/includes/learning_game_catalog.php';
$render = function ($isAdmin, $gameVisibility) use ($root) {
    $games=lh_game_catalog();ob_start();require $root.'/includes/learning_game_cards.php';return [ob_get_clean(),$games];
};
[$html, $games] = $render(false, []);
if (count($games)!==16 || substr_count($html, '<article ')!==16) throw new RuntimeException('Incomplete gallery');
$bytes = 0;
foreach ($games as $game) {
    $key = $game['key'];
    $cover=$game['cover']??('assets/game-covers/illustrated-v3/'.$key.'.webp');
    $file=$root.'/'.$cover;
    $size = getimagesize($file);
    if (!$size || $size[0]!==720 || $size[1]!==405 || $size['mime']!=='image/webp') throw new RuntimeException('Invalid illustrated cover: '.$key);
    if(!str_contains($html,e($cover))) throw new RuntimeException('Catalog does not use illustrated cover: '.$key);
    $bytes += filesize($file);
    if (!is_file($root.'/'.parse_url($game['url'],PHP_URL_PATH)) || substr_count($html, 'href="/cds/'.e($game['url']).'"')!==2) throw new RuntimeException('Broken game route: '.$key);
    if (!str_contains($html, 'id="game-title-'.$key.'"') || !str_contains($html, e($game['name']))) throw new RuntimeException('Missing accessible title: '.$key);
}
if(str_contains($html,'class="game-cover-title"')||str_contains($html,'class="game-cover-shade"')) throw new RuntimeException('Duplicate title/shade over illustrated cover');
if ($bytes>1500000) throw new RuntimeException('Cover budget exceeded');
[$hidden] = $render(false, ['quiz'=>false, 'cards'=>false]);
[$admin] = $render(true, ['quiz'=>false, 'cards'=>false]);
if (substr_count($hidden, '<article ')!==14 || str_contains($hidden, 'id="game-title-quiz"') || str_contains($hidden, 'id="game-title-cards"') || substr_count($admin, '<article ')!==16) throw new RuntimeException('Visibility regression');
if (substr_count($html, 'loading="lazy"')!==15) throw new RuntimeException('Lazy loading regression');
echo "PASS: 16 integrated-title illustrated covers and game routes; teacher visibility and admin access preserved; lazy loading; ".$bytes." image bytes\n";
