<div id="nafb-builder-root" class="nafb-layout" data-config="<?php echo esc_attr(wp_json_encode($builderConfig)); ?>">
    <section class="nafb-main" aria-label="<?php esc_attr_e('Builder de variações', 'nafloresta-buy'); ?>">
        <header class="nafb-head">
            <h3><?php esc_html_e('Monte seu pedido', 'nafloresta-buy'); ?></h3>
            <p><?php esc_html_e('Selecione as variações e personalize os nomes dos alunos.', 'nafloresta-buy'); ?></p>
        </header>

        <div class="nafb-variation-list" data-role="variation-list"></div>

        <button type="button" class="button alt nafb-submit vs-btn" data-role="submit"><?php esc_html_e('Adicionar ao carrinho', 'nafloresta-buy'); ?></button>
        <div class="nafb-messages" data-role="messages" aria-live="polite"></div>
    </section>

    <aside class="nafb-summary" aria-label="<?php esc_attr_e('Resumo da seleção', 'nafloresta-buy'); ?>">
        <h4><?php esc_html_e('Resumo', 'nafloresta-buy'); ?></h4>
        <div data-role="summary"></div>
        <footer class="nafb-summary__footer">
            <span><?php esc_html_e('Subtotal', 'nafloresta-buy'); ?></span>
            <strong data-role="subtotal">R$ 0,00</strong>
        </footer>
    </aside>

    <div class="nafb-drawer-overlay" data-role="drawer-overlay" aria-hidden="true"></div>

    <section class="nafb-drawer" data-role="drawer" aria-hidden="true" aria-label="<?php esc_attr_e('Personalização da variação', 'nafloresta-buy'); ?>">
        <header class="nafb-drawer__header">
            <h4 data-role="drawer-title"><?php esc_html_e('Personalização', 'nafloresta-buy'); ?></h4>
            <button type="button" class="nafb-drawer__close" data-role="drawer-close" data-action="close" aria-label="<?php esc_attr_e('Fechar', 'nafloresta-buy'); ?>">×</button>
        </header>

        <div class="nafb-drawer__quantity" role="group" aria-label="<?php esc_attr_e('Quantidade da variação', 'nafloresta-buy'); ?>">
            <button type="button" data-role="drawer-minus" data-action="decrease-drawer" aria-label="<?php esc_attr_e('Diminuir quantidade', 'nafloresta-buy'); ?>">−</button>
            <input type="number" readonly data-role="drawer-qty" aria-label="<?php esc_attr_e('Quantidade atual', 'nafloresta-buy'); ?>" value="0" />
            <button type="button" data-role="drawer-plus" data-action="increase-drawer" aria-label="<?php esc_attr_e('Aumentar quantidade', 'nafloresta-buy'); ?>">+</button>
        </div>

        <div class="nafb-drawer__students" data-role="drawer-students"></div>
    </section>
</div>
