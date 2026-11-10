# Illustrated game covers

The 14 covers share a polished chibi drawing style and glossy rounded Vietnamese lettering only. Each game has its own composition, camera angle, palette, character cast, clothing and expression. New images are generated without the old reference image to prevent repeated faces and poses. Titles use different placements rather than a repeated wooden plaque and ribbon.

Examples: Bấm chuông uses a low-angle action close-up; Ô cửa bí mật uses an isometric spiral; Kéo co uses diagonal perspective; Quay số shows a tabletop and hands only; Đua vịt features ducks without students. These differences are deliberately part of the art direction.

Production method: built-in image generation, one image per game. The exact common prompt and per-game title/subtitle/scene specifications are in `game-cover-prompts.json`. Original generated PNGs are preserved outside the source checkout. Web assets are exported as 720 × 405 WebP files under `assets/game-covers/illustrated-v3/`.

The catalog no longer renders an additional title, dark shade, category badge or controller stamp over the illustrated cover. The real HTML game name and accessible link label remain. The hover sheen respects reduced-motion preferences. Kéo co and Ô cửa bí mật setup previews use the corresponding new cover.

Original root-level covers remain available for older callers. The actual game scenes, sound, scoring, question banks and school data are unchanged by this artwork update. The gallery test checks 14 local images, dimensions, file size budget, routes, visibility, lazy loading and absence of duplicate title overlays. The deployment fixture checks the new artwork reaches its target directory.
