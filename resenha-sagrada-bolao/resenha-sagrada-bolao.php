<?php
/**
 * Plugin Name: Resenha Sagrada Bolão
 * Description: Dados dos confrontos do bolão com bandeiras das seleções, sem alterar pontuações de palpites.
 * Version: 1.0.1
 * Author: Resenha Sagrada
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! class_exists('RSB_Bolao_Flags')) {
    final class RSB_Bolao_Flags
    {
        /**
         * Confrontos liberados para inserção na fase atual.
         *
         * Importante: esta lista é apenas metadado de exibição/seleção dos jogos.
         * Ela não altera tabelas, metas ou cálculos de pontuação dos palpites existentes.
         *
         * @return array<int,array<string,mixed>>
         */
        public static function matches()
        {
            return array(
                array(
                    'date' => '2026-07-09',
                    'time' => '17:00',
                    'home' => self::team('França', 'FR'),
                    'away' => self::team('Marrocos', 'MA'),
                ),
                array(
                    'date' => '2026-07-10',
                    'time' => '16:00',
                    'home' => self::team('Espanha', 'ES'),
                    'away' => self::team('Bélgica', 'BE'),
                ),
                array(
                    'date' => '2026-07-11',
                    'time' => '18:00',
                    'home' => self::team('Noruega', 'NO'),
                    'away' => self::team('Inglaterra', 'GB-ENG'),
                ),
                array(
                    'date' => '2026-07-11',
                    'time' => '22:00',
                    'home' => self::team('Argentina', 'AR'),
                    'away' => self::team('Suíça', 'CH'),
                ),
            );
        }

        /**
         * Permite que o código existente do bolão consuma os confrontos sem criar shortcodes novos.
         *
         * @param array<int,array<string,mixed>> $matches Confrontos já montados pelo plugin.
         * @return array<int,array<string,mixed>>
         */
        public static function append_matches($matches)
        {
            if (! is_array($matches)) {
                $matches = array();
            }

            foreach (self::matches() as $match) {
                $matches[] = $match;
            }

            return $matches;
        }

        /**
         * Renderiza uma seleção com bandeira para os confrontos que já exibem nomes de times.
         *
         * @param string $name Nome da seleção.
         * @param string $code Código ISO/flag-icons da bandeira.
         * @return string
         */
        public static function render_team($name, $code = '')
        {
            $name = (string) $name;
            $code = (string) $code;
            $flag = self::flag_emoji($code);

            if ($flag === '') {
                return esc_html($name);
            }

            return '<span class="rsb-team-with-flag"><span class="rsb-team-flag" aria-hidden="true">' . esc_html($flag) . '</span> <span class="rsb-team-name">' . esc_html($name) . '</span></span>';
        }

        private static function team($name, $code)
        {
            return array(
                'name' => $name,
                'flag' => self::flag_emoji($code),
                'flag_code' => $code,
            );
        }

        private static function flag_emoji($code)
        {
            $flags = array(
                'AR' => '🇦🇷',
                'BE' => '🇧🇪',
                'CH' => '🇨🇭',
                'ES' => '🇪🇸',
                'FR' => '🇫🇷',
                'GB-ENG' => '🏴',
                'MA' => '🇲🇦',
                'NO' => '🇳🇴',
            );

            return isset($flags[$code]) ? $flags[$code] : '';
        }
    }

    add_filter('rsb_bolao_matches', array('RSB_Bolao_Flags', 'append_matches'));
    add_filter('resenha_sagrada_bolao_matches', array('RSB_Bolao_Flags', 'append_matches'));
    add_filter('rsb_bolao_render_team', array('RSB_Bolao_Flags', 'render_team'), 10, 2);
}
