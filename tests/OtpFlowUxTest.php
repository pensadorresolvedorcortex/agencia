<?php
$php = file_get_contents(dirname(__DIR__) . '/portal-grupos-whatsapp.php');
$css = file_get_contents(dirname(__DIR__) . '/assets/dist/frontend.css');
foreach ([
    'process_otp_submission',
    "add_action('template_redirect', [self::class, 'process_otp_submission'], 1)",
    'auth_flow_url(int $user_id, string $purpose, ?string $redirect = null)',
    'if ($redirect !== \'\') $args[\'pgw_redirect_to\'] = $redirect;',
    'consume_pending_redirect(int $user_id, ?string $fallback = null)',
    'otp_redirect_from_request',
    'safe_post_auth_redirect',
    'is_auth_destination',
    'pgw_auth_return',
    'auth_page_slugs',
    'name="pgw_redirect_to" value="',
    'html_mail_content_type',
    'otp_email_html',
    'wp_mail_content_type',
    'Autenticação em 2 fatores',
    'Confirmar e continuar',
] as $rule) if (strpos($php, $rule) === false) throw new RuntimeException('Missing OTP flow contract: ' . $rule);
foreach (['.pgw-auth--otp', '.pgw-otp-code input', '.pgw-otp-actions', 'grid-template-columns:1.2fr .8fr'] as $rule) if (strpos($css, $rule) === false) throw new RuntimeException('Missing OTP CSS contract: ' . $rule);
echo "OtpFlowUxTest passed\n";
