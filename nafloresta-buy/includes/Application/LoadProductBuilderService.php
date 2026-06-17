<?php

namespace NaFlorestaBuy\Application;

use NaFlorestaBuy\Infrastructure\Repository\ConfigRepositoryInterface;

class LoadProductBuilderService
{
    private ConfigRepositoryInterface $repository;

    public function __construct(ConfigRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function load(int $productId): array
    {
        return $this->repository->getProductConfig($productId);
    }
}
