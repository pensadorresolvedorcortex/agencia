<?php
/**
 * Deterministic showcase ordering independent from WordPress queries.
 *
 * @package PGW
 */
declare(strict_types=1);

namespace PGW\Featured;

final class FeaturedOrder
{
    /**
     * @param array<int, int> $priorities Post ID indexed priorities.
     * @return int[] Post IDs ordered left, center, right, then remaining items.
     */
    public function order(array $priorities): array
    {
        $slots = [2 => [], 1 => [], 3 => []];
        $remaining = [];

        foreach ($priorities as $postId => $priority) {
            $postId = (int) $postId;
            $priority = (int) $priority;
            if ($postId < 1) continue;
            if (isset($slots[$priority])) $slots[$priority][] = $postId;
            else $remaining[] = $postId;
        }

        return array_merge($slots[2], $slots[1], $slots[3], $remaining);
    }
}
