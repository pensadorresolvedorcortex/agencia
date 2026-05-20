<?php

define('ABSPATH', __DIR__ . '/');

global $nafb_options, $nafb_post_meta, $nafb_actions, $nafb_transients, $nafb_products, $nafb_cart_items;
$nafb_options = [];
$nafb_post_meta = [];
$nafb_actions = [];
$nafb_transients = [];
$nafb_products = [];
$nafb_cart_items = [];

function __(string $text): string { return $text; }
function esc_html__(string $text): string { return $text; }
function sanitize_key(string $key): string { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)); }
function sanitize_text_field(string $text): string { return trim(strip_tags($text)); }
function absint($v): int { return abs((int) $v); }
function wp_unslash($v) { return $v; }
function wp_json_encode($v): string { return json_encode($v); }
function wc_string_to_bool($v): bool { return in_array($v, ['yes', '1', 1, true], true); }
function sanitize_title(string $v): string { return trim(strtolower(str_replace(' ', '-', $v))); }
function get_current_user_id(): int { return 1; }
function is_admin(): bool { return true; }
function current_user_can(): bool { return true; }
function wp_verify_nonce(): bool { return true; }

function get_option(string $k, $default = false) { global $nafb_options; return $nafb_options[$k] ?? $default; }
function update_option(string $k, $v): bool { global $nafb_options; $nafb_options[$k] = $v; return true; }
function add_option(string $k, $v): bool { global $nafb_options; $nafb_options[$k] = $v; return true; }
function delete_option(string $k): bool { global $nafb_options; unset($nafb_options[$k]); return true; }
function get_post_meta(int $id, string $key) { global $nafb_post_meta; return $nafb_post_meta[$id][$key] ?? null; }
function update_post_meta(int $id, string $key, $value): bool { global $nafb_post_meta; $nafb_post_meta[$id][$key] = $value; return true; }
function set_transient(string $k, $v, int $ttl = 0): bool { global $nafb_transients; $nafb_transients[$k] = $v; return true; }
function get_transient(string $k) { global $nafb_transients; return $nafb_transients[$k] ?? false; }
function delete_transient(string $k): bool { global $nafb_transients; unset($nafb_transients[$k]); return true; }

function do_action(string $hook, $payload = null): void { global $nafb_actions; $nafb_actions[] = [$hook, $payload]; }
function add_action(): void {}
function add_filter(): void {}

class NAFB_Test_Product {
    public int $id; public int $parentId; public bool $inStock = true; public bool $manageStock = false; public ?int $stockQty = null;
    public function __construct(int $id, int $parentId, string $name = 'Var') { $this->id = $id; $this->parentId = $parentId; $this->name = $name; }
    public function is_type(string $type): bool { return $type === 'variation'; }
    public function get_parent_id(): int { return $this->parentId; }
    public function is_in_stock(): bool { return $this->inStock; }
    public function managing_stock(): bool { return $this->manageStock; }
    public function get_stock_quantity(): ?int { return $this->stockQty; }
    public function get_name(): string { return $this->name; }
    public function get_variation_attributes(): array { return []; }
}

function wc_get_product(int $id) { global $nafb_products; return $nafb_products[$id] ?? null; }

class NAFB_Test_Cart {
    public array $added = [];
    public function add_to_cart($productId, $qty, $variationId, $attrs, $data) {
        $key = 'cart_' . (count($this->added) + 1);
        $this->added[$key] = compact('productId', 'qty', 'variationId', 'attrs', 'data');
        return $key;
    }
}

class NAFB_Test_WC { public $cart; public function __construct() { $this->cart = new NAFB_Test_Cart(); }}
function WC() { static $wc; if (!$wc) { $wc = new NAFB_Test_WC(); } return $wc; }

class NAFB_Test_Order_Item_Product {
    public array $meta = [];
    public function add_meta_data($key, $value): void { $this->meta[$key] = $value; }
}
class_alias('NAFB_Test_Order_Item_Product', 'WC_Order_Item_Product');

$base = dirname(__DIR__);
require_once $base . '/includes/Domain/ValidationResult.php';
require_once $base . '/includes/Core/Logger.php';
require_once $base . '/includes/Infrastructure/Repository/ConfigRepositoryInterface.php';
require_once $base . '/includes/Infrastructure/Support/DataIntegrityGuard.php';
require_once $base . '/includes/Infrastructure/Repository/PostMetaConfigRepository.php';
require_once $base . '/includes/Application/ValidateSelectionService.php';
require_once $base . '/includes/Application/BatchAddToCartService.php';
require_once $base . '/includes/Application/OrderMetaPersisterService.php';
require_once $base . '/includes/Presentation/Admin/ProductTabRenderer.php';

function nafb_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "Assertion failed: {$message}\n");
        exit(1);
    }
}
