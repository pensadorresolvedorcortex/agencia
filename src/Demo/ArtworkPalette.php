<?php
namespace PGW\Demo;

final class ArtworkPalette {
    private const COLORS = ['#1f8236', '#03d0ae', '#26d768', '#102d19', '#2477a8', '#183c24'];

    public function for(string $seed): array {
        $index = (int) sprintf('%u', crc32($seed)) % count(self::COLORS);
        return [self::COLORS[$index], self::COLORS[($index + 1) % count(self::COLORS)], self::COLORS[($index + 3) % count(self::COLORS)]];
    }
}
