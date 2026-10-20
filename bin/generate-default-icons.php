<?php

declare(strict_types=1);

/** Genera les icones per defecte (lletres JSPM sobre blau) a public/assets/img/icons. Ús: php bin/generate-default-icons.php */
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

$dir = BASE_PATH . '/public/assets/img/icons';
@mkdir($dir, 0755, true);
$sizes = ['favicon-16' => 16, 'favicon-32' => 32, 'apple-touch-icon' => 180, 'icon-192' => 192, 'icon-512' => 512, 'icon-maskable-512' => 512];

function drawBase(int $n, bool $round): GdImage
{
    $big = 512;
    $im = imagecreatetruecolor($big, $big);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    $blue = imagecolorallocate($im, 11, 61, 145);
    $white = imagecolorallocate($im, 255, 255, 255);
    if ($round) {
        imagefilledellipse($im, 256, 256, 500, 500, $blue);
    } else {
        imagefilledrectangle($im, 0, 0, 511, 511, $blue);
    }
    // Lletres J S P M dibuixades com a blocs gruixuts (sense dependre de tipus de lletra)
    $s = 17;
    $glyphs = [
        'J' => ["..###", "...#.", "...#.", "#..#.", ".##.."],
        'S' => [".####", "#....", ".###.", "....#", "####."],
        'P' => ["####.", "#...#", "####.", "#....", "#...."],
        'M' => ["#...#", "##.##", "#.#.#", "#...#", "#...#"],
    ];
    $gw = 5 * $s;
    $gap = 13;
    $total = 4 * $gw + 3 * $gap;
    $x0 = intdiv(512 - $total, 2);
    $y0 = intdiv(512 - 5 * $s, 2);
    $i = 0;
    foreach ($glyphs as $rows) {
        foreach ($rows as $ry => $row) {
            foreach (str_split($row) as $rx => $c) {
                if ($c === '#') {
                    imagefilledrectangle($im, $x0 + $i * ($gw + $gap) + $rx * $s, $y0 + $ry * $s, $x0 + $i * ($gw + $gap) + ($rx + 1) * $s - 1, $y0 + ($ry + 1) * $s - 1, $white);
                }
            }
        }
        $i++;
    }
    return $im;
}

foreach ($sizes as $name => $size) {
    $base = drawBase($size, !in_array($name, ['icon-maskable-512', 'apple-touch-icon'], true));
    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagealphablending($out, false);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $base, 0, 0, 0, 0, $size, $size, 512, 512);
    imagepng($out, "$dir/$name.png", 9);
    echo "$name.png\n";
}
