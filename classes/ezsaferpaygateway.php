<?php

define( "EZ_PAYMENT_GATEWAY_TYPE_SAFERPAY", "ezsaferpay" );

class eZSaferPayGateway extends eZRedirectGateway
{
    /*!
        Constructor.
    */
    function  __construct()
    {
        $this->logger = eZPaymentLogger::CreateForAdd( "var/log/eZSaferPayGateway.log" );
        $this->logger->writeTimedString( 'eZSafePayGateway::__construct()' );
    }

    /*!
        Creates new eZPaypalGateway object.
    */
    function createPaymentObject( $processID, $orderID )
    {
        $this->logger->writeTimedString("createPaymentObject");
        return eZPaymentObject::createNew( $processID, $orderID, 'SaferPay' );
    }

    /*!
        Creates redirectional url to paypal server.
    */
    function createRedirectionUrl( $process )
    {

        $this->logger->writeTimedString("createRedirectionUrl");

        $saferPayINI    = eZINI::instance( 'saferpay.ini' );

        $processParams  = $process->attribute( 'parameter_list' );
        $orderID        = $processParams['order_id'];

        $atm = new AtelierTicketManager();
        $sessionId = $atm->getSessionFromOrder($orderID);

        $order          = eZOrder::fetch( $orderID );
        $amount         = urlencode( $order->attribute( 'total_inc_vat' ) * 100 );
        $currency       = urlencode( $order->currencyCode() );

        $ezSaferPay     = new eZSaferPay(array('order_id' => $orderID, 'user_id' => eZUser::currentUserID(), 'amount' => $amount, 'session_id' => $sessionId));
        $ezSaferPay->initTokens();

        $customer_email = urlencode( $order->accountEmail() );

        $accountID      = urlencode( $saferPayINI->variable( 'MerchantSettings', 'AccountID' ) );

        $description    = urlencode( 'den Atelier Shop' );

        $requestServer   = $saferPayINI->variable( 'ServerSettings', 'ServerName' );
        $createPaymentURI= $saferPayINI->variable( 'ServerSettings', 'CreatePaymentURI' );

        $url = eZSys::serverURL();

        $baseURL         = sprintf("%s/saferpay", trim($url, '/'));

        $notifyURL      = urlencode(sprintf("%s/notify?token=%s", $baseURL, $ezSaferPay->getToken()));
        $backURL        = urlencode(sprintf("%s/checkout?token=%s&op=back", $baseURL, $ezSaferPay->getToken()));
        $successURL     = urlencode(sprintf("%s/checkout?token=%s&op=ok", $baseURL, $ezSaferPay->getToken()));
        $failURL        = urlencode(sprintf("%s/checkout?token=%s&op=nok", $baseURL, $ezSaferPay->getToken()));

        $saferpay_params = array(
                'ACCOUNTID' => $accountID,
                'LANGID' => 'en',
                'AMOUNT' => $amount,
                'CURRENCY' => $currency,
                'ALLOWCOLLECT' => 'no',
                'AUTOCLOSE' => 0,
                'NOTIFYADDRESS' => 'saferpay@atelier.lu',
                'DESCRIPTION' => $description,
                'ORDERID' => $orderID,
                'DELIVERY' => 'no',
                'CCCVC' => 'yes',
                'CCNAME' => 'no',
                'SUCCESSLINK' => $successURL,
                'FAILLINK' => $failURL,
                'NOTIFYURL' => $notifyURL,
                'BACKLINK' => $backURL,
                'VTCONFIG' => 'denatelier',
                'CARDREFID' => $ezSaferPay->attribute("cardrefid")
        );

        $request_params = array();
        foreach ($saferpay_params as $key => $value)
        {
            $request_params[] = sprintf("%s=%s", $key, $value);
        }

        $request_params = join("&", $request_params);
        $request_url =  $requestServer . $createPaymentURI;

        $final_url = sprintf("%s?%s", $request_url, $request_params);

        //__DEBUG__
        $this->logger->writeTimedString("REQUEST URL = " . $request_url);
        $this->logger->writeTimedString("ACCOUNTID   = " . $accountID);
        $this->logger->writeTimedString("AMOUNT      = " . $amount);
        $this->logger->writeTimedString("DESCRIPTION = " . $description);
        $this->logger->writeTimedString("USERNOTIFY  = " . $customer_email);
        $this->logger->writeTimedString("CURRENCY    = " . $currency);
        $this->logger->writeTimedString("ORDERID     = " . $orderID);
        $this->logger->writeTimedString("SUCCESSLINK = " . $successURL);
        $this->logger->writeTimedString("FAILLINK    = " . $failURL);
        $this->logger->writeTimedString("NOTIFYURL   = " . $notifyURL);
        $this->logger->writeTimedString("BACKLINK    = " . $backURL);
        $this->logger->writeTimedString("FINAL URL   = " . $final_url);
        //___end____

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $final_url);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $targetUrl = curl_exec($ch);

        $this->logger->writeTimedString("TARGET URL  = " . $targetUrl);

        return $targetUrl;
    }
}

eZPaymentGatewayType::registerGateway( EZ_PAYMENT_GATEWAY_TYPE_SAFERPAY, "ezsaferpaygateway", "SaferPay" );
