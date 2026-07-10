<?php
/**
 * Plugin Name: Resenha Sagrada Bolão
 * Description: Exibe bandeiras das seleções nos confrontos do bolão sem alterar tabelas, palpites ou pontuação.
 * Version: 1.0.1
 * Author: Resenha Sagrada
 */

if (!defined('ABSPATH')) {
    exit;
}

final class RSB_Bolao_Flags {
    private const VERSION = '1.0.1';

    /**
     * Jogos solicitados para conferência/cadastro visual. Estes dados são usados apenas
     * para referência de apresentação e não escrevem nem recalculam pontuações no banco.
     */
    private const FEATURED_MATCHES = [
        ['date' => '2026-07-09', 'time' => '17:00', 'home' => 'França', 'away' => 'Marrocos'],
        ['date' => '2026-07-10', 'time' => '16:00', 'home' => 'Espanha', 'away' => 'Bélgica'],
        ['date' => '2026-07-11', 'time' => '18:00', 'home' => 'Noruega', 'away' => 'Inglaterra'],
        ['date' => '2026-07-11', 'time' => '22:00', 'home' => 'Argentina', 'away' => 'Suíça'],
    ];

    private const FLAGS = [
        'argentina' => '🇦🇷',
        'belgica' => '🇧🇪',
        'bélgica' => '🇧🇪',
        'espanha' => '🇪🇸',
        'franca' => '🇫🇷',
        'frança' => '🇫🇷',
        'inglaterra' => '🏴',
        'marrocos' => '🇲🇦',
        'noruega' => '🇳🇴',
        'suica' => '🇨🇭',
        'suíça' => '🇨🇭',
    ];

    public static function init(): void {
        add_filter('the_content', [__CLASS__, 'append_flags_to_matchups'], 20);
        add_filter('widget_text', [__CLASS__, 'append_flags_to_matchups'], 20);
        add_filter('widget_text_content', [__CLASS__, 'append_flags_to_matchups'], 20);
        add_shortcode('rsb_jogos_bandeiras', [__CLASS__, 'render_featured_matches']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }

    public static function enqueue_styles(): void {
        wp_register_style('rsb-bolao-flags', false, [], self::VERSION);
        wp_enqueue_style('rsb-bolao-flags');
        wp_add_inline_style('rsb-bolao-flags', '.rsb-team-flag{display:inline-block;margin:0 .25rem;font-size:1.15em;line-height:1;vertical-align:-.08em}.rsb-match-flags{display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap}.rsb-featured-matches{display:grid;gap:.75rem;margin:1rem 0}.rsb-featured-match{padding:.85rem 1rem;border:1px solid rgba(0,0,0,.12);border-radius:.75rem;background:#fff}.rsb-featured-match__date{display:block;margin-bottom:.35rem;font-size:.9em;opacity:.75}');
    }

    public static function append_flags_to_matchups(string $content): string {
        if ($content === '' || is_admin()) {
            return $content;
        }

        foreach (self::FLAGS as $team => $flag) {
            $content = self::append_flag_to_team($content, $team, $flag);
        }

        return $content;
    }

    private static function append_flag_to_team(string $content, string $team, string $flag): string {
        $quoted = preg_quote($team, '/');
        return preg_replace_callback('/(?<![\pL])(' . $quoted . ')(?![\pL])(?!\s*' . preg_quote($flag, '/') . ')/iu', static function (array $matches) use ($flag): string {
            return $matches[1] . ' <span class="rsb-team-flag" aria-hidden="true">' . esc_html($flag) . '</span>';
        }, $content) ?? $content;
    }

    public static function render_featured_matches(): string {
        $items = [];
        foreach (self::FEATURED_MATCHES as $match) {
            $date = date_i18n('l, d \d\e F \d\e Y', strtotime($match['date']));
            $items[] = sprintf(
                '<div class="rsb-featured-match"><span class="rsb-featured-match__date">%s às %s</span><strong class="rsb-match-flags">%s</strong></div>',
                esc_html($date),
                esc_html(substr($match['time'], 0, 5)),
                wp_kses_post(self::format_matchup($match['home'], $match['away']))
            );
        }

        return '<div class="rsb-featured-matches">' . implode('', $items) . '</div>';
    }

    private static function format_matchup(string $home, string $away): string {
        return esc_html($home) . ' <span class="rsb-team-flag" aria-hidden="true">' . esc_html(self::flag_for($home)) . '</span> x ' . esc_html($away) . ' <span class="rsb-team-flag" aria-hidden="true">' . esc_html(self::flag_for($away)) . '</span>';
    }

    private static function flag_for(string $team): string {
        $key = strtolower(remove_accents($team));
        return self::FLAGS[$key] ?? self::FLAGS[strtolower($team)] ?? '';
    }
}

RSB_Bolao_Flags::init();
