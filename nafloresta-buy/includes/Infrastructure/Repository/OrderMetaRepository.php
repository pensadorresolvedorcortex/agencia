<?php

namespace NaFlorestaBuy\Infrastructure\Repository;

class OrderMetaRepository
{
    public function getSnapshot(\WC_Order_Item_Product $item): array
    {
        return (array) $item->get_meta('_nafb_snapshot', true);
    }
}
