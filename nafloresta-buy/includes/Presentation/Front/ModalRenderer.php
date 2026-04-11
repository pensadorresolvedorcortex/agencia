<?php

namespace NaFlorestaBuy\Presentation\Front;

class ModalRenderer
{
    public function render(): void
    {
        include NAFB_PLUGIN_PATH . 'templates/front/modal.php';
    }
}
