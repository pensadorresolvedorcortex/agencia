<?php
/**
 * Plugin Name: Resenha Sagrada Bolão
 * Description: Relatórios administrativos por partida para o bolão Resenha Sagrada.
 * Version: 1.1.0
 * Author: Resenha Sagrada
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('RSB_Bolao_Admin_Relatorios')) {
    final class RSB_Bolao_Admin_Relatorios
    {
        private const MENU_SLUG = 'rsb-relatorios';

        public static function init()
        {
            add_action('admin_menu', array(__CLASS__, 'register_menu'));
            add_action('admin_post_rsb_relatorio_partida_pdf', array(__CLASS__, 'render_pdf_view'));
        }

        public static function register_menu()
        {
            if (! self::can_manage()) {
                return;
            }

            add_menu_page(
                'Relatórios',
                'Resenha Sagrada',
                'manage_options',
                self::MENU_SLUG,
                array(__CLASS__, 'render_dashboard'),
                'dashicons-awards',
                3
            );

            add_submenu_page(
                self::MENU_SLUG,
                'Relatórios',
                'Relatórios',
                'manage_options',
                self::MENU_SLUG,
                array(__CLASS__, 'render_dashboard')
            );
        }

        public static function render_dashboard()
        {
            if (! self::can_manage()) {
                wp_die(esc_html__('Acesso negado.', 'resenha-sagrada-bolao'));
            }

            $matches = self::matches();
            ?>
            <div class="wrap rsb-admin-wrap">
                <style><?php echo self::admin_css(); ?></style>
                <section class="rsb-glass-shell">
                    <div class="rsb-hero">
                        <div class="rsb-brand">
                            <div class="rsb-logo">RS</div>
                            <div>
                                <h1>RELATÓRIOS</h1>
                                <span>Resenha Sagrada</span>
                            </div>
                        </div>
                    </div>
                    <div class="rsb-match-grid">
                        <?php foreach ($matches as $match) :
                            $stats = self::match_stats($match);
                            $pdf_url = wp_nonce_url(
                                admin_url('admin-post.php?action=rsb_relatorio_partida_pdf&match=' . rawurlencode($match['id'])),
                                'rsb_relatorio_partida_pdf_' . $match['id']
                            );
                            ?>
                            <article class="rsb-match-card">
                                <div class="rsb-match-date"><?php echo esc_html(self::format_date($match['date']) . ' • ' . $match['time']); ?></div>
                                <div class="rsb-versus">
                                    <?php echo self::team_html($match['home']); ?>
                                    <strong>x</strong>
                                    <?php echo self::team_html($match['away']); ?>
                                </div>
                                <div class="rsb-stats">
                                    <span><b><?php echo esc_html((string) $stats['palpites']); ?></b> Palpites</span>
                                    <span><b><?php echo esc_html((string) $stats['participantes']); ?></b> Participantes</span>
                                    <span><b><?php echo esc_html((string) $stats['placares']); ?></b> Placar exato</span>
                                </div>
                                <a class="rsb-pdf-button" href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener">Gerar PDF</a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
            <?php
        }

        public static function render_pdf_view()
        {
            if (! self::can_manage()) {
                wp_die(esc_html__('Acesso negado.', 'resenha-sagrada-bolao'));
            }

            $match_id = isset($_GET['match']) ? sanitize_key((string) wp_unslash($_GET['match'])) : '';
            check_admin_referer('rsb_relatorio_partida_pdf_' . $match_id);

            $match = self::find_match($match_id);
            if ($match === null) {
                wp_die(esc_html__('Partida não encontrada.', 'resenha-sagrada-bolao'));
            }

            $stats = self::match_stats($match);
            $rows = self::prediction_rows($match);

            nocache_headers();
            header('Content-Type: text/html; charset=' . get_option('blog_charset'));
            ?>
            <!doctype html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo('charset'); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title><?php echo esc_html('Relatório - ' . $match['home']['name'] . ' x ' . $match['away']['name']); ?></title>
                <style><?php echo self::pdf_css(); ?></style>
            </head>
            <body>
                <main class="rsb-pdf-page">
                    <section class="rsb-pdf-hero">
                        <div class="rsb-pdf-brand"><div class="rsb-pdf-logo">RS</div><div><span>RESENHA SAGRADA</span><h1>Relatório da Partida</h1></div></div>
                        <button onclick="window.print()">Salvar PDF</button>
                    </section>
                    <section class="rsb-pdf-card rsb-pdf-scoreboard">
                        <div><?php echo self::team_html($match['home'], 'pdf'); ?></div>
                        <strong>x</strong>
                        <div><?php echo self::team_html($match['away'], 'pdf'); ?></div>
                        <span><?php echo esc_html(self::format_date($match['date']) . ' • ' . $match['time']); ?></span>
                    </section>
                    <section class="rsb-pdf-kpis">
                        <div><b><?php echo esc_html((string) $stats['palpites']); ?></b><span>Palpites</span></div>
                        <div><b><?php echo esc_html((string) $stats['participantes']); ?></b><span>Participantes</span></div>
                        <div><b><?php echo esc_html((string) $stats['mandante']); ?></b><span><?php echo esc_html($match['home']['name']); ?></span></div>
                        <div><b><?php echo esc_html((string) $stats['empate']); ?></b><span>Empate</span></div>
                        <div><b><?php echo esc_html((string) $stats['visitante']); ?></b><span><?php echo esc_html($match['away']['name']); ?></span></div>
                    </section>
                    <section class="rsb-pdf-card">
                        <table>
                            <thead><tr><th>Participante</th><th>Palpite</th><th>Resultado</th><th>Pontos</th></tr></thead>
                            <tbody>
                            <?php if (empty($rows)) : ?>
                                <tr><td colspan="4">Sem palpites registrados para esta partida.</td></tr>
                            <?php else : foreach ($rows as $row) : ?>
                                <tr>
                                    <td><?php echo esc_html($row['participante']); ?></td>
                                    <td><?php echo esc_html($row['palpite']); ?></td>
                                    <td><?php echo esc_html($row['resultado']); ?></td>
                                    <td><?php echo esc_html($row['pontos']); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </section>
                </main>
                <script>window.addEventListener('load',function(){setTimeout(function(){window.print();},250);});</script>
            </body>
            </html>
            <?php
            exit;
        }

        private static function can_manage()
        {
            return is_super_admin() || current_user_can('manage_options');
        }

        private static function matches()
        {
            $matches = array(
                array('id' => 'franca-marrocos-2026-07-09-17', 'date' => '2026-07-09', 'time' => '17h', 'home' => self::team('França', '🇫🇷'), 'away' => self::team('Marrocos', '🇲🇦')),
                array('id' => 'espanha-belgica-2026-07-10-16', 'date' => '2026-07-10', 'time' => '16h', 'home' => self::team('Espanha', '🇪🇸'), 'away' => self::team('Bélgica', '🇧🇪')),
                array('id' => 'noruega-inglaterra-2026-07-11-18', 'date' => '2026-07-11', 'time' => '18h', 'home' => self::team('Noruega', '🇳🇴'), 'away' => self::team('Inglaterra', '🏴'),),
                array('id' => 'argentina-suica-2026-07-11-22', 'date' => '2026-07-11', 'time' => '22h', 'home' => self::team('Argentina', '🇦🇷'), 'away' => self::team('Suíça', '🇨🇭')),
            );

            return apply_filters('rsb_bolao_report_matches', $matches);
        }

        private static function find_match($match_id)
        {
            foreach (self::matches() as $match) {
                if ($match['id'] === $match_id) {
                    return $match;
                }
            }

            return null;
        }

        private static function team($name, $flag)
        {
            return array('name' => $name, 'flag' => $flag);
        }

        private static function team_html($team, $context = 'admin')
        {
            $class = $context === 'pdf' ? 'rsb-pdf-team' : 'rsb-team';
            return '<span class="' . esc_attr($class) . '"><span>' . esc_html($team['flag']) . '</span><em>' . esc_html($team['name']) . '</em></span>';
        }

        private static function match_stats($match)
        {
            $rows = self::prediction_rows($match);
            $stats = array('palpites' => count($rows), 'participantes' => 0, 'placares' => 0, 'mandante' => 0, 'empate' => 0, 'visitante' => 0);
            $participants = array();

            foreach ($rows as $row) {
                $participants[$row['participante']] = true;
                if ($row['resultado'] === 'Mandante') {
                    $stats['mandante']++;
                } elseif ($row['resultado'] === 'Empate') {
                    $stats['empate']++;
                } elseif ($row['resultado'] === 'Visitante') {
                    $stats['visitante']++;
                }
                if ($row['pontos'] !== '-' && (int) $row['pontos'] >= 10) {
                    $stats['placares']++;
                }
            }

            $stats['participantes'] = count($participants);
            return $stats;
        }

        private static function prediction_rows($match)
        {
            global $wpdb;

            $rows = apply_filters('rsb_bolao_report_prediction_rows', null, $match);
            if (is_array($rows)) {
                return $rows;
            }

            $tables = array($wpdb->prefix . 'rsb_palpites', $wpdb->prefix . 'bolao_palpites', $wpdb->prefix . 'palpites');
            foreach ($tables as $table) {
                $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
                if ($exists !== $table) {
                    continue;
                }

                $columns = $wpdb->get_col("DESCRIBE {$table}", 0);
                $match_column = self::first_existing($columns, array('match_id', 'partida_id', 'jogo_id', 'game_id'));
                if ($match_column === '') {
                    continue;
                }

                $user_column = self::first_existing($columns, array('participante', 'nome', 'user_name', 'usuario', 'user_id'));
                $home_column = self::first_existing($columns, array('home_score', 'mandante_gols', 'gols_mandante', 'placar_casa'));
                $away_column = self::first_existing($columns, array('away_score', 'visitante_gols', 'gols_visitante', 'placar_fora'));
                $points_column = self::first_existing($columns, array('pontos', 'points', 'score'));

                $db_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$match_column} = %s ORDER BY id DESC LIMIT 500", $match['id']), ARRAY_A);
                return self::normalize_rows($db_rows, $user_column, $home_column, $away_column, $points_column);
            }

            return array();
        }

        private static function normalize_rows($db_rows, $user_column, $home_column, $away_column, $points_column)
        {
            $rows = array();
            foreach ((array) $db_rows as $db_row) {
                $home = $home_column !== '' && isset($db_row[$home_column]) ? (string) $db_row[$home_column] : '';
                $away = $away_column !== '' && isset($db_row[$away_column]) ? (string) $db_row[$away_column] : '';
                $rows[] = array(
                    'participante' => $user_column !== '' && isset($db_row[$user_column]) ? (string) $db_row[$user_column] : 'Participante',
                    'palpite' => $home !== '' || $away !== '' ? $home . ' x ' . $away : '-',
                    'resultado' => self::guess_result($home, $away),
                    'pontos' => $points_column !== '' && isset($db_row[$points_column]) ? (string) $db_row[$points_column] : '-',
                );
            }
            return $rows;
        }

        private static function first_existing($columns, $candidates)
        {
            foreach ($candidates as $candidate) {
                if (in_array($candidate, (array) $columns, true)) {
                    return $candidate;
                }
            }
            return '';
        }

        private static function guess_result($home, $away)
        {
            if ($home === '' || $away === '' || ! is_numeric($home) || ! is_numeric($away)) {
                return '-';
            }
            if ((int) $home > (int) $away) {
                return 'Mandante';
            }
            if ((int) $home < (int) $away) {
                return 'Visitante';
            }
            return 'Empate';
        }

        private static function format_date($date)
        {
            $timestamp = strtotime($date . ' 00:00:00');
            return $timestamp ? date_i18n('d/m/Y', $timestamp) : $date;
        }

        private static function admin_css()
        {
            return '.rsb-admin-wrap{margin:0 20px 0 0}.rsb-glass-shell{min-height:650px;margin:22px 0;padding:32px;border-radius:20px;background:radial-gradient(circle at top left,rgba(122,196,64,.28),transparent 34%),linear-gradient(135deg,#071423 0%,#0d1d31 58%,#10263f 100%);box-shadow:0 24px 70px rgba(6,20,36,.28);color:#e9fff6}.rsb-hero{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}.rsb-brand{display:flex;gap:16px;align-items:center}.rsb-logo{width:64px;height:64px;border-radius:22px;display:grid;place-items:center;font-weight:900;color:#132013;background:linear-gradient(135deg,#ff8a00,#7ac440 55%,#00d084);box-shadow:0 12px 32px rgba(122,196,64,.28)}.rsb-brand h1{margin:0;color:#f8fff8;font-size:34px;letter-spacing:.04em}.rsb-brand span{color:#9fd0bc}.rsb-match-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px}.rsb-match-card{border:1px solid rgba(255,255,255,.18);border-radius:24px;padding:22px;background:linear-gradient(145deg,rgba(255,255,255,.16),rgba(255,255,255,.06));box-shadow:inset 0 1px 0 rgba(255,255,255,.2),0 18px 40px rgba(0,0,0,.22);backdrop-filter:blur(18px)}.rsb-match-date{color:#c9f4de;font-weight:700;margin-bottom:18px}.rsb-versus{display:flex;align-items:center;justify-content:space-between;gap:12px;font-size:24px}.rsb-versus strong{color:#ff9c23}.rsb-team{display:flex;align-items:center;gap:8px}.rsb-team span{font-size:30px}.rsb-team em{font-style:normal;font-weight:800;color:#fff}.rsb-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:22px 0}.rsb-stats span{padding:12px;border-radius:16px;background:rgba(255,255,255,.1);color:#bde7d2;text-align:center}.rsb-stats b{display:block;color:#fff;font-size:20px}.rsb-pdf-button{display:inline-flex;justify-content:center;width:100%;border-radius:16px;padding:13px 18px;background:linear-gradient(135deg,#ff8a00,#7ac440 55%,#00d084);color:#071423!important;text-decoration:none;font-weight:900;text-transform:uppercase;letter-spacing:.05em}';
        }

        private static function pdf_css()
        {
            return '@page{size:A4;margin:10mm}*{box-sizing:border-box}body{margin:0;background:#071423;color:#effff6;font-family:Inter,Arial,sans-serif}.rsb-pdf-page{min-height:100vh;padding:28px;background:radial-gradient(circle at 15% 0%,rgba(255,138,0,.26),transparent 30%),radial-gradient(circle at 90% 8%,rgba(0,208,132,.28),transparent 32%),linear-gradient(135deg,#071423,#0e2036)}.rsb-pdf-hero,.rsb-pdf-card,.rsb-pdf-kpis div{border:1px solid rgba(255,255,255,.18);background:linear-gradient(145deg,rgba(255,255,255,.16),rgba(255,255,255,.07));box-shadow:0 16px 40px rgba(0,0,0,.26);backdrop-filter:blur(18px)}.rsb-pdf-hero{display:flex;justify-content:space-between;align-items:center;border-radius:26px;padding:22px;margin-bottom:18px}.rsb-pdf-brand{display:flex;align-items:center;gap:16px}.rsb-pdf-logo{width:62px;height:62px;border-radius:20px;display:grid;place-items:center;font-weight:900;color:#102015;background:linear-gradient(135deg,#ff8a00,#7ac440 55%,#00d084)}.rsb-pdf-brand span{color:#b7ebce;font-weight:800;letter-spacing:.12em}.rsb-pdf-brand h1{margin:3px 0 0;font-size:30px}.rsb-pdf-hero button{border:0;border-radius:999px;padding:12px 18px;background:linear-gradient(135deg,#ff8a00,#00d084);font-weight:900;color:#071423}.rsb-pdf-scoreboard{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;text-align:center;border-radius:26px;padding:26px;margin-bottom:18px;position:relative}.rsb-pdf-scoreboard>strong{font-size:34px;color:#ff9c23}.rsb-pdf-scoreboard>span{grid-column:1/4;color:#bde7d2;margin-top:12px;font-weight:800}.rsb-pdf-team{display:inline-flex;align-items:center;justify-content:center;gap:12px;font-size:28px}.rsb-pdf-team span{font-size:40px}.rsb-pdf-team em{font-style:normal;font-weight:900}.rsb-pdf-kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:12px;margin-bottom:18px}.rsb-pdf-kpis div{border-radius:22px;padding:18px;text-align:center}.rsb-pdf-kpis b{display:block;font-size:26px;color:#fff}.rsb-pdf-kpis span{color:#bde7d2;font-weight:800}.rsb-pdf-card{border-radius:24px;padding:18px}table{width:100%;border-collapse:collapse;overflow:hidden;border-radius:18px}th,td{padding:12px;border-bottom:1px solid rgba(255,255,255,.13);text-align:left}th{color:#071423;background:linear-gradient(135deg,#ff8a00,#7ac440 55%,#00d084);font-weight:900}td{color:#eefcf6}@media print{body{background:#071423}.rsb-pdf-hero button{display:none}.rsb-pdf-page{padding:0}.rsb-pdf-hero,.rsb-pdf-card,.rsb-pdf-kpis div{box-shadow:none}}';
        }
    }

    RSB_Bolao_Admin_Relatorios::init();
}
