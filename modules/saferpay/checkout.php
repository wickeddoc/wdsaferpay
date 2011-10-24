<?php

    $http = eZHTTPTool::instance();
    $logger = eZPaymentLogger::CreateForAdd('var/log/eZSaferPayCheckout.log');

    if ( $http->hasVariable( 'token' ) )
    {
        $token = $http->getVariable('token');
        $logger->writeTimedString($token, 'got token');

        $eZSaferPay = eZSaferPay::fetchByToken($token);
        if ($eZSaferPay)
        {
            $status = $eZSaferPay->getTokenStatus($token);
            $logger->writeTimedString($status, 'token status is');
            if ($status)
            {
                switch ($status)
                {
                    case "success":
                    case "failed":
                        if (!$eZSaferPay->isProcessed())
                        {
                            $logger->writeTimedString('processing payment');
                            $checker = new eZSaferPayChecker('saferpay.ini');
                            $checker->createDataFromGET();
                            $checker->setupOrderAndPaymentObject($eZSaferPay->attribute('order_id'));
                            $data = $checker->getSaferPayData();
                            $eZSaferPay->processPayment($data);
                            $verified = $checker->verifyPayment($data);
                            if ($verified && $status == 'success')
                            {
                                $orderID = $eZSaferPay->attribute('order_id');
                                $logger->writeTimedString($orderID, 'payment approved for order');
                                $eZSaferPay->setStatus('success');
                                $checker->order->modifyStatus($checker->ini->variable( 'OrderSettings', 'SuccessID' ));
                                if($checker->completePayment($eZSaferPay->attribute('auth_id')))
                                    $eZSaferPay->setSettled();
                            }
                            else
                            {
                                $orderID = $eZSaferPay->attribute('order_id');
                                if ($status == 'success')
                                    $logger->writeTimedString($orderID, 'payment failed for order');
                                else
                                    $logger->writeTimedString($orderID, 'payment verification failed for order');
                                $eZSaferPay->setStatus('failed');
                                $checker->order->modifyStatus($checker->ini->variable( 'OrderSettings', 'FailedID' ));
                            }
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
