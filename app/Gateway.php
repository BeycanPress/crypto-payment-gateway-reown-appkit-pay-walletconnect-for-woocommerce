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
                echo esc_html__('Please set the receiver address in the settings.', 'reown-payment-gateway');
                return;
            }

            if (!$this->reown_app_kit_id) {
                echo esc_html__('Please set the Reown AppKit ID in the settings.', 'reown-payment-gateway');
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
                'messages' => $this->createMessage(esc_html__('Invalid security token. Please refresh the page and try again.', 'reown-payment-gateway'))
            ]);
        }

        $orderKey = isset($_POST['key']) ? sanitize_text_field(wp_unslash($_POST['key'])) : null;
        if (!$orderKey) {
            return wp_send_json_error([
                'messages' => $this->createMessage(esc_html__('Invalid order key.', 'reown-payment-gateway'))
            ]);
        }
        $order = wc_get_order(wc_get_order_id_by_order_key(sanitize_text_field($orderKey)));
        if (!$order) {
            return wp_send_json_error([
                'messages' => $this->createMessage(esc_html__('Order not found.', 'reown-payment-gateway'))
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
            return wp_send_json_error($this->createMessage(esc_html__('Invalid security token. Please refresh the page and try again.', 'reown-payment-gateway')));
        }

        $order = wc_get_order(isset($_POST['orderId']) ? absint($_POST['orderId']) : 0);
        if (!$order) {
            return wp_send_json_error($this->createMessage(esc_html__('Order not found.', 'reown-payment-gateway')));
        }

        $order->update_status('failed', esc_html__('Payment failed.', 'reown-payment-gateway'));
        $order->add_order_note(esc_html__('Payment failed by Reown Payment Gateway.', 'reown-payment-gateway'));

        return wp_send_json_success($order->get_checkout_payment_url(true));
    }

    /**
     * @return mixed
     */
    public function completePayment(): mixed
    {
        global $woocommerce;

        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'reown-nonce')) {
            return wp_send_json_error($this->createMessage(esc_html__('Invalid security token. Please refresh the page and try again.', 'reown-payment-gateway')));
        }

        $txId = isset($_POST['txId']) ? sanitize_text_field(wp_unslash($_POST['txId'])) : null;
        if (!$txId) {
            return wp_send_json_error($this->createMessage(esc_html__('Invalid request.', 'reown-payment-gateway')));
        }

        $order = wc_get_order(isset($_POST['orderId']) ? absint($_POST['orderId']) : 0);

        if (!$order) {
            return wp_send_json_error(
                $this->createMessage(esc_html__('Order not found.', 'reown-payment-gateway'))
            );
        }

        if ('pending' !== $order->get_status()) {
            return wp_send_json_error(
                $this->createMessage(esc_html__('Order is not pending.', 'reown-payment-gateway'))
            );
        }

        $paymentAmount = $order->get_meta('reown_amount', true);
        $paymentReceiver = $order->get_meta('reown_receiver', true);
        $paymentNetworkId = $order->get_meta('reown_network_id', true);
        $paymentCurrencyAddress = $order->get_meta('reown_currency_address', true);

        if (!$paymentAmount || !$paymentReceiver || !$paymentNetworkId || !$paymentCurrencyAddress) {
            return wp_send_json_error(
                $this->createMessage(esc_html__('Payment data is missing.', 'reown-payment-gateway'))
            );
        }

        $network = Networks::getNetworkById((int) $paymentNetworkId);

        if (!$network) {
            return wp_send_json_error(
                $this->createMessage(esc_html__('Network not supported.', 'reown-payment-gateway'))
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
                $this->createMessage(esc_html__('Transaction is not correct for this order.', 'reown-payment-gateway'))
            );
        }

        $order->payment_complete($txId);
        $woocommerce->cart->empty_cart();

        $order->update_status(
            Gateway::get_option_custom('payment_complete_order_status'),
            esc_html__('Payment completed successfully.', 'reown-payment-gateway')
        );
        $order->add_order_note(
            sprintf(
                /* translators: %s is the transaction ID */
                esc_html__('Payment completed successfully with transaction ID: %s', 'reown-payment-gateway'),
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
            'test_mode' => [
                'title' => esc_html__('Test Mode', 'reown-payment-gateway'),
                'type' => 'checkbox',
                'label' => esc_html__('Enable Test Mode', 'reown-payment-gateway'),
                'default' => 'no',
                'description' => esc_html__('Enable this to use the test environment for Reown Payment Gateway.', 'reown-payment-gateway')
            ],
            'theme' => [
                'title' => esc_html__('Theme', 'reown-payment-gateway'),
                'type' => 'select',
                'options' => [
                    'light' => esc_html__('Light', 'reown-payment-gateway'),
                    'dark' => esc_html__('Dark', 'reown-payment-gateway'),
                ],
                'default' => 'light',
                'description' => esc_html__('Select the theme for the payment interface.', 'reown-payment-gateway')
            ],
            'receiver' => [
                'title' => esc_html__('Receiver Address', 'reown-payment-gateway'),
                'type' => 'text',
                'description' => esc_html__('The address where the payment will be sent', 'reown-payment-gateway'),
                'default' => '',
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
                'redirecting' => esc_html__('Redirecting...', 'reown-payment-gateway'),
                'processingPayment' => esc_html__('Processing payment...', 'reown-payment-gateway'),
                'errorProcessingCheckout' => esc_html__('Error processing checkout. Please try again.', 'reown-payment-gateway'),
                'paymentFailed' => esc_html__('Payment failed. Please try again.', 'reown-payment-gateway'),
                'networkIsNotSupported' => esc_html__('This network is not supported by Reown Payment Gateway.', 'reown-payment-gateway'),
                'waitingForCurrencyConversion' => esc_html__('Waiting for currency conversion...', 'reown-payment-gateway'),
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
        return self::get_option_custom('description') ?: esc_html__('Pay with Reown (WalletConnect)', 'reown-payment-gateway');
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
                echo esc_html__('Please set the receiver address in the settings.', 'reown-payment-gateway');
                return;
            }

            if (!$this->reown_app_kit_id) {
                echo esc_html__('Please set the Reown AppKit ID in the settings.', 'reown-payment-gateway');
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

            return [
                'result' => 'success',
                'redirect' => $order->get_checkout_order_received_url(),
            ];
        } elseif (!$order->get_meta('reown_amount', true)) {
            $order->update_status('wc-pending', esc_html__('Payment is awaited.', 'reown-payment-gateway'));

            $order->add_order_note(
                esc_html__('Customer has chosen Reown Payment gateway, payment is pending.', 'reown-payment-gateway')
            );
        }

        if (defined('REST_REQUEST')) {
            return [
                'result' => 'success',
                'redirect' => $order->get_checkout_payment_url(true)
            ];
        } elseif ('failed' === $order->get_status()) {
            $order->update_status('wc-pending', esc_html__('Payment is awaited.', 'reown-payment-gateway'));
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
            wc_add_notice(esc_html__('Payment data is missing.', 'reown-payment-gateway'), 'error');
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
            wc_add_notice(esc_html__('Missing required payment data.', 'reown-payment-gateway'), 'error');
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
