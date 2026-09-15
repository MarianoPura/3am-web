DROP PHOTOS AND VIDEO POSTERS IN THIS FOLDER
============================================

1. Copy the file here, e.g.  media/showreel-poster.jpg
2. Open  config/assets.php
3. Find the slot and fill in 'src' and 'alt':

       'hero.showreel' => [
           'src' => 'media/showreel-poster.jpg',
           'alt' => 'Camera operator at a live conference broadcast',
       ],

That is the only file you edit. Nothing in app/ or css/ needs touching.

NOTE: an image with a 'src' but no 'alt' will NOT display — the slot keeps
its placeholder and an explanatory comment appears in the page source.
That is deliberate: an unlabelled image is unusable for anyone on a screen
reader, and silently shipping one is worse than showing the placeholder.

SIZES
  21:9  1680x720     4:3   1200x900
  16:9  1600x900     1:1   1000x1000
  JPEG ~80% or WebP. Under 300KB each; hero under 200KB.
