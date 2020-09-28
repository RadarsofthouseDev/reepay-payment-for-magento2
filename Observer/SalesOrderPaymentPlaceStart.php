<?php

namespace Radarsofthouse\Reepay\Observer;

/**
 * Class SalesOrderPaymentPlaceStart
 *
 * @package Radarsofthouse\Reepay\Observer
 */
class SalesOrderPaymentPlaceStart implements \Magento\Framework\Event\ObserverInterface
{
    protected $reepayHelper;

    public function __construct(
        \Radarsofthouse\Reepay\Helper\Data $reepayHelper
    ) {
        $this->reepayHelper = $reepayHelper;
    }

    /**
     * sales_order_payment_place_start observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $payment = $observer->getPayment();
        $paymentMethod = $payment->getMethod();
        if ($this->reepayHelper->isReepayPaymentMethod($paymentMethod)) {
            $order = $payment->getOrder();

            if ($this->reepayHelper->getConfig('send_order_email_when_success', $order->getStoreId())) {
                $order->setCanSendNewEmailFlag(false)
                    ->setIsCustomerNotified(false)
                    ->save();
            }
        }
    }
}
