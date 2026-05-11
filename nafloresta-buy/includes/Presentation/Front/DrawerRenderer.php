<?php

namespace NaFlorestaBuy\Presentation\Front;

class DrawerRenderer
{
    public function render(): void
    {
        include NAFB_PLUGIN_PATH . 'templates/front/drawer.php';
    }
}
