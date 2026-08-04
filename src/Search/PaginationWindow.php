<?php
/**
 * Calculates safe offset-based windows for asynchronous catalog loading.
 *
 * @package PGW
 */
declare(strict_types=1);

namespace PGW\Search;

final class PaginationWindow
{
    public function __construct(
        private readonly int $maximumAmount = 50
    ) {
    }

    /** @return array{offset:int,amount:int,next_offset:int} */
    public function fromInput(mixed $offset, mixed $amount, int $defaultAmount = 15): array
    {
        $safeOffset = filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $safeAmount = filter_var($amount, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $safeOffset = $safeOffset === false ? 0 : $safeOffset;
        $safeAmount = $safeAmount === false ? $defaultAmount : $safeAmount;
        $safeAmount = max(1, min($this->maximumAmount, $safeAmount));

        return [
            'offset' => $safeOffset,
            'amount' => $safeAmount,
            'next_offset' => $safeOffset + $safeAmount,
        ];
    }

    public function hasMore(int $offset, int $returned, int $total): bool
    {
        return $returned > 0 && ($offset + $returned) < $total;
    }
}
