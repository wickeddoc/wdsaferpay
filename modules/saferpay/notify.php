<?php

$http = eZHTTPTool::instance();
$logger = eZPaymentLogger::CreateForAdd( 'var/log/eZSaferPayNotify.log' );
$logger->writeTimedString($_SERVER['REMOTE_ADDR'], 'payment notify request from IP' );

if ( $http->hasVariable( 'token' ) )
{
    $token = $http->getVariable('token');

    $logger->writeTimedString($token, 'loading eZSaferPay for token');
    $eZSaferPay = eZSaferPay::fetchByToken($token);
    if ($eZSaferPay && $eZSaferPay->getTokenStatus($token) == 'notify' && !$eZSaferPay->isProcessed())
    {
        $logger->writeTimedString('processing payment');
        $checker = new eZSaferPayChecker('saferpay.ini');
        $checker->createDataFromPOST();
        $checker->setupOrderAndPaymentObject($eZSaferPay->attribute('order_id'));
        $data = $checker->getSaferPayData();
        $eZSaferPay->processPayment($data);
        $verified = $checker->verifyPayment($data);
        if ($verified)
        {
            $orderID = $eZSaferPay->attribute('order_id');
            $logger->writeTimedString($orderID, 'payment approved for order');
            $eZSaferPay->setStatus('success');
            $checker->order->modifyStatus($checker->ini->variable( 'OrderSettings', 'SuccessID' ));
            if($checker->completePayment($eZSaferPay->attribute('auth_id')))
                $eZSaferPay->setSettled();
            $checker->approvePayment();
            $checker->order->activate();
        }
        else
        {
            $logger->writeTimedString('payment did not pass verification');
        }
    }
    elseif ($eZSaferPay->isProcessed())
    {
        $logger->writeTimedString($token, 'payment has already been processed');
    }
    else
    {
        $logger->writeTimedString($token, 'invalid token, need valid notify token');
    }

}

eZExecution::cleanExit();
