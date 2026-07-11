<?php
/**
 * Plugin Name: RSB - Atualizar Confrontos Imediato
 * Description: Atualiza de forma segura os confrontos M097 a M100 do Resenha Sagrada Bolão sem mexer em placares, palpites, pontuações ou ranking.
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) { exit; }

function rsb_confrontos_imediatos_payload(): array {
    return [
        'M097' => ['fase'=>'Mata-mata - Quartas','grupo'=>'','rodada'=>'Quartas de final','data_jogo'=>'2026-07-09','hora_jogo'=>'17:00:00','time_mandante'=>'França','time_visitante'=>'Marrocos','local_jogo'=>'Foxborough, Estados Unidos','status'=>'agendado'],
        'M098' => ['fase'=>'Mata-mata - Quartas','grupo'=>'','rodada'=>'Quartas de final','data_jogo'=>'2026-07-10','hora_jogo'=>'16:00:00','time_mandante'=>'Espanha','time_visitante'=>'Bélgica','local_jogo'=>'Los Angeles, Estados Unidos','status'=>'agendado'],
        'M099' => ['fase'=>'Mata-mata - Quartas','grupo'=>'','rodada'=>'Quartas de final','data_jogo'=>'2026-07-11','hora_jogo'=>'18:00:00','time_mandante'=>'Noruega','time_visitante'=>'Inglaterra','local_jogo'=>'Miami, Estados Unidos','status'=>'agendado'],
        'M100' => ['fase'=>'Mata-mata - Quartas','grupo'=>'','rodada'=>'Quartas de final','data_jogo'=>'2026-07-11','hora_jogo'=>'22:00:00','time_mandante'=>'Argentina','time_visitante'=>'Suíça','local_jogo'=>'Kansas City, Estados Unidos','status'=>'agendado'],
    ];
}

function rsb_confrontos_imediatos_find_jogos_table(): string {
    global $wpdb;
    if (function_exists('rsb_table')) {
        $table = rsb_table('jogos');
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists === $table) { return $table; }
    }
    $candidates = $wpdb->get_col("SHOW TABLES LIKE '%rsb%jogos%'");
    foreach ((array) $candidates as $table) {
        $codigo = $wpdb->get_var("SHOW COLUMNS FROM `" . esc_sql($table) . "` LIKE 'codigo_jogo'");
        if ($codigo) { return $table; }
    }
    $candidates = $wpdb->get_col("SHOW TABLES LIKE '%bolao%jogos%'");
    foreach ((array) $candidates as $table) {
        $codigo = $wpdb->get_var("SHOW COLUMNS FROM `" . esc_sql($table) . "` LIKE 'codigo_jogo'");
        if ($codigo) { return $table; }
    }
    return '';
}

function rsb_confrontos_imediatos_run(): array {
    global $wpdb;
    $table = rsb_confrontos_imediatos_find_jogos_table();
    if ($table === '') {
        return ['ok'=>false, 'message'=>'Tabela de jogos não encontrada.'];
    }

    $updated = 0;
    $missing = [];
    $now = current_time('mysql');

    foreach (rsb_confrontos_imediatos_payload() as $codigo => $game) {
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id FROM `$table` WHERE codigo_jogo = %s", $codigo));
        if (!$rows) {
            $missing[] = $codigo;
            continue;
        }

        foreach ($rows as $row) {
            $payload = $game;
            $payload['updated_at'] = $now;
            $wpdb->update($table, $payload, ['id' => (int) $row->id]);
            $updated++;
        }
    }

    update_option('rsb_confrontos_imediatos_20260709_result', [
        'executed_at' => $now,
        'table' => $table,
        'updated' => $updated,
        'missing' => $missing,
    ], false);

    return ['ok'=>true, 'table'=>$table, 'updated'=>$updated, 'missing'=>$missing];
}

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) { return; }
    if (get_option('rsb_confrontos_imediatos_20260709_done')) { return; }
    $result = rsb_confrontos_imediatos_run();
    if (!empty($result['ok'])) {
        update_option('rsb_confrontos_imediatos_20260709_done', 1, false);
    }
});

add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) { return; }
    $result = get_option('rsb_confrontos_imediatos_20260709_result');
    if (!$result) { return; }
    echo '<div class="notice notice-success"><p><strong>RSB:</strong> confrontos M097 a M100 atualizados. Linhas atualizadas: ' . esc_html((string) ($result['updated'] ?? 0)) . '.</p></div>';
});
