<?php
declare(strict_types=1);

namespace PGW\Taxonomy;

final class CategoryColor
{
    private const PALETTE = ['#39ff88','#00e5ff','#ff4fd8','#ffe04b','#9d7bff','#ff7a45','#5cffea','#c7ff4a','#ff5f7e','#65a7ff','#f8ff57','#c65cff'];

    public function normalize(mixed $color): string
    {
        $color = strtolower(trim((string) $color));
        return preg_match('/^#[0-9a-f]{6}$/D', $color) === 1 ? $color : '';
    }

    public function fallback(int $termId, string $slug = ''): string
    {
        $seed = $termId > 0 ? $termId : (int) sprintf('%u', crc32($slug));
        return self::PALETTE[$seed % count(self::PALETTE)];
    }

    public function ink(string $color): string
    {
        $color = $this->normalize($color) ?: '#ffffff';
        $red = hexdec(substr($color, 1, 2));
        $green = hexdec(substr($color, 3, 2));
        $blue = hexdec(substr($color, 5, 2));
        return (($red * 299 + $green * 587 + $blue * 114) / 1000) >= 145 ? '#102d19' : '#ffffff';
    }
}
