# Illustrated game covers

The 14 catalog covers are newly generated illustrations matching the user's supplied chibi school-game reference. Each includes its Vietnamese title as part of the artwork: rounded glossy yellow/cyan lettering, dark inner contour, white sticker outline, offset shadow, red subtitle ribbon and expressive schoolchildren. Scenery and central props correspond to each game.

Production method: built-in image generation, one image per game. The exact common prompt and per-game title/subtitle/scene specifications are in `game-cover-prompts.json`. Original generated PNGs are preserved outside the source checkout. Web assets are exported as 720 × 405 WebP files under `assets/game-covers/illustrated-v2/`.

The catalog no longer renders an additional title, dark shade, category badge or controller stamp over the illustrated cover. The real HTML game name and accessible link label remain. The hover sheen respects reduced-motion preferences. Kéo co and Ô cửa bí mật setup previews use the corresponding new cover.

Original root-level covers remain available for older callers. The actual game scenes, sound, scoring, question banks and school data are unchanged by this artwork update. The gallery test checks 14 local images, dimensions, file size budget, routes, visibility, lazy loading and absence of duplicate title overlays. The deployment fixture checks the new artwork reaches its target directory.
