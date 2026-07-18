<?php
/**
 * Plugin Name: Resenha Sagrada Bolão
 * Description: Anexa bandeiras às seleções dos confrontos informados sem alterar tabelas, palpites ou pontuações do bolão.
 * Version: 1.0.1
 * Author: Resenha Sagrada
 */

if (! defined('ABSPATH')) {
    exit;
}

final class RSB_Flags_Update
{
    private const VERSION = '1.0.1';

    /**
     * Atualização pontual solicitada para as oitavas: somente exibição.
     * Não cria tabelas, não atualiza opções e não toca nos registros/pontuação dos palpites.
     */
    private const FLAGS = [
        'França' => '🇫🇷',
        'Marrocos' => '🇲🇦',
        'Espanha' => '🇪🇸',
        'Bélgica' => '🇧🇪',
        'Noruega' => '🇳🇴',
        'Inglaterra' => '🏴',
        'Argentina' => '🇦🇷',
        'Suíça' => '🇨🇭',
    ];

    private const FIXTURES = [
        ['date' => '2026-07-09', 'time' => '17h', 'home' => 'França', 'away' => 'Marrocos'],
        ['date' => '2026-07-10', 'time' => '16h', 'home' => 'Espanha', 'away' => 'Bélgica'],
        ['date' => '2026-07-11', 'time' => '18h', 'home' => 'Noruega', 'away' => 'Inglaterra'],
        ['date' => '2026-07-11', 'time' => '22h', 'home' => 'Argentina', 'away' => 'Suíça'],
    ];

    public static function boot(): void
    {
        add_filter('the_content', [__CLASS__, 'append_flags_to_fixture_names'], 20);
        add_filter('widget_text_content', [__CLASS__, 'append_flags_to_fixture_names'], 20);
    }

    public static function append_flags_to_fixture_names(string $content): string
    {
        if ($content === '') {
            return $content;
        }

        foreach (self::FIXTURES as $fixture) {
            $content = self::append_flag_for_team($content, $fixture['home']);
            $content = self::append_flag_for_team($content, $fixture['away']);
        }

        return $content;
    }

    private static function append_flag_for_team(string $content, string $team): string
    {
        $flag = self::FLAGS[$team] ?? '';
        if ($flag === '') {
            return $content;
        }

        $pattern = '/(?<![\pL])(' . preg_quote($team, '/') . ')(?!\s*' . preg_quote($flag, '/') . ')(?![\pL])/u';

        return (string) preg_replace($pattern, '$1 ' . $flag, $content);
    }

    public static function fixtures(): array
    {
        return self::FIXTURES;
    }
}

RSB_Flags_Update::boot();
