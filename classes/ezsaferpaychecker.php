<?php
//
// Definition of eZSaferPayChecker class
//
// Created on: <06-Mar-2011 23:57:17 dl>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ SaferPay Payment Gateway
// SOFTWARE RELEASE: 1.0
// COPYRIGHT NOTICE: Copyright (C) 2011 Yves Thommes <ythommes@gmail.com>
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
//
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
//
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
//
//
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/*! \file ezSaferPaychecker.php
*/

class eZSaferPayChecker extends eZPaymentCallbackChecker
{
    /*!
        Constructor.
    */
    function __construct( $iniFile )
    {
        $this->logger   = eZPaymentLogger::CreateForAdd( 'var/log/eZSaferPayChecker.log' );
        $this->ini      = eZINI::instance( $iniFile );
    }


    /*!
        Asks paypal's server to validate callback.
    */
    function requestValidation()
    {
        $server     = $this->ini->variable( 'ServerSettings', 'ServerName');
        //$serverPort = $this->ini->variable( 'ServerSettings', 'ServerPort');
        $serverPort = 80;
        $requestURI = $this->ini->variable( 'ServerSettings', 'RequestURI');
        $request    = $this->buildRequestString();
        $response   = $this->sendPOSTRequest( $server, $serverPort, $requestURI, $request);

        $this->logger->writeTimedString( $response, 'requestValidation. response from server is' );

        if( $response && strcasecmp( $response, 'VERIFIED' ) == 0 )
        {
            return true;
        }

        $this->logger->writeTimedString( 'invalid response' );
        return false;
    }

    /*!
        Convinces of completion of the payment.
    */
    function checkPaymentStatus()
    {
        if( $this->checkDataField( 'payment_status', 'Completed' ) )
        {
            return true;
        }

        $this->logger->writeTimedString( 'checkPaymentStatus faild' );
        return false;
    }

    // overrides
    /*!
        Creates resquest string which is used to 
        confirm paypal's callback.
    */
    function buildRequestString()
    {
        $request = "cmd=_notify-validate";
        foreach( $this->callbackData as $key => $value )
        {
            $request .= "&$key=".urlencode( $value );
        }
        return $request;
    }
    
    function handleResponse( $socket )
    {
        if( $socket )
        {
            while ( !feof( $socket ) )
            {
                $response = fgets ( $socket, 1024 );
            }
      
            fclose( $socket );
            return $response;
        }

        $this->logger->writeTimedString( "socket = $socket is invalid.", 'handlePOSTResponse faild' );
        return null;
    }

    public function getSaferPayData()
    {
        return array(
            'data' => $this->getFieldValue('DATA'),
            'signature' => $this->getFieldValue('SIGNATURE')
        );
    }

    // overrides
    /*!
        we override this to add the urldecode
    */
    public function createDataFromGET()
    {
        $this->logger->writeTimedString( 'createDataFromGET' );
        $this->callbackData = array();

        $query_string = eZSys::serverVariable( 'QUERY_STRING' );
        if( $query_string )
        {
            $key_value_pairs = explode( '&', $query_string );

            foreach( $key_value_pairs as $key_value )
            {
                $data = explode( '=', $key_value );
                $this->callbackData[$data[0]] = urldecode($data[1]);
                $this->logger->writeTimedString( "$data[0] = $data[1]" );
            }
        }

        return ( count( $this->callbackData ) > 0 );
    }


    /*!
        Asks saferpay server to validate callback.
    */
    public function verifyPayment($data)
    {
        if (!empty($data['data']) && !empty($data['signature']))
        {
            $request_params = array();
            foreach ($data as $key => $value)
            {
                $request_params[] = sprintf("%s=%s", strtoupper($key), urlencode($value));
            }
            $requestServer   = $this->ini->variable( 'ServerSettings', 'ServerName' );
            $createPaymentURI= $this->ini->variable( 'ServerSettings', 'VerifyPayConfirmURI' );
            $request_params = join("&", $request_params);
            $request_url =  $requestServer . $createPaymentURI;
            $final_url = sprintf("%s?%s", $request_url, $request_params);

            $this->logger->writeTimedString( $final_url, 'verifyPayment. URL is' );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $final_url);
            curl_setopt($ch, CURLOPT_PORT, 443);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            $verifyResult = curl_exec($ch);
            $this->logger->writeTimedString( $verifyResult, 'verifyPayment. response was' );
            $res = explode(":", $verifyResult);
            if ($res[0] == 'OK')
                return true;
        }
        else
        {
            return false;
        }
    }

    public function completePayment($id)
    {
        $saferpay_params = array(
            'ID' => $id,
            'ACCOUNTID' => $this->ini->variable( 'MerchantSettings', 'AccountID' ),
            'spPassword' => $this->ini->variable( 'MerchantSettings', 'Password' )
        );
        $request_params = array();
        foreach ($saferpay_params as $key => $value)
        {
            $request_params[] = sprintf("%s=%s", strtoupper($key), $value);
        }
        $requestServer   = $this->ini->variable( 'ServerSettings', 'ServerName' );
        $createPaymentURI= $this->ini->variable( 'ServerSettings', 'PayCompleteURI' );
        $request_params = join("&", $request_params);
        $request_url =  $requestServer . $createPaymentURI;
        $final_url = sprintf("%s?%s", $request_url, $request_params);

        $this->logger->writeTimedString( $final_url, 'completePayment. URL is' );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $final_url);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $completeResult = curl_exec($ch);
        $this->logger->writeTimedString( $completeResult, 'completePayment. response was' );
        if ($completeResult == 'OK')
            return true;
        return false;
    }

    public function executePayment($action, $amount, $cardrefid, $orderId, $spAccount)
    {

        $saferpay_params = array(
            'CARDREFID' => $cardrefid,
            'ORDERID' => $orderId,
            'AMOUNT' => (int) $amount,
            'ACTION' => $action,
            'CURRENCY' => $this->ini->variable( 'MerchantSettings', 'Currency' ),
            'ACCOUNTID' => $this->ini->variable( 'MerchantSettings', 'AccountID' ),
            'spPassword' => $this->ini->variable( 'MerchantSettings', 'Password' )
        );
        $request_params = array();
        foreach ($saferpay_params as $key => $value)
        {
            $request_params[] = sprintf("%s=%s", strtoupper($key), $value);
        }
        $requestServer   = $this->ini->variable( 'ServerSettings', 'ServerName' );
        $executeURI= $this->ini->variable( 'ServerSettings', 'ExecuteURI' );
        $request_params = join("&", $request_params);
        $request_url =  $requestServer . $executeURI;
        $final_url = sprintf("%s?%s", $request_url, $request_params);

        $this->logger->writeTimedString( $final_url, 'executePayment. URL is' );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $final_url);
        curl_setopt($ch, CURLOPT_PORT, 443);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $completeResult = curl_exec($ch);
        $this->logger->writeTimedString( $completeResult, 'executePayment. response was' );
        if (strtolower(substr($completeResult, 0, 2)) == 'ok')
        {
            $completeResult = substr($completeResult, 3);
            $completeResult = eZSaferPay::dataToArray($completeResult);
            $this->logger->writeTimedString( print_r($completeResult, true), 'executePayment. expanded response was' );
            return $completeResult;
        }
        return false;

    }


}

