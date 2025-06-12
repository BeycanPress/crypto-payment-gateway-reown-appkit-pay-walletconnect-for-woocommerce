import * as evmNetworks from 'viem/chains'
import { createAppKit } from '@reown/appkit'
import { AppKitNetwork } from '@reown/appkit/networks'
import { WagmiAdapter } from '@reown/appkit-adapter-wagmi'
import { getIsPaymentInProgress, getPayError, getPayResult, openPay } from '@reown/appkit-pay'

declare global {
    interface Window {
        wc_checkout_params: {
            checkout_url: string
        }
        Reown: {
            isOrderPay: boolean
            ajaxUrl: string
            theme: string
            appKitId: string
            testMode: boolean
            metadata: {
                name: string
                description: string
                url: string
            }
            lang: Record<string, string>
        }
    }
}

;(async ($) => {
    const Reown = window.Reown
    Reown.testMode = Boolean(Reown.testMode)
    Reown.isOrderPay = Boolean(Reown.isOrderPay)

    let nonce = ''
    $.ajax({
        type: 'POST',
        dataType: 'json',
        url: Reown.ajaxUrl,
        data: {
            action: 'reown_get_new_nonce'
        },
        success: function (response) {
            if (response.success) {
                nonce = response.data.nonce || ''
            } else {
                console.error('Error fetching nonce:', response.data)
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            console.error('Error fetching nonce:', jqXHR, textStatus, errorThrown)
        }
    })

    const testnets = Object.values(evmNetworks).filter(
        (network) => network.testnet || network.name.includes('Testnet')
    )
    const mainnets = Object.values(evmNetworks).filter(
        (network) => !network.testnet && !network.name.includes('Testnet')
    )

    const networks: [AppKitNetwork, ...AppKitNetwork[]] = [
        (Reown.testMode ? testnets : mainnets)[0],
        ...(Reown.testMode ? testnets.slice(1) : mainnets.slice(1))
    ]

    const getNetworkById = (id: number): AppKitNetwork | undefined => {
        return networks.find((network) => network.id === id)
    }

    const projectId = Reown.appKitId

    const wagmiAdapter = new WagmiAdapter({
        projectId,
        networks: Reown.testMode ? testnets : mainnets
    })

    const metadata = {
        name: Reown.metadata.name,
        description: Reown.metadata.description,
        url: Reown.metadata.url,
        icons: []
    }

    const modal = createAppKit({
        themeMode: Reown.theme as 'light' | 'dark',
        themeVariables: {
            '--w3m-z-index': 99999999
        },
        adapters: [wagmiAdapter],
        networks,
        metadata,
        projectId
    })

    const showLoading = (text = 'Loading...') => {
        const overlay = $('#loadingOverlay')
        const loadingText = $('#loadingText')

        if (!overlay.length || !loadingText.length) {
            console.error('Loading elements not found in the DOM')
            return
        }

        loadingText.text(text)
        overlay.addClass('active')
    }

    const hideLoading = () => {
        const overlay = $('#loadingOverlay')

        if (!overlay.length) {
            console.error('Loading overlay not found in the DOM')
            return
        }

        overlay.removeClass('active')
    }

    const $form = $('form.checkout')

    const handleUnloadEvent = (e: JQuery.TriggeredEvent<Window>) => {
        if (navigator.userAgent.indexOf('MSIE') !== -1 || !!document.DOCUMENT_NODE) {
            // IE handles unload events differently than modern browsers
            e.preventDefault()
            return undefined
        }

        return true
    }

    const scrollToNotices = () => {
        let scrollElement = $(
            '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout'
        )

        if (!scrollElement.length) {
            scrollElement = $form
        }

        if (scrollElement.length) {
            $('html, body').animate(
                {
                    scrollTop: (scrollElement.offset()?.top || 0) - 100
                },
                1000
            )
        }
    }

    const stripHtml = (html: string) => {
        const tempDiv = document.createElement('div')
        tempDiv.innerHTML = html
        return tempDiv.innerText.trim()
    }

    const printError = (errorMessage: string) => {
        if (!$form.length) {
            alert(stripHtml(errorMessage))
            return
        }
        $('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove()
        $form.prepend(
            '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
                errorMessage +
                '</div>'
        )
        // @ts-expect-error it's fine
        $form.removeClass('processing').unblock()
        $form.find('.input-text, select, input:checkbox').trigger('validate').trigger('blur')
        scrollToNotices()
        $(document.body).trigger('checkout_error', [errorMessage])
    }

    const getUrlParameter = (name: string): string | null => {
        const url = new URL(window.location.href)
        return url.searchParams.get(name)
    }

    const checkFormProcess = async (
        payload: Record<string, any>
    ): Promise<
        | {
              orderId: number
              amount: number
              recipient: string
          }
        | false
    > => {
        let data = $form.serialize()
        if (Reown.isOrderPay) {
            data += '&nonce=' + nonce
            data += '&key=' + getUrlParameter('key')
            data += '&action=reown_process_payload'
        }
        data += '&payload=' + JSON.stringify(payload)
        return new Promise((resolve) => {
            $.ajax({
                data: data,
                type: 'POST',
                dataType: 'json',
                url: Reown.isOrderPay ? Reown.ajaxUrl : window.wc_checkout_params.checkout_url,
                success: function (response) {
                    $(window).off('beforeunload', handleUnloadEvent)

                    if (response.result === 'success' || response.success) {
                        $('.woocommerce-NoticeGroup-checkout').remove()
                        const result = response.data || response
                        resolve({
                            orderId: result.order_id,
                            amount: result.converted.amount,
                            recipient: result.converted.recipient
                        })
                    } else {
                        if (response.data) {
                            if (true === response.data.reload) {
                                window.location.reload()
                                return
                            }

                            if (true === response.data.refresh) {
                                $(document.body).trigger('update_checkout')
                            }
                        }

                        const message =
                            response.message || response.messages || response.data?.messages

                        if (message) {
                            printError(message)
                        }

                        resolve(false)
                    }
                },
                error: function (jqXHR, textStatus, errorThrown) {
                    $(window).off('beforeunload', handleUnloadEvent)

                    const message =
                        jqXHR.responseJSON?.message ||
                        jqXHR.responseJSON?.messages ||
                        jqXHR.responseJSON?.data?.message
                    printError(message || Reown.lang.errorProcessingCheckout)

                    resolve(false)
                }
            })
        })
    }

    const completePayment = (txId: string, orderId: number) => {
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: Reown.ajaxUrl,
            data: {
                action: 'reown_complete_payment',
                orderId: orderId,
                txId: txId,
                nonce
            },
            beforeSend: function () {
                showLoading(Reown.lang.processingPayment)
            },
            success: function (response) {
                if (response.success) {
                    showLoading(Reown.lang.redirecting)
                    window.location.href = response.data
                } else {
                    hideLoading()
                    printError(response.data || Reown.lang.errorProcessingCheckout)
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Error completing payment:', jqXHR, textStatus, errorThrown)
                hideLoading()
                printError(jqXHR.responseJSON?.data || Reown.lang.errorProcessingCheckout)
            }
        })
    }

    const paymentFailed = (orderId: number) => {
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: Reown.ajaxUrl,
            data: {
                action: 'reown_payment_failed',
                orderId: orderId,
                nonce
            },
            beforeSend: function () {
                showLoading(Reown.lang.processingPayment)
            },
            success: function (response) {
                if (response.success) {
                    showLoading(Reown.lang.redirecting)
                    window.location.href = response.data
                } else {
                    hideLoading()
                    printError(response.data || Reown.lang.errorProcessingCheckout)
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Error completing payment:', jqXHR, textStatus, errorThrown)
                hideLoading()
                printError(jqXHR.responseJSON?.data || Reown.lang.errorProcessingCheckout)
            }
        })
    }

    let checker: any = null

    $(document).on('DOMContentLoaded', function () {
        $(document).on('click', '.currency-item', async function () {
            const info = $(this).data('info') as {
                networkId: number
                symbol: string
                address: string
                decimals: number
            }

            const network = getNetworkById(info.networkId)
            if (!network) {
                alert(Reown.lang.networkIsNotSupported)
                return hideLoading()
            }

            await modal.disconnect()
            await modal.switchNetwork(network)

            showLoading(Reown.lang.waitingForCurrencyConversion)

            const result = await checkFormProcess({
                networkId: network.id,
                networkName: network.name,
                currencySymbol: info.symbol,
                currencyAddress: info.address
            })

            if (!result) {
                return hideLoading()
            }

            await openPay({
                recipient: result.recipient,
                amount: result.amount,
                paymentAsset: {
                    network: `eip155:${info.networkId}`,
                    asset: info.address,
                    metadata: {
                        name: info.symbol,
                        symbol: info.symbol,
                        decimals: info.decimals
                    }
                }
            })

            clearInterval(checker)
            checker = setInterval(() => {
                if (!getIsPaymentInProgress()) {
                    const txId = getPayResult()
                    const error = getPayError()
                    if (!txId && !error) {
                        return
                    }
                    if (txId) {
                        completePayment(txId, result.orderId)
                    } else if (error) {
                        paymentFailed(result.orderId)
                    }
                    modal.close()
                    clearInterval(checker)
                }
            }, 1000)

            hideLoading()
        })
    })
})(jQuery)
