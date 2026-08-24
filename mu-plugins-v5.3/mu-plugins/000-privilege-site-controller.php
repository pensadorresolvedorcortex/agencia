<?php
/**
 * Plugin Name: Privilege Site Controller
 * Description: Controlador MU das customizações e otimizações da Agência Privilége.
 * Version: 5.3.0-flyingpress
 * Author: Agência Privilége
 *
 * Stack de performance: FlyingPress.
 *
 * V5.0: reescrita do módulo de performance após auditoria (código + PSI +
 * HAR real) — ver AUDIT-README.txt. Novo módulo admin-dashboard.php com o
 * resumo da auditoria e a ação manual de maior impacto.
 *
 * V5.1: segunda rodada de auditoria, desta vez com PSI mobile + desktop
 * (a primeira só tinha mobile). Corrige o logo do header chegando com
 * loading=lazy/fetchpriority=low aplicados pelo WordPress core (maior causa
 * de CLS no desktop), adiciona preload do hero com background-image inline
 * do Bold Builder (invisível para a heurística fetchpriority=high por não
 * ser uma tag <img>), cobre o link do logo com class="logo" puro do desktop
 * (aria-label) e pré-aquece os assets gerados antes do preload da Home.
 *
 * V5.2: terceira rodada — CLS do desktop piorou (0,216→0,784), desta vez
 * pela imagem de topo do Bold Builder (bt_bb_section_top_section_coverage_image),
 * mesmo com width/height no HTML. Aplicado aspect-ratio dinâmico como
 * correção parcial/conservadora; painel de administração ganhou uma seção
 * que extrai a regra CSS real do tema para essa classe, porque resolver
 * isso com certeza depende de ver o CSS que não está neste pacote de
 * mu-plugins. Ver AUDIT-README.txt.
 *
 * V5.3: quarta rodada CONFIRMOU a correção da V5.2 — desktop 72→97,
 * CLS 0,784→0,003, sem precisar de nenhum ajuste adicional. Sem mudança de
 * lógica de performance nesta versão: painel de administração atualizado
 * (a notice de CLS crítico virou confirmação de resolução, com os números
 * antes/depois) e documentados 3 achados avaliados e conscientemente NÃO
 * alterados (rate-limit do Cloudflare Turnstile, JS interno do Bold
 * Builder, checkbox de fetchpriority do hero em background-image) — cada
 * um com o motivo técnico específico de não ser seguro ou possível de
 * corrigir por código. RUCSS segue como única ação de alto impacto
 * pendente, agora com o gargalo do LCP mobile mais claramente atribuído a
 * ela. Ver AUDIT-README.txt.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( defined( 'PRIVILEGE_SITE_CONTROLLER_LOADED' ) ) {
    return;
}

define( 'PRIVILEGE_SITE_CONTROLLER_LOADED', true );
define( 'PRIVILEGE_SITE_CONTROLLER_VERSION', '5.3.0-flyingpress' );

$privilege_site_controller_modules = array(
    'site-customizations.php',
    'performance-flyingpress.php',
    'admin-dashboard.php',
    'bot-whatsapp.php',
);

foreach ( $privilege_site_controller_modules as $privilege_site_controller_module ) {
    $privilege_site_controller_file =
        __DIR__ . '/privilege-site-controller/modules/' . $privilege_site_controller_module;

    if ( is_readable( $privilege_site_controller_file ) ) {
        require_once $privilege_site_controller_file;
    }
}
