# Illustrated game catalog

The Games tab uses 13 original AI-generated chibi covers, one for each existing game/tool. The 720 × 405 WebP assets live in `assets/game-covers/`; the titles are real HTML text using the locally bundled Nunito font, preserving Vietnamese accents and selectable accessible headings. Covers contain no baked-in titles or student data.

`assets/hoclieu-games.css` is loaded only on the Games tab. Bootstrap displays four columns on large desktops, three on smaller desktops, two on tablets and one on phones. Both the cover and action button open the existing route. Existing authorization, administrator visibility settings, CSRF forms and game implementations are unchanged.

All covers together are under 1 MB. Explicit dimensions reserve image space; the first cover is eager and the rest lazy-loaded. Reduced-motion preferences disable hover animation.

Validation: `php tests/game_gallery_test.php` checks actual catalog rendering, routes, cover dimensions/MIME/budget and teacher/admin visibility. `php tests/staff_evaluation_deploy_test.php` checks byte-identical deployment and preservation of hosting data. Artwork was visually reviewed; direct browser layout QA was unavailable because the browser session usage limit was reached.

Deploy through the existing cPanel Git workflow: Update from Remote, Deploy HEAD Commit, then refresh the Games tab with Ctrl+F5.
