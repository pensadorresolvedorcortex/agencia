<?php

namespace NaFlorestaBuy\Presentation\Front;

class SummaryRenderer
{
    public function render(): void
    {
        include NAFB_PLUGIN_PATH . 'templates/front/summary.php';
    }
}
