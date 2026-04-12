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
                <p class="nafb-modal__next-step"><?php esc_html_e('Você poderá adicionar outros livros em seguida.', 'nafloresta-buy'); ?></p>
            </div>
            <button type="button" class="nafb-modal__close" data-role="nafb-modal-close" aria-label="<?php esc_attr_e('Fechar', 'nafloresta-buy'); ?>">×</button>
        </header>

        <div class="nafb-modal__fields" data-role="nafb-modal-fields"></div>

        <button type="button" class="vs-btn nafb-modal__ok" data-role="nafb-modal-ok"><?php esc_html_e('Adicionar ao pedido', 'nafloresta-buy'); ?></button>
    </section>

    <p class="nafb-order-summary__status" data-role="nafb-order-summary-status"><?php esc_html_e('Escolha uma série e adicione o nome do aluno.', 'nafloresta-buy'); ?></p>
    <section class="nafb-order-summary" data-role="nafb-order-summary" hidden>
        <header class="nafb-order-summary__head">
            <h4><?php esc_html_e('Resumo do seu pedido', 'nafloresta-buy'); ?></h4>
            <p><?php esc_html_e('Você pode revisar todos os dados antes de pagar.', 'nafloresta-buy'); ?></p>
            <p><?php esc_html_e('Você pode adicionar livros para outros alunos antes de finalizar.', 'nafloresta-buy'); ?></p>
        </header>
        <div class="nafb-order-summary__list" data-role="nafb-order-summary-list"></div>
        <div class="nafb-order-summary__empty" data-role="nafb-order-summary-empty">
            <strong><?php esc_html_e('Nenhum livro adicionado ainda', 'nafloresta-buy'); ?></strong>
            <p><?php esc_html_e('Escolha uma série para começar.', 'nafloresta-buy'); ?></p>
        </div>
        <footer class="nafb-order-summary__footer">
            <strong data-role="nafb-order-summary-subtotal"></strong>
            <div class="nafb-order-summary__actions">
                <a class="nafb-order-summary__cta nafb-order-summary__cta--primary" data-role="nafb-order-go-cart" href="#"><?php esc_html_e('Finalizar pedido', 'nafloresta-buy'); ?></a>
                <button type="button" class="nafb-order-summary__cta nafb-order-summary__cta--secondary" data-role="nafb-order-add-more"><?php esc_html_e('Adicionar outro livro', 'nafloresta-buy'); ?></button>
            </div>
        </footer>
    </section>

    <aside class="nafb-exit-hint" data-role="nafb-exit-hint" hidden>
        <p><?php esc_html_e('Seu pedido está quase pronto — deseja finalizar agora?', 'nafloresta-buy'); ?></p>
        <div class="nafb-exit-hint__actions">
            <a class="button alt" data-role="nafb-exit-checkout" href="#"><?php esc_html_e('Finalizar pedido', 'nafloresta-buy'); ?></a>
            <button type="button" class="button" data-role="nafb-exit-continue"><?php esc_html_e('Continuar escolhendo', 'nafloresta-buy'); ?></button>
        </div>
    </aside>

    <div class="nafb-mobile-checkout" data-role="nafb-mobile-checkout" hidden>
        <a class="button alt" data-role="nafb-mobile-checkout-link" href="#"></a>
    </div>
</div>
