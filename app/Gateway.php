<?php

declare(strict_types=1);

// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage

namespace BeycanPress\ReownPaymentGateway;

class Gateway extends \WC_Payment_Gateway
{
    /**
     * @var string
     */
    public const ID = 'reown';

    /**
     * @var string
     */
    // @phpcs:ignore
    public $id;

    /**
     * @var string
     */
    // @phpcs:ignore
    public $method_title;

    /**
     * @var string
     */
    // @phpcs:ignore
    public $method_description;

    /**
     * @var string
     */
    // @phpcs:ignore
    public $title;

    /**
     * @var string
     */
    // @phpcs:ignore
    public $description;

    /**
     * @var string
     */
    // @phpcs:ignore
    public $enabled;

    /**
     * @var string
     */
    // @phpcs:ignore
    public $order_button_text;

    /**
     * @var array<string>
     */
    // @phpcs:ignore
    public $supports;

    /**
     * @var array<mixed>
     */
    // @phpcs:ignore
    public $form_fields;

    /**
     * @return void
     */
    public function __construct()
    {
        $this->id = self::ID;
        $this->method_title = esc_html__('Reown Payment Gateway', 'reown-payment-gateway');
        $this->method_description = esc_html__('Reown Payment Gateway', 'reown-payment-gateway');

        // gateways can support subscriptions, refunds, saved payment methods,
        // but in this tutorial we begin with simple payments
        $this->supports = ['products'];

        // Method with all the options fields
        $this->init_form_fields();

        // Load the settings.
        /** @disregard */
        $this->init_settings();
        /** @disregard */
        $this->title = $this->get_option('title');
        /** @disregard */
        $this->enabled = $this->get_option('enabled');
        /** @disregard */
        $this->description = $this->get_option('description');
        /** @disregard */
        $this->order_button_text = $this->get_option('order_button_text');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * @return void
     */
    // @phpcs:ignore
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'       => esc_html__('Enable/Disable', 'reown-payment-gateway'),
                'label'       => esc_html__('Enable', 'reown-payment-gateway'),
                'type'        => 'checkbox',
                'default'     => 'no'
            ],
            'title' => [
                'title'       => esc_html__('Title', 'reown-payment-gateway'),
                'type'        => 'text',
                'description' => esc_html__('This controls the title which the user sees during checkout.', 'reown-payment-gateway'),
                'default'     => esc_html__('Pay with Reown (WalletConnect)', 'reown-payment-gateway')
            ],
            'description' => [
                'title'       => esc_html__('Description', 'reown-payment-gateway'),
                'type'        => 'textarea',
                'description' => esc_html__('This controls the description which the user sees during checkout.', 'reown-payment-gateway'),
                'default'     => esc_html__('Pay with crypto wallets and exchanges by Reown (WalletConnect)', 'reown-payment-gateway'),
            ],
            'order_button_text' => [
                'title'       => esc_html__('Order button text', 'reown-payment-gateway'),
                'type'        => 'text',
                'description' => esc_html__('Pay button on the checkout page', 'reown-payment-gateway'),
                'default'     => esc_html__('Pay with Reown (WalletConnect)', 'reown-payment-gateway'),
            ],
            'payment_complete_order_status' => [
                'title'   => esc_html__('Payment complete order status', 'reown-payment-gateway'),
                'type'    => 'select',
                'help'    => esc_html__('The status to apply for order after payment is complete.', 'reown-payment-gateway'),
                'options' => [
                    'wc-completed' => esc_html__('Completed', 'reown-payment-gateway'),
                    'wc-processing' => esc_html__('Processing', 'reown-payment-gateway')
                ],
                'default' => 'wc-completed',
            ],
            'reown_app_kit_id' => [
                'title' => esc_html__('Reown AppKit ID', 'reown-payment-gateway'),
                'type' => 'text',
                'description' => esc_html__('AppKit ID is required for WalletConnect and AppKit, which are used to connect to mobile wallets on many networks. If you do not have a Project ID, Reown AppKit will not work. You can get your project ID by registering for Reown Cloud at the link below.', 'reown-payment-gateway') . '<br><br><a href="https://cloud.reown.com/sign-in" target="_blank">' . esc_html__('Get Reown AppKit ID', 'reown-payment-gateway') . '</a>.',
                'default' => ''
            ],
        ];
    }

    /**
     * @param string $key
     * @return string|null
     */
    // @phpcs:ignore
    public static function get_option_custom(string $key): ?string
    {
        $options = get_option('woocommerce_' . self::ID . '_settings');
        return isset($options[$key]) ? $options[$key] : null;
    }

    /**
     * @return mixed
     */
    // @phpcs:ignore
    public function get_icon() : string
    {
        return '<img src="' . plugins_url('assets/images/reown.png', dirname(__FILE__)) . '" alt="Reown" />';
    }

    /**
     * @return string
     */
    public function getPaymentFields(): string
    {
        ob_start();
        $this->payment_fields();
        return ob_get_clean();
    }

    /**
     * @return void
     */
    // @phpcs:ignore
    public function payment_fields(): void
    {
        // @phpcs:disable
        echo esc_html($this->description);
        ?>
        - <span class="py-footer">
            <span class="powered-by">
                Powered by
            </span>
            <a href="https://beycanpress.com/cryptopay/?utm_source=reown_plugin&amp;utm_medium=powered_by" target="_blank">CryptoPay</a>
        </span>
        <?php
        // @phpcs:enable
    }

    /**
     * @param int $orderId
     * @return array<string,string>
     */
    // @phpcs:ignore
    public function process_payment($orderId): array
    {
        global $woocommerce;

        $order = new \WC_Order($orderId);

        $status = Gateway::get_option_custom('payment_complete_order_status');

        if (0 == $order->get_total()) {
            if ('wc-completed' == $status) {
                $note = esc_html__('Your order is complete.', 'reown-payment-gateway');
            } else {
                $note = esc_html__('Your order is processing.', 'reown-payment-gateway');
            }

            $order->payment_complete();

            $order->update_status($status, $note);

            $order->add_order_note(esc_html__(
                'Was directly approved by Reown Payment Gateway as the order amount was zero!',
                'reown-payment-gateway'
            ));

            $url = $order->get_checkout_order_received_url();
        } else {
            $order->update_status('wc-pending', esc_html__('Payment is awaited.', 'reown-payment-gateway'));

            $order->add_order_note(
                esc_html__('Customer has chosen Reown Payment gateway, payment is pending.', 'reown-payment-gateway')
            );

            $url = $order->get_checkout_payment_url(true);
        }

        // Remove cart
        $woocommerce->cart->empty_cart();

        // Return thankyou redirect
        return [
            'result' => 'success',
            'redirect' => $url
        ];
    }
}
