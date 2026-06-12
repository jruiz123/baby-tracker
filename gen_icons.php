#!/usr/bin/env php
<?php
$dir = __DIR__ . '/assets/icons';
if (!is_dir($dir)) mkdir($dir, 0755, true);
foreach ([192, 512] as $size) {
    $img  = imagecreatetruecolor($size, $size);
    $bg   = imagecolorallocate($img, 232, 115, 90);
    $white= imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $size-1, $size-1, $bg);
    $cx = $size/2; $r = $size*0.2;
    imagefilledellipse($img, $cx, $cx+$size*0.12, $r*2, $r*2, $white);
    imagefilledrectangle($img, $cx-$r*0.4, $cx-$size*0.28, $cx+$r*0.4, $cx+$size*0.06, $white);
    imagepng($img, "$dir/icon-{$size}.png");
    imagedestroy($img);
    echo "icon-{$size}.png created\n";
}
