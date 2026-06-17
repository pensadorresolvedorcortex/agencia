<?php

namespace NaFlorestaBuy\Application;

use NaFlorestaBuy\Presentation\Shared\CartItemPresenter;

class CartItemPresenterService
{
    public function toDisplay(array $snapshot): array
    {
        $presenter = new CartItemPresenter();
        return $presenter->present(['nafb_snapshot' => $snapshot]);
    }
}
