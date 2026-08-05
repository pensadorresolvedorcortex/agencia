<?php
/** Preserve all portal data by default. */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
// Deliberadamente não remove usuários, grupos, opções ou tabelas.
