<?php

declare(strict_types=1);

// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage

namespace BeycanPress\ReownPaymentGateway;

use BeycanPress\CurrencyConverter;
use MultipleChain\EvmChains\Provider;
use MultipleChain\Enums\AssetDirection;
use MultipleChain\Enums\TransactionStatus;
use MultipleChain\EvmChains\Models\CoinTransaction;
use MultipleChain\EvmChains\Models\TokenTransaction;

class Gateway extends \WC_Payment_Gateway
{
    /**
     * @var string
     */
    public const ID = 'reown';

    /**
     * @var string
     */
    public const SCRIPT_ID = 'reown';

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
     * @var string
     */
    // @phpcs:ignore
    public $reown_app_kit_id;

    /**
     * @var string
     */
    // @phpcs:ignore
    public $test_mode;

    /**
     * @var string
     */
    // @phpcs:ignore
    public $theme;

    /**
     * @var string
     */
    // @phpcs:ignore
    public $receiver;

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
        $this->method_title = esc_html__('Reown Payment Gateway', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce');
        $this->method_description = esc_html__('Reown Payment Gateway', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce');

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
        /** @disregard */
        $this->reown_app_kit_id = $this->get_option('reown_app_kit_id');
        /** @disregard */
        $this->test_mode = $this->get_option('test_mode');
        /** @disregard */
        $this->theme = $this->get_option('theme');
        /** @disregard */
        $this->receiver = $this->get_option('receiver');

        add_filter('woocommerce_order_button_html', [$this, 'configureOrderButtonHtml']);

        add_action('woocommerce_receipt_reown', [$this, 'pay']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        add_action('wp_ajax_reown_complete_payment', [$this, 'completePayment']);
        add_action('wp_ajax_nopriv_reown_complete_payment', [$this, 'completePayment']);
        add_action('wp_ajax_reown_payment_failed', [$this, 'paymentFailed']);
        add_action('wp_ajax_nopriv_reown_payment_failed', [$this, 'paymentFailed']);
        add_action('wp_ajax_reown_process_payload', [$this, 'processPayload']);
        add_action('wp_ajax_nopriv_reown_process_payload', [$this, 'processPayload']);
        add_action('wp_ajax_reown_get_new_nonce', [$this, 'getNewNonce']);
        add_action('wp_ajax_nopriv_reown_get_new_nonce', [$this, 'getNewNonce']);
    }

    /**
     * @return void
     */
    public function getNewNonce(): void
    {
        wp_send_json_success(['nonce' => wp_create_nonce('reown-nonce')]);
    }

    /**
     * @param int $orderId
     * @return void
     */
    public function pay(int $orderId): void
    {
        $order = wc_get_order($orderId);

        if ('pending' != $order->get_status()) {
            wp_redirect($order->get_checkout_order_received_url());
            exit();
        } else {
            $this->payment_scripts();

            if (!$this->receiver) {
                echo esc_html__('Please set the receiver address in the settings.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce');
                return;
            }

            if (!$this->reown_app_kit_id) {
                echo esc_html__('Please set the Reown AppKit ID in the settings.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce');
                return;
            }

            require_once REOWN_PAYMENT_GATEWAY_PATH . 'views/currency-list.php';
        }
    }

    /**
     * @param string $message
     * @return string
     */
    private function createMessage(string $message): string
    {
        return '<ul class="woocommerce-error" role="alert">
            <li>' . esc_html($message) . '</li>
        </ul>';
    }

    /**
     * @return mixed
     */
    public function processPayload(): mixed
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'reown-nonce')) {
            return wp_send_json_error([
                'messages' => $this->createMessage(esc_html__('Invalid security token. Please refresh the page and try again.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'))
            ]);
        }

        $orderKey = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : null;
        if (!$orderKey) {
            return wp_send_json_error([
                'messages' => $this->createMessage(esc_html__('Invalid order key.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'))
            ]);
        }
        $order = wc_get_order(wc_get_order_id_by_order_key(sanitize_text_field($orderKey)));
        if (!$order) {
            return wp_send_json_error([
                'messages' => $this->createMessage(esc_html__('Order not found.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'))
            ]);
        }

        return wp_send_json_success($this->processPayment($order));
    }

    /**
     * @return mixed
     */
    public function paymentFailed(): mixed
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'reown-nonce')) {
            return wp_send_json_error($this->createMessage(esc_html__('Invalid security token. Please refresh the page and try again.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')));
        }

        $order = wc_get_order(isset($_POST['orderId']) ? absint($_POST['orderId']) : 0);
        if (!$order) {
            return wp_send_json_error($this->createMessage(esc_html__('Order not found.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')));
        }

        $order->update_status('failed', esc_html__('Payment failed.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'));
        $order->add_order_note(esc_html__('Payment failed by Reown Payment Gateway.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'));

        return wp_send_json_success($order->get_checkout_payment_url(true));
    }

    /**
     * @return mixed
     */
    public function completePayment(): mixed
    {
        global $woocommerce;

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'reown-nonce')) {
            return wp_send_json_error($this->createMessage(esc_html__('Invalid security token. Please refresh the page and try again.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')));
        }

        $txId = isset($_POST['txId']) ? sanitize_text_field(wp_unslash($_POST['txId'])) : null;
        if (!$txId) {
            return wp_send_json_error($this->createMessage(esc_html__('Invalid request.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')));
        }

        $order = wc_get_order(isset($_POST['orderId']) ? absint($_POST['orderId']) : 0);

        if (!$order) {
            return wp_send_json_error(
                $this->createMessage(esc_html__('Order not found.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'))
            );
        }

        if ('pending' !== $order->get_status()) {
            return wp_send_json_error(
                $this->createMessage(esc_html__('Order is not pending.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'))
            );
        }

        $paymentAmount = $order->get_meta('reown_amount', true);
        $paymentReceiver = $order->get_meta('reown_receiver', true);
        $paymentNetworkId = $order->get_meta('reown_network_id', true);
        $paymentCurrencyAddress = $order->get_meta('reown_currency_address', true);

        if (!$paymentAmount || !$paymentReceiver || !$paymentNetworkId || !$paymentCurrencyAddress) {
            return wp_send_json_error(
                $this->createMessage(esc_html__('Payment data is missing.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'))
            );
        }

        $network = Networks::getNetworkById((int) $paymentNetworkId);

        if (!$network) {
            return wp_send_json_error(
                $this->createMessage(esc_html__('Network not supported.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'))
            );
        }

        $provider = new Provider($network);

        if ('native' === $paymentCurrencyAddress) {
            $transaction = new CoinTransaction($txId, $provider);
        } else {
            $transaction = new TokenTransaction($txId, $provider);
        }

        $result = $transaction->verifyTransfer(AssetDirection::INCOMING, $paymentReceiver, (float) $paymentAmount);

        if (TransactionStatus::CONFIRMED !== $result) {
            return wp_send_json_error(
                $this->createMessage(esc_html__('Transaction is not correct for this order.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'))
            );
        }

        $order->payment_complete($txId);
        $woocommerce->cart->empty_cart();

        $order->update_status(
            Gateway::get_option_custom('payment_complete_order_status'),
            esc_html__('Payment completed successfully.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')
        );
        $order->add_order_note(
            sprintf(
                /* translators: %s is the transaction ID */
                esc_html__('Payment completed successfully with transaction ID: %s', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                $txId
            )
        );
        $order->add_meta_data('reown_tx_id', $txId, true);
        $order->add_meta_data('reown_tx_url', $transaction->getUrl(), true);
        $order->save();

        return wp_send_json_success($order->get_checkout_order_received_url());
    }

    /**
     * @param \WC_Order $order
     */
    // @phpcs:ignore
    public function get_transaction_url($order)
    {
        return $order->get_meta('reown_tx_url', true) ?: '';
    }

    /**
     * @return string
     */
    public static function isTestMode(): bool
    {
        return 'yes' === Gateway::get_option_custom('test_mode');
    }

    /**
     * @return void
     */
    // @phpcs:ignore
    public function init_form_fields(): void
    {
        $this->form_fields = [
            'enabled' => [
                'title'       => esc_html__('Enable/Disable', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'label'       => esc_html__('Enable', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'type'        => 'checkbox',
                'default'     => 'no'
            ],
            'title' => [
                'title'       => esc_html__('Title', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'type'        => 'text',
                'description' => esc_html__('This controls the title which the user sees during checkout.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'default'     => esc_html__('Pay with Reown (WalletConnect)', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')
            ],
            'description' => [
                'title'       => esc_html__('Description', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'type'        => 'textarea',
                'description' => esc_html__('This controls the description which the user sees during checkout.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'default'     => esc_html__('Pay with crypto wallets and exchanges by Reown (WalletConnect)', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
            ],
            'order_button_text' => [
                'title'       => esc_html__('Order button text', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'type'        => 'text',
                'description' => esc_html__('Pay button on the checkout page', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'default'     => esc_html__('Pay with Reown (WalletConnect)', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
            ],
            'payment_complete_order_status' => [
                'title'   => esc_html__('Payment complete order status', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'type'    => 'select',
                'help'    => esc_html__('The status to apply for order after payment is complete.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'options' => [
                    'wc-completed' => esc_html__('Completed', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                    'wc-processing' => esc_html__('Processing', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')
                ],
                'default' => 'wc-completed',
            ],
            'test_mode' => [
                'title' => esc_html__('Test Mode', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'type' => 'checkbox',
                'label' => esc_html__('Enable Test Mode', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'default' => 'no',
                'description' => esc_html__('Enable this to use the test environment for Reown Payment Gateway.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')
            ],
            'theme' => [
                'title' => esc_html__('Theme', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'type' => 'select',
                'options' => [
                    'light' => esc_html__('Light', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                    'dark' => esc_html__('Dark', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                ],
                'default' => 'light',
                'description' => esc_html__('Select the theme for the payment interface.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')
            ],
            'receiver' => [
                'title' => esc_html__('Receiver Address', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'type' => 'text',
                'description' => esc_html__('The address where the payment will be sent', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'default' => '',
            ],
            'reown_app_kit_id' => [
                'title' => esc_html__('Reown AppKit ID', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'type' => 'text',
                'description' => esc_html__('AppKit ID is required for WalletConnect and AppKit, which are used to connect to mobile wallets on many networks. If you do not have a Project ID, Reown AppKit will not work. You can get your project ID by registering for Reown Cloud at the link below.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce') . '<br><br><a href="https://cloud.reown.com/sign-in" target="_blank">' . esc_html__('Get Reown AppKit ID', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce') . '</a>.',
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
     * @param string $button
     * @return string
     */
    public function configureOrderButtonHtml(string $button): string
    {
        $paymentMethod = WC()->session->get('chosen_payment_method');

        // @phpstan-ignore-next-line
        if ($paymentMethod == $this->id && WC()->cart->total > 0) {
            return '';
        }

        return $button;
    }

    /**
     * @return void
     */
    // @phpcs:ignore
    public function payment_scripts(): void
    {
        if (!$this->receiver || !$this->reown_app_kit_id) {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_ID,
            REOWN_PAYMENT_GATEWAY_URL . 'assets/js/main.min.js',
            [],
            REOWN_PAYMENT_GATEWAY_VERSION,
            true
        );

        wp_enqueue_style(
            self::SCRIPT_ID,
            REOWN_PAYMENT_GATEWAY_URL . 'assets/css/main.css',
            [],
            REOWN_PAYMENT_GATEWAY_VERSION
        );

        wp_localize_script(self::SCRIPT_ID, 'Reown', [
            'theme' => $this->theme,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'appKitId' => $this->reown_app_kit_id,
            'testMode' => 'yes' === $this->test_mode,
            'isOrderPay' => is_checkout() && is_wc_endpoint_url('order-pay'),
            'metadata' => [
                'title' => $this->title,
                'description' => $this->description,
                'url' => home_url(),
            ],
            'lang' => [
                'redirecting' => esc_html__('Redirecting...', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'processingPayment' => esc_html__('Processing payment...', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'errorProcessingCheckout' => esc_html__('Error processing checkout. Please try again.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'paymentFailed' => esc_html__('Payment failed. Please try again.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'networkIsNotSupported' => esc_html__('This network is not supported by Reown Payment Gateway.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
                'waitingForCurrencyConversion' => esc_html__('Waiting for currency conversion...', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'),
            ]
        ]);
    }

    /**
     * @return mixed
     */
    // @phpcs:ignore
    public function get_icon(): string
    {
        return '<img src="' . plugins_url('assets/images/reown.png', dirname(__FILE__)) . '" alt="Reown" />';
    }

    /**
     * @return string
     */
    public function getPaymentFields(): string
    {
        return self::get_option_custom('description') ?: esc_html__('Pay with Reown (WalletConnect)', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce');
    }

    /**
     * @return void
     */
    // @phpcs:ignore
    public function payment_fields(): void
    {
        if (WC()->cart->total > 0) {
            $this->payment_scripts();

            if (!$this->receiver) {
                echo esc_html__('Please set the receiver address in the settings.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce');
                return;
            }

            if (!$this->reown_app_kit_id) {
                echo esc_html__('Please set the Reown AppKit ID in the settings.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce');
                return;
            }

            require_once REOWN_PAYMENT_GATEWAY_PATH . 'views/currency-list.php';
        } else {
            echo esc_html($this->description);
        }
    }

    /**
     * @param int $orderId
     * @return array<string,string>
     */
    // @phpcs:ignore
    public function process_payment($orderId): array
    {
        $order = new \WC_Order($orderId);

        $status = Gateway::get_option_custom('payment_complete_order_status');

        if (0 == $order->get_total()) {
            if ('wc-completed' == $status) {
                $note = esc_html__('Your order is complete.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce');
            } else {
                $note = esc_html__('Your order is processing.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce');
            }

            $order->payment_complete();

            $order->update_status($status, $note);

            $order->add_order_note(esc_html__(
                'Was directly approved by Reown Payment Gateway as the order amount was zero!',
                'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'
            ));

            return [
                'result' => 'success',
                'redirect' => $order->get_checkout_order_received_url(),
            ];
        } elseif (!$order->get_meta('reown_amount', true)) {
            $order->update_status('wc-pending', esc_html__('Payment is awaited.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'));

            $order->add_order_note(
                esc_html__('Customer has chosen Reown Payment gateway, payment is pending.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce')
            );
        }

        if (defined('REST_REQUEST')) {
            return [
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            ];
        } elseif ('failed' === $order->get_status()) {
            $order->update_status('wc-pending', esc_html__('Payment is awaited.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'));
            wp_redirect($order->get_checkout_payment_url(true));
            exit;
        }

        return $this->processPayment($order);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadCheck(): array
    {
        // here cannot have nonce check, because this one is private method. So nonce checked already.
        $payload = isset($_POST['payload']) ? sanitize_text_field(wp_unslash($_POST['payload'])) : null;
        if (!$payload) {
            wc_add_notice(esc_html__('Payment data is missing.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'), 'error');
            return [
                'result' => 'failure',
            ];
        }

        $payload = json_decode($payload, true);

        $networkId = isset($payload['networkId']) ? sanitize_text_field($payload['networkId']) : null;
        $networkName = isset($payload['networkName']) ? sanitize_text_field($payload['networkName']) : null;
        $currencySymbol = isset($payload['currencySymbol']) ? sanitize_text_field($payload['currencySymbol']) : null;
        $currencyAddress = isset($payload['currencyAddress']) ? sanitize_text_field($payload['currencyAddress']) : null;

        if (!$networkId || !$networkName || !$currencySymbol || !$currencyAddress) {
            wc_add_notice(esc_html__('Missing required payment data.', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'), 'error');
            return [
                'result' => 'failure',
            ];
        }

        return [
            'network' => [
                'id' => $networkId,
                'name' => $networkName,
            ],
            'currency' => [
                'symbol' => $currencySymbol,
                'address' => $currencyAddress,
            ]
        ];
    }

    /**
     * @param string $cryptoCurrency
     * @param float $amount
     * @return array<string, mixed>
     */
    private function convertCurrency(string $cryptoCurrency, float $amount): array
    {
        $fiatCurrency = get_woocommerce_currency();

        $converter = new CurrencyConverter('CryptoCompare');
        $convertedAmount = $converter->convert($fiatCurrency, $cryptoCurrency, $amount);

        return [
            'amount' => $convertedAmount,
            'recipient' => $this->receiver,
        ];
    }

    /**
     * @param \WC_Order $order
     * @return array<string, string>
     */
    // @phpcs:ignore
    private function processPayment(\WC_Order $order): array
    {
        $payload = $this->payloadCheck();

        if ('failure' === $payload['result']) {
            return [
                'result' => 'failure'
            ];
        }

        $amount = (float) (\WC()->cart->total ? \WC()->cart->total : $order->get_total());
        $converted = $this->convertCurrency($payload['currency']['symbol'], $amount);

        $order->add_meta_data('reown_amount', $converted['amount'], true);
        $order->add_meta_data('reown_receiver', $converted['recipient'], true);
        $order->add_meta_data('reown_network_id', $payload['network']['id'], true);
        $order->add_meta_data('reown_network_name', $payload['network']['name'], true);
        $order->add_meta_data('reown_currency_symbol', $payload['currency']['symbol'], true);
        $order->add_meta_data('reown_currency_address', $payload['currency']['address'], true);

        $order->save();

        return [
            'result' => 'success',
            'converted' => $converted,
            'order_id' => $order->get_id(),
        ];
    }
}
