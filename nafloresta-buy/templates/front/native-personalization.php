<div id="nafb-native-layer" data-config="<?php echo esc_attr(wp_json_encode($nativeConfig)); ?>">
    <div class="nafb-modal-overlay" data-role="nafb-modal-overlay" aria-hidden="true"></div>
    <section class="nafb-modal" data-role="nafb-modal" aria-hidden="true" aria-label="<?php esc_attr_e('Personalizar', 'nafloresta-buy'); ?>">
        <header class="nafb-modal__header">
            <div>
                <h4><?php esc_html_e('Personalizar', 'nafloresta-buy'); ?></h4>
                <p class="nafb-modal__subtitle" data-role="nafb-modal-subtitle"></p>
                <p class="nafb-modal__trust"><?php esc_html_e('Personalização segura • você poderá revisar depois', 'nafloresta-buy'); ?></p>
                <p class="nafb-modal__helper" data-role="nafb-modal-helper">Digite o nome para personalizar o livro</p>
                <p class="nafb-modal__progress" data-role="nafb-modal-progress"></p>
            </div>
            <button type="button" class="nafb-modal__close" data-role="nafb-modal-close" aria-label="<?php esc_attr_e('Fechar', 'nafloresta-buy'); ?>">×</button>
        </header>

        <div class="nafb-modal__fields" data-role="nafb-modal-fields"></div>

        <button type="button" class="vs-btn nafb-modal__ok" data-role="nafb-modal-ok"><?php esc_html_e('Confirmar', 'nafloresta-buy'); ?></button>
    </section>
</div>
