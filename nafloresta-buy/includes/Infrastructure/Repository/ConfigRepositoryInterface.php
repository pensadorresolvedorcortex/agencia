<?php

namespace NaFlorestaBuy\Infrastructure\Repository;

interface ConfigRepositoryInterface
{
    public function getProductConfig(int $productId): array;

    public function saveProductConfig(int $productId, array $config): void;
}
