<?php
/**
 * Plugin Name: Resenha Sagrada Bolão
 * Description: Adiciona bandeiras aos confrontos do bolão no front-end sem alterar palpites, pontuação ou banco de dados.
 * Version: 1.0.2
 * Author: Resenha Sagrada
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RSB_Bolao_Flags {
    private const VERSION = '1.0.2';

    /**
     * Confrontos solicitados para a oitava de finais.
     *
     * Estes dados são expostos apenas para o JavaScript de apresentação. O plugin não
     * cria jogos, não atualiza palpites e não recalcula pontuações no banco de dados.
     */
    private const MATCHES = [
        ['date' => '2026-07-09', 'time' => '17:00', 'home' => 'França', 'away' => 'Marrocos'],
        ['date' => '2026-07-10', 'time' => '16:00', 'home' => 'Espanha', 'away' => 'Bélgica'],
        ['date' => '2026-07-11', 'time' => '18:00', 'home' => 'Noruega', 'away' => 'Inglaterra'],
        ['date' => '2026-07-11', 'time' => '22:00', 'home' => 'Argentina', 'away' => 'Suíça'],
    ];

    private const FLAGS = [
        'Argentina' => '🇦🇷',
        'Bélgica' => '🇧🇪',
        'Espanha' => '🇪🇸',
        'França' => '🇫🇷',
        'Inglaterra' => '🏴',
        'Marrocos' => '🇲🇦',
        'Noruega' => '🇳🇴',
        'Suíça' => '🇨🇭',
    ];

    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function enqueue_assets(): void {
        if (is_admin()) {
            return;
        }

        $base_url = plugin_dir_url(__FILE__);
        wp_enqueue_style('rsb-bolao-flags', $base_url . 'assets/rsb-bolao-flags.css', [], self::VERSION);
        wp_enqueue_script('rsb-bolao-flags', $base_url . 'assets/rsb-bolao-flags.js', [], self::VERSION, true);
        wp_localize_script('rsb-bolao-flags', 'RSBBolaoFlags', [
            'flags' => self::FLAGS,
            'matches' => self::MATCHES,
        ]);
    }
}

RSB_Bolao_Flags::init();
