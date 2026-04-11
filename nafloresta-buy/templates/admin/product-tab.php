<div id="nafloresta_buy_panel" class="panel woocommerce_options_panel hidden nafb-admin-panel">
    <?php wp_nonce_field('nafb_save_product_config', 'nafb_admin_nonce'); ?>
    <input type="hidden" name="nafb_preset_action" id="nafb_preset_action" value="" />

    <div class="nafb-admin-grid">
        <section class="nafb-admin-card">
            <h3><?php esc_html_e('Configuração do Builder', 'nafloresta-buy'); ?></h3>
            <p class="description"><?php esc_html_e('Defina como o NaFlorestaBuy será exibido neste produto.', 'nafloresta-buy'); ?></p>

            <p class="form-field">
                <label for="nafb_enabled"><?php esc_html_e('Ativar builder', 'nafloresta-buy'); ?></label>
                <select name="nafb_enabled" id="nafb_enabled" data-nafb-required="true">
                    <option value="no" <?php selected(!empty($config['enabled']) ? 'yes' : 'no', 'no'); ?>><?php esc_html_e('Não', 'nafloresta-buy'); ?></option>
                    <option value="yes" <?php selected(!empty($config['enabled']) ? 'yes' : 'no', 'yes'); ?>><?php esc_html_e('Sim', 'nafloresta-buy'); ?></option>
                </select>
            </p>

            <p class="form-field">
                <label for="nafb_builder_mode"><?php esc_html_e('Builder mode', 'nafloresta-buy'); ?></label>
                <select name="nafb_builder_mode" id="nafb_builder_mode">
                    <option value="matrix" <?php selected($config['builder_mode'] ?? 'matrix', 'matrix'); ?>>matrix</option>
                    <option value="single" <?php selected($config['builder_mode'] ?? 'matrix', 'single'); ?>>single</option>
                </select>
            </p>

            <p class="form-field">
                <label for="nafb_ui_mode"><?php esc_html_e('UI mode', 'nafloresta-buy'); ?></label>
                <select name="nafb_ui_mode" id="nafb_ui_mode">
                    <option value="drawer" <?php selected($config['ui_mode'] ?? 'drawer', 'drawer'); ?>>drawer</option>
                    <option value="modal" <?php selected($config['ui_mode'] ?? 'drawer', 'modal'); ?>>modal</option>
                    <option value="inline" <?php selected($config['ui_mode'] ?? 'drawer', 'inline'); ?>>inline</option>
                </select>
            </p>
        </section>

        <section class="nafb-admin-card">
            <h3><?php esc_html_e('Variações participantes', 'nafloresta-buy'); ?></h3>
            <p class="form-field">
                <label for="nafb_variation_ids"><?php esc_html_e('Variações habilitadas', 'nafloresta-buy'); ?></label>
                <select class="wc-enhanced-select" multiple="multiple" name="nafb_variation_ids[]" id="nafb_variation_ids" style="width: 100%;" data-nafb-required="true">
                    <?php foreach ($variationOptions as $variationOption) : ?>
                        <option value="<?php echo esc_attr((string) $variationOption['id']); ?>" <?php selected(in_array($variationOption['id'], array_map('intval', $config['variation_ids'] ?? []), true)); ?>>
                            <?php echo esc_html($variationOption['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p class="form-field">
                <label for="nafb_fields_schema"><?php esc_html_e('Schema de campos (JSON)', 'nafloresta-buy'); ?></label>
                <textarea id="nafb_fields_schema" name="nafb_fields_schema" rows="8" style="width:100%;"><?php echo esc_textarea(wp_json_encode($config['fields_schema'] ?? [], JSON_PRETTY_PRINT)); ?></textarea>
                <span class="description"><?php esc_html_e('Suporta text, select, checkbox e date.', 'nafloresta-buy'); ?></span>
            </p>
        </section>

        <section class="nafb-admin-card">
            <h3><?php esc_html_e('Presets reutilizáveis', 'nafloresta-buy'); ?></h3>
            <p class="form-field">
                <label for="nafb_preset_name"><?php esc_html_e('Salvar preset', 'nafloresta-buy'); ?></label>
                <input type="text" id="nafb_preset_name" name="nafb_preset_name" placeholder="<?php esc_attr_e('Ex.: Uniforme Escolar', 'nafloresta-buy'); ?>" />
                <button type="button" class="button" data-nafb-action="save_preset"><?php esc_html_e('Salvar preset', 'nafloresta-buy'); ?></button>
            </p>

            <p class="form-field">
                <label for="nafb_preset_key"><?php esc_html_e('Aplicar preset', 'nafloresta-buy'); ?></label>
                <select id="nafb_preset_key" name="nafb_preset_key" style="width:100%;">
                    <option value=""><?php esc_html_e('Selecione um preset', 'nafloresta-buy'); ?></option>
                    <?php foreach ((array) $presets as $presetKey => $presetData) : ?>
                        <option value="<?php echo esc_attr((string) $presetKey); ?>"><?php echo esc_html((string) ($presetData['name'] ?? $presetKey)); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button" data-nafb-action="apply_preset"><?php esc_html_e('Aplicar preset', 'nafloresta-buy'); ?></button>
            </p>
        </section>

        <section class="nafb-admin-card">
            <h3><?php esc_html_e('Exportar / Importar configuração', 'nafloresta-buy'); ?></h3>
            <p class="form-field">
                <label for="nafb_export_json"><?php esc_html_e('Export JSON', 'nafloresta-buy'); ?></label>
                <textarea id="nafb_export_json" rows="8" style="width:100%;" readonly><?php echo esc_textarea(wp_json_encode($config, JSON_PRETTY_PRINT)); ?></textarea>
            </p>
            <p class="form-field">
                <label for="nafb_import_json"><?php esc_html_e('Import JSON', 'nafloresta-buy'); ?></label>
                <textarea id="nafb_import_json" name="nafb_import_json" rows="8" style="width:100%;"></textarea>
                <button type="button" class="button" data-nafb-action="import_config"><?php esc_html_e('Importar e aplicar', 'nafloresta-buy'); ?></button>
            </p>
        </section>

        <section class="nafb-admin-card nafb-admin-preview" data-role="nafb-preview" data-variations="<?php echo esc_attr(wp_json_encode($variationOptions)); ?>">
            <h3><?php esc_html_e('Preview rápido', 'nafloresta-buy'); ?></h3>
            <div class="nafb-preview-canvas" data-role="nafb-preview-canvas"></div>
        </section>
    </div>

    <p class="nafb-admin-inline-error" data-role="nafb-admin-error" hidden></p>
</div>
