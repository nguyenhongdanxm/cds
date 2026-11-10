# Secret doors audio credits

Author: **Kenney** (https://kenney.nl).
License for all eight audio files: **Creative Commons Zero 1.0 (CC0-1.0)**.
CC0 legal text: https://creativecommons.org/publicdomain/zero/1.0/legalcode

Primary pack pages explicitly marking the assets CC0:

- Interface Sounds: https://kenney.nl/assets/interface-sounds
- Music Jingles: https://kenney.nl/assets/music-jingles

The original download endpoints were unavailable during preparation. Audio was retrieved from a pinned redistribution of those packs, with its explicit source mapping:

https://github.com/manuel-palacio/brickstorm/blob/90673e14473430ef16a3b163431dc03d6adc6fd0/CREDITS.md

Only the named Kenney audio files were used; no code, graphics, synthesized music or other assets from that repository are included.

| Local file | Original Kenney file | Pack |
| --- | --- | --- |
| click.mp3 | click_001.ogg | Interface Sounds |
| tick.mp3 | click_002.ogg | Interface Sounds |
| open.mp3 | drop_002.ogg | Interface Sounds |
| correct.mp3 | confirmation_001.ogg | Interface Sounds |
| wrong.mp3 | jingles_NES00.ogg | Music Jingles |
| win.mp3 | jingles_NES12.ogg | Music Jingles |
| loss.mp3 | jingles_HIT09.ogg | Music Jingles |
| sparkle.mp3 | jingles_NES12.ogg | Music Jingles |

Modifications: short extracts, loudness normalization (target −19 LUFS, true peak −2 dB), optional ending fade, mono 44.1 kHz MP3 transcode with FFmpeg. Runtime variations change playback pitch/rate for different rewards. Full source and output SHA-256 values are in `manifest.json`. Retrieved 2026-11-10. The game serves these files locally and does not contact Kenney or GitHub during gameplay.

The previous synthesized background has been replaced with a licensed music excerpt (see below); the animated SVG icons are drawn directly in CDS code.

## Background music (separate license)

“Monkeys Spinning Monkeys” Kevin MacLeod (incompetech.com)
Licensed under Creative Commons: By Attribution 4.0 License
https://creativecommons.org/licenses/by/4.0/

Source: https://incompetech.com/music/royalty-free/index.html?isrc=USUAN1400011

`music-loop.mp3` is a 48-second excerpt from 00:04, normalized, faded, transcoded to mono MP3 and looped. This background track is **CC BY 4.0, not CC0**. Visible attribution, license links and modification notice appear in the game. Exact source, modification details and hashes are in `music-license.json`.
