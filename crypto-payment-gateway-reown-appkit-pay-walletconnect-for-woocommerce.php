<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable Generic.Files.LineLength 

/**
 * Plugin Name: Reown Payment Gateway
 * Version:     1.0.2
 * Plugin URI:  https://beycanpress.com/
 * Description: Accept cryptocurrency payments in your WooCommerce store using Reown (WalletConnect) payment gateway.
 * Author:      BeycanPress LLC
 * Author URI:  https://beycanpress.com
 * License:     GPLv3
 * Text Domain: crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce
 * Domain Path: /languages
 * Tags:        crypto, cryptocurrency, bitcoin, ethereum, woocommerce, payment gateway, walletconnect, reown, appkit pay
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 8.1
 */


require __DIR__ . '/vendor/autoload.php';

define('REOWN_PAYMENT_GATEWAY_VERSION', '1.0.0');
define('REOWN_PAYMENT_GATEWAY_URL', plugin_dir_url(__FILE__));
define('REOWN_PAYMENT_GATEWAY_PATH', plugin_dir_path(__FILE__));

new BeycanPress\ReownPaymentGateway\OtherPlugins(__FILE__);

add_action('before_woocommerce_init', function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
    // @phpcs:disable
    $links[] = '<a href="https://1.envato.market/jejdYn" style="color: #389e38;font-weight: bold;" target="_blank">' . esc_html__('Buy Premium', 'cryptopay') . '</a>';
    // @phpcs:enable
    return $links;
});

add_action('plugins_loaded', function (): void {
    if (!defined('REOWN_PAYMENT_GATEWAY_PREMIUM')) {
        add_filter('woocommerce_payment_gateways', function ($gateways) {
            $gateways[] = \BeycanPress\ReownPaymentGateway\Gateway::class;
            return $gateways;
        });
        add_action('woocommerce_blocks_loaded', function (): void {
            if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
                add_action('woocommerce_blocks_payment_method_type_registration', function ($registry): void {
                    $registry->register(new BeycanPress\ReownPaymentGateway\BlocksGateway());
                });
            }
        });
    }
});
