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
    'Confirme seu Acesso', 'logo-1.png', 'Grupos WhatsApp - Todos os Direitos Reservados', 'background:#eaeaea', 'font-family:Maven Pro,Arial,sans-serif!important',
    'Confirmar e continuar',
] as $rule) if (strpos($php, $rule) === false) throw new RuntimeException('Missing OTP flow contract: ' . $rule);
if (substr_count($php, 'font-family:Maven Pro,Arial,sans-serif!important') < 2) throw new RuntimeException('OTP email must force Maven Pro on body and wrapper.');
if (strpos($php, 'Autenticação em 2 fatores') !== false || strpos($php, 'Seu código de segurança — Portal Grupos WhatsApp') !== false || strpos($php, 'Portal independente, seguro e moderado') !== false) throw new RuntimeException('Legacy OTP branding must be removed.');
foreach (['.pgw-auth--otp', '.pgw-otp-wrap{background:#eaeaea}', '.pgw-auth--otp:before{height:0', '.pgw-otp-logo', '.pgw-otp-code input', '.pgw-otp-actions', 'grid-template-columns:1.2fr .8fr'] as $rule) if (strpos($css, $rule) === false) throw new RuntimeException('Missing OTP CSS contract: ' . $rule);
echo "OtpFlowUxTest passed\n";
