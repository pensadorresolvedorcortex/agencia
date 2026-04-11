<?php

namespace NaFlorestaBuy\Core;

use NaFlorestaBuy\Application\BatchAddToCartService;
use NaFlorestaBuy\Application\ValidateSelectionService;
use NaFlorestaBuy\Infrastructure\Repository\PostMetaConfigRepository;
use NaFlorestaBuy\Infrastructure\Support\DataIntegrityGuard;
use NaFlorestaBuy\Infrastructure\Transport\AjaxController;
use NaFlorestaBuy\Infrastructure\Woo\AdminOrderHooks;
use NaFlorestaBuy\Infrastructure\Woo\CartHooks;
use NaFlorestaBuy\Infrastructure\Woo\CheckoutHooks;
use NaFlorestaBuy\Infrastructure\Woo\ProductHooks;
use NaFlorestaBuy\Presentation\Admin\ProductTabRenderer;
use NaFlorestaBuy\Presentation\Admin\SettingsPageRenderer;
use NaFlorestaBuy\Presentation\Front\ProductPageRenderer;

class Plugin
{
    public function boot(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }

        load_plugin_textdomain('nafloresta-buy', false, dirname(plugin_basename(NAFB_PLUGIN_FILE)) . '/languages');

        $container = $this->buildContainer();
        $compat = new Compatibility();
        $compat->declareHposCompatibility();

        $registrar = new HookRegistrar();
        $registrar->register([
            $container->get(AssetsManager::class),
            $container->get(ProductTabRenderer::class),
            $container->get(SettingsPageRenderer::class),
            $container->get(ProductPageRenderer::class),
            $container->get(AjaxController::class),
            $container->get(ProductHooks::class),
            $container->get(CartHooks::class),
            $container->get(CheckoutHooks::class),
            $container->get(AdminOrderHooks::class),
        ]);
    }

    private function buildContainer(): Container
    {
        $c = new Container();

        $c->set(AssetsManager::class, fn() => new AssetsManager());
        $c->set(Logger::class, fn() => new Logger());
        $c->set(DataIntegrityGuard::class, fn() => new DataIntegrityGuard());
        $c->set(PostMetaConfigRepository::class, fn(Container $c) => new PostMetaConfigRepository($c->get(DataIntegrityGuard::class)));
        $c->set(ValidateSelectionService::class, fn(Container $c) => new ValidateSelectionService($c->get(PostMetaConfigRepository::class), $c->get(Logger::class)));
        $c->set(BatchAddToCartService::class, fn(Container $c) => new BatchAddToCartService($c->get(ValidateSelectionService::class), $c->get(Logger::class)));
        $c->set(AjaxController::class, fn(Container $c) => new AjaxController($c->get(BatchAddToCartService::class), $c->get(Logger::class)));
        $c->set(ProductTabRenderer::class, fn(Container $c) => new ProductTabRenderer($c->get(PostMetaConfigRepository::class)));
        $c->set(ProductPageRenderer::class, fn(Container $c) => new ProductPageRenderer($c->get(PostMetaConfigRepository::class)));
        $c->set(SettingsPageRenderer::class, fn() => new SettingsPageRenderer());
        $c->set(ProductHooks::class, fn() => new ProductHooks());
        $c->set(CartHooks::class, fn(Container $c) => new CartHooks($c->get(DataIntegrityGuard::class)));
        $c->set(CheckoutHooks::class, fn() => new CheckoutHooks());
        $c->set(AdminOrderHooks::class, fn() => new AdminOrderHooks());

        return $c;
    }
}
