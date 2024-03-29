<?php

require_once('api/Okay.php');

class WayForPay extends Okay
{
    protected $keysForSignature = array(
        'merchantAccount',
        'merchantDomainName',
        'orderReference',
        'orderDate',
        'amount',
        'currency',
        'productName',
        'productCount',
        'productPrice'
    );


    public function checkout_form($order_id)
    {
        $order = $this->orders->get_order((int)$order_id);
        $purchases = $this->orders->get_purchases(array('order_id' => intval($order->id)));
        $payment_method = $this->payment->get_payment_method($order->payment_method_id);
        $payment_currency = $this->money->get_currency(intval($payment_method->currency_id));
        $settings = $this->payment->get_payment_settings($payment_method->id);
        $amount = round($this->money->convert($order->total_price, $payment_method->currency_id, false), 2);

        $currency = $payment_currency->code;

        $productNames = array();
        $productQty = array();
        $productPrices = array();
        foreach ($purchases as $purchase) {
            $productNames[] = trim($purchase->product_name . ' ' . $purchase->variant_name);
            $productPrices[] = $this->money->convert($purchase->price, $payment_method->currency_id, false);
            $productQty[] = $purchase->amount;
        }


        $option = array();
        $option['merchantAccount'] = $settings['wayforpay_merchant'];
        $option['orderReference'] = $order->id.'#'.time();
        $option['orderDate'] = strtotime($order->date);
        $option['merchantAuthType'] = 'simpleSignature';
        $option['merchantDomainName'] = $_SERVER['HTTP_HOST'];
        $option['merchantTransactionSecureType'] = 'AUTO';
        $option['currency'] = $currency;
        $option['amount'] = $amount;

        $option['productName'] = $productNames;
        $option['productPrice'] = $productPrices;
        $option['productCount'] = $productQty;


        $option['returnUrl'] = $this->config->root_url . '/order/' . $order->url;
        $option['serviceUrl'] = $this->config->root_url . '/payment/WayForPay/callback.php';


        $hash = array();
        foreach ($this->keysForSignature as $dataKey) {
            if (!isset($option[$dataKey])) {
                continue;
            }
            if (is_array($option[$dataKey])) {
                foreach ($option[$dataKey] as $v) {
                    $hash[] = $v;
                }
            } else {
                $hash [] = $option[$dataKey];

            }
        }
        $hash = implode(';', $hash);

        $option['merchantSignature'] = hash_hmac('md5', $hash, $settings['wayforpay_secretkey']);

        /**
         * Check phone
         */
        $phone = str_replace(array('+', ' ', '(', ')'), array('','','',''), $order->phone);
        if(strlen($phone) == 10){
            $phone = '38'.$phone;
        } elseif(strlen($phone) == 11){
            $phone = '3'.$phone;
        }

        $name = explode(' ', $order->name);
        $option['clientFirstName'] = isset($name[0]) ? $name[0] : '';
        $option['clientLastName'] = isset($name[1]) ? $name[1] : '';
        $option['clientEmail'] = $order->email;
        $option['clientPhone'] = $phone;
        $option['clientCity'] = $order->location;
        $option['clientAddress'] = $order->address;
        $option['language'] = $settings['wayforpay_language'];

        return $option;
    }
}

