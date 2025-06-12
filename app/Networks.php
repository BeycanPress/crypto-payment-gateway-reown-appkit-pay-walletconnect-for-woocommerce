<?php

declare(strict_types=1);

namespace BeycanPress\ReownPaymentGateway;

class Networks
{
    /**
     * @param bool $testnet
     * @return array<mixed>
     */
    public static function getCurrencies(): array
    {
        $networks = Gateway::isTestMode() ? self::testnets() : self::mainnets();

        $currencies = [];
        foreach ($networks as $network) {
            foreach ($network['currencies'] as $currency) {
                $currencies[] = array_merge($currency, [
                    'image' => self::getCoinIcon($currency['symbol']),
                    'network' => [
                        'name' => $network['name'],
                        'id' => $network['id'] ?? $network['code'],
                        'image' => self::getCoinIcon($network['nativeCurrency']['symbol'])
                    ],
                ]);
            }
        }

        return $currencies;
    }

    /**
     * @param int $id
     * @return array<mixed>|null
     */
    public static function getNetworkById(int $id): ?array
    {
        $networks = Gateway::isTestMode() ? self::testnets() : self::mainnets();

        $index = array_search($id, array_column($networks, 'id'));
        if (false !== $index) {
            unset($networks[$index]['currencies']);
            return $networks[$index];
        }

        return null;
    }

    /**
     * @param string $symbol
     * @return string
     */
    public static function getCoinIcon(string $symbol): string
    {
        return REOWN_PAYMENT_GATEWAY_URL . 'assets/images/icons/' . strtolower($symbol) . '.svg';
    }

    /**
     * @return array<mixed>
     */
    private static function testnets(): array
    {
        return [
            [
                "id" => 11155111,
                "hexId" => "0xaa36a7",
                "mainnetId" => 1,
                "code" => "evmchains",
                "name" => "Ethereum Sepolia Testnet",
                "rpcUrl" => "https://ethereum-sepolia-rpc.publicnode.com",
                "explorerUrl" => "https://sepolia.etherscan.io/",
                "nativeCurrency" => [
                    "symbol" => "ETH",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "ETH", "address" => "native", "decimals" => 18],
                    ["symbol" => "USDT", "address" => "0x419Fe9f14Ff3aA22e46ff1d03a73EdF3b70A62ED", "decimals" => 6],
                    ["symbol" => "USDC", "address" => "0x13fA158A117b93C27c55b8216806294a0aE88b6D", "decimals" => 6],
                ],
            ],
            [
                "id" => 97,
                "hexId" => "0x61",
                "mainnetId" => 56,
                "code" => "evmchains",
                "name" => "BNB Smart Chain Testnet",
                "rpcUrl" => "https://bsc-testnet.publicnode.com",
                "explorerUrl" => "https://testnet.bscscan.com/",
                "nativeCurrency" => [
                    "symbol" => "BNB",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "BNB", "address" => "native", "decimals" => 18],
                    ["symbol" => "USDT", "address" => "0xba6670261a05b8504e8ab9c45d97a8ed42573822", "decimals" => 6],
                ],
            ],
            [
                "id" => 43113,
                "hexId" => "0xa869",
                "mainnetId" => 43114,
                "code" => "evmchains",
                "name" => "Avalanche FUJI C-Chain Testnet",
                "rpcUrl" => "https://api.avax-test.network/ext/bc/C/rpc",
                "explorerUrl" => "https://cchain.explorer.avax-test.network",
                "nativeCurrency" => [
                    "symbol" => "AVAX",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "AVAX", "address" => "native", "decimals" => 18],
                    ["symbol" => "USDT", "address" => "0xFe143522938e253e5Feef14DB0732e9d96221D72", "decimals" => 6],
                ],
            ],
            [
                "id" => 80002,
                "hexId" => "0x13882",
                "mainnetId" => 137,
                "code" => "evmchains",
                "name" => "Polygon Amoy Testnet",
                "rpcUrl" => "https://rpc-amoy.polygon.technology",
                "explorerUrl" => "https://www.oklink.com/amoy",
                "nativeCurrency" => [
                    "symbol" => "POL",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "POL", "address" => "native", "decimals" => 18],
                    ["symbol" => "USDT", "address" => "0xa02f6adc7926efebbd59fd43a84f4e0c0c91e832", "decimals" => 6],
                ],
            ],
            [
                "id" => 84532,
                "hexId" => "0x14a34",
                "mainnetId" => 8453,
                "code" => "evmchains",
                "name" => "Base Testnet",
                "rpcUrl" => "https://sepolia.base.org",
                "explorerUrl" => "https://sepolia.basescan.org",
                "nativeCurrency" => [
                    "symbol" => "ETH",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "ETH", "address" => "native", "decimals" => 18],
                ],
            ],
        ];
    }

    /**
     * @return array<mixed>
     */
    private static function mainnets(): array
    {
        return [
            [
                "id" => 1,
                "hexId" => "0x1",
                "name" => "Ethereum",
                "code" => "evmchains",
                "rpcUrl" => "https://ethereum-rpc.publicnode.com",
                "explorerUrl" => "https://etherscan.io/",
                "nativeCurrency" => [
                    "symbol" => "ETH",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "ETH", "address" => "native", "decimals" => 18],
                    ["symbol" => "USDT", "address" => "0xdac17f958d2ee523a2206206994597c13d831ec7", "decimals" => 6],
                    ["symbol" => "USDC", "address" => "0xa0b86991c6218b36c1d19d4a2e9eb0ce3606eb48", "decimals" => 6],
                    ["symbol" => "DAI",  "address" => "0x6b175474e89094c44da98b954eedeac495271d0f", "decimals" => 6],
                ],
            ],
            [
                "id" => 56,
                "hexId" => "0x38",
                "name" => "BNB Smart Chain",
                "code" => "evmchains",
                "rpcUrl" => "https://bsc-rpc.publicnode.com",
                "explorerUrl" => "https://bscscan.com/",
                "nativeCurrency" => [
                    "symbol" => "BNB",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "BNB", "address" => "native", "decimals" => 18],
                    ["symbol" => "USDT", "address" => "0x55d398326f99059ff775485246999027b3197955", "decimals" => 6],
                    ["symbol" => "USDC", "address" => "0x8ac76a51cc950d9822d68b83fe1ad97b32cd580d", "decimals" => 6],
                    ["symbol" => "DAI",  "address" => "0x1af3f329e8be154074d8769d1ffa4ee058b1dbc3", "decimals" => 6],
                ],
            ],
            [
                "id" => 43114,
                "hexId" => "0xa86a",
                "name" => "Avalanche C-Chain",
                "code" => "evmchains",
                "rpcUrl" => "https://api.avax.network/ext/bc/C/rpc",
                "explorerUrl" => "https://cchain.explorer.avax.network/",
                "nativeCurrency" => [
                    "symbol" => "AVAX",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "AVAX", "address" => "native", "decimals" => 18],
                    ["symbol" => "USDT", "address" => "0xde3a24028580884448a5397872046a019649b084", "decimals" => 6],
                    ["symbol" => "USDC", "address" => "0xB97EF9Ef8734C71904D8002F8b6Bc66Dd9c48a6E", "decimals" => 6],
                    ["symbol" => "DAI",  "address" => "0xd586E7F844cEa2F87f50152665BCbc2C279D8d70", "decimals" => 6],
                ],
            ],
            [
                "id" => 137,
                "hexId" => "0x89",
                "name" => "Polygon",
                "code" => "evmchains",
                "rpcUrl" => "https://polygon-rpc.com/",
                "explorerUrl" => "https://polygonscan.com/",
                "nativeCurrency" => [
                    "symbol" => "POL",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "POL", "address" => "native", "decimals" => 18],
                    ["symbol" => "USDT", "address" => "0xc2132d05d31c914a87c6611c10748aeb04b58e8f", "decimals" => 6],
                    ["symbol" => "USDC", "address" => "0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359", "decimals" => 6],
                    ["symbol" => "DAI",  "address" => "0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063", "decimals" => 6],
                ],
            ],
            [
                "id" => 8453,
                "hexId" => "0x2105",
                "name" => "Base Mainnet",
                "code" => "evmchains",
                "rpcUrl" => "https://mainnet.base.org",
                "explorerUrl" => "https://basescan.org",
                "nativeCurrency" => [
                    "symbol" => "ETH",
                    "decimals" => 18,
                ],
                "currencies" => [
                    ["symbol" => "ETH", "address" => "native", "decimals" => 18],
                    ["symbol" => "USDT", "address" => "0xfde4c96c8593536e31f229ea8f37b2ada2699bb2", "decimals" => 6],
                    ["symbol" => "USDC", "address" => "0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913", "decimals" => 6],
                ],
            ],
        ];
    }
}
