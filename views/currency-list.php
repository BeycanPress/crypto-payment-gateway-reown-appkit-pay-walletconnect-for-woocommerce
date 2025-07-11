<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

use BeycanPress\ReownPaymentGateway\Networks;
?>

<div class="loading-overlay <?php echo esc_attr($this->theme); ?>" id="loadingOverlay">
    <div class="loading-container">
        <div class="spinner" id="spinner">
            <div class="spinner-circle"></div>
        </div>
        <div class="loading-text" id="loadingText">
            <?php echo esc_html__('Loading...', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'); ?>
        </div>
    </div>
</div>
<div class="currency-list-wrapper <?php echo esc_attr($this->theme); ?>">
    <div class="currency-list-inner">
        <div class="currency-list-header">
            <div class="currency-list-title">
                <?php echo esc_html__('Select Currency', 'crypto-payment-gateway-reown-appkit-pay-walletconnect-for-woocommerce'); ?>
            </div>
        </div>

        <div class="currency-list">
            <?php foreach (Networks::getCurrencies() as $currency): ?>
                <div class="currency-item" data-info='<?php echo wp_json_encode([
                                                            'symbol' => $currency['symbol'],
                                                            'address' => $currency['address'],
                                                            'networkId' => $currency['network']['id'],
                                                            'decimals' => $currency['decimals'],
                                                        ]) ?>'>
                    <div class="currency-icon">
                        <img src="<?php echo esc_url($currency['image']); ?>" alt="<?php echo esc_attr($currency['symbol']); ?>" />
                        <img src="<?php echo esc_url($currency['network']['image']); ?>" alt="<?php echo esc_attr($currency['network']['name']); ?>" />
                    </div>
                    <div class="currency-info">
                        <div class="currency-name"><?php echo esc_html($currency['symbol']); ?></div>
                        <div class="currency-network"><?php echo esc_html($currency['network']['name']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="powered-by-cryptopay">
    <span>Powered by</span>
    <a href="https://beycanpress.com/cryptopay?utm_source=reown_plugin&amp;utm_medium=powered_by" target="_blank">CryptoPay</a>
</div>