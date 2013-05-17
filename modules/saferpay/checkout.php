<?php

    $http = eZHTTPTool::instance();
    $logger = eZPaymentLogger::CreateForAdd('var/log/eZSaferPayCheckout.log');

    if ( $http->hasVariable( 'token' ) )
    {
        $token = $http->getVariable('token');
        $logger->writeTimedString($token, 'got token');

        $eZSaferPay = eZSaferPay::fetchByToken($token);
        if ($eZSaferPay && $http->hasVariable( 'op' ))
        {
            $logger->writeTimedString($status, 'token status is');
            $status = $http->getVariable( 'op' );
            if ($status)
            {
                switch ($status)
                {
                    case "ok":
                        sleep(3);
                        eZBasket::cleanupCurrentBasket(false);
                        $url = "/shop/orderview/" . $eZSaferPay->attribute('order_id');
                    break;
                    case "nok":
                        if (!$eZSaferPay->isProcessed())
                        {
                            $logger->writeTimedString('processing payment');
                            $checker = new eZSaferPayChecker('saferpay.ini');
                            $checker->setupOrderAndPaymentObject($eZSaferPay->attribute('order_id'));
                            $orderID = $eZSaferPay->attribute('order_id');
                            $logger->writeTimedString($orderID, 'payment failed for order');
                            $eZSaferPay->setStatus('failed');
                            $checker->order->modifyStatus($checker->ini->variable( 'OrderSettings', 'FailedID' ));
                            $checker->approvePayment();
                            $checker->order->activate();
                        }
                        else
                        {
                            $logger->writeTimedString($token, 'payment has already been processed');
                        }
                        $url = "/shop/orderview/" . $eZSaferPay->attribute('order_id');
                    break;
                    case "back":
                        $eZSaferPay->setAttribute('status', 'back');
                        $eZSaferPay->store();
                        $url = "/shop/basket";
                    break;
                    default:
                        $url = "/";
                }
            }
        }
        else
        {
            $logger->writeTimedString($e->getMessage());
            $url = "/";
        }
    }
    else
    {
        $logger->writeTimedString('token was not set');
        $url = "/";
    }

    $logger->writeTimedString($url, 'redirecting to');
    eZHTTPTool::redirect($url);
    eZExecution::cleanExit();


