<div class="row games-grid">
<?php foreach ($games as $game):
    if (!$isAdmin && isset($gameVisibility[$game['key']]) && !$gameVisibility[$game['key']]) continue;
?>
<div class="col-12 col-sm-6 col-xl-4 col-xxl-3"><article class="game-tile game-<?= e($game['key']) ?>" aria-labelledby="game-title-<?= e($game['key']) ?>">
    <a class="game-cover" href="<?= BASE_URL . e($game['url']) ?>" aria-label="<?= e('Mở ' . $game['name']) ?>">
        <img src="<?= BASE_URL ?>assets/game-covers/illustrated-v3/<?= e($game['key']) ?>.webp?v=20261110-5" alt="" width="720" height="405" loading="<?= $game['key'] === 'muc' ? 'eager' : 'lazy' ?>" decoding="async">
    </a>
    <div class="game-copy"><h3 id="game-title-<?= e($game['key']) ?>"><?= e($game['name']) ?></h3><p><?= e($game['description']) ?></p></div>
    <a class="game-link" href="<?= BASE_URL . e($game['url']) ?>"><i class="bi <?= $game['key']==='cards'?'bi-printer':'bi-play-circle' ?>" aria-hidden="true"></i><?= e($game['action']) ?></a>
</article></div>
<?php endforeach; ?>
</div>
