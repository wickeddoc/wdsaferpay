<?php

class eZSaferPay extends eZPersistentObject
{

    function __construct( $row )
    {
        $this->eZPersistentObject( $row );
    }

    static function definition()
    {
        return array( "fields" => array( "id" => array( 'name' => 'ID',
                                                        'datatype' => 'integer',
                                                        'default' => 0,
                                                        'required' => true ),
                                         "order_id" => array( 'name' => "OrderID",
                                                              'datatype' => 'integer',
                                                              'default' => 0,
                                                              'required' => true,
                                                              'foreign_class' => 'eZOrder',
                                                              'foreign_attribute' => 'id',
                                                              'multiplicity' => '1..*'),
                                         "user_id" => array( 'name' => "UserID",
                                                             'datatype' => 'integer',
                                                             'default' => 0,
                                                             'required' => true,
                                                             'foreign_class' => 'eZUser',
                                                             'foreign_attribute' => 'contentobject_id',
                                                             'multiplicity' => '1..*' ),
                                        "session_id" => array( 'name' => "SessionId",
                                            'datatype' => 'string',
                                            'default' => '',
                                            'required' => false ),
                                         "token" => array( 'name' => "Token",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "cardrefid" => array( 'name' => "CardRefId",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "auth_id" => array( 'name' => "AuthId",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "card_country" => array( 'name' => "CardCountry",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "ip_country" => array( 'name' => "IPCountry",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "ip" => array( 'name' => "IP",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "amount" => array( 'name' => "Amount",
                                                                 'datatype' => 'float',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "status" => array( 'name' => "Status",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "signature" => array( 'name' => "Signature",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),

                                        "providerid" => array( 'name' => "ProviderID",
                                            'datatype' => 'string',
                                            'default' => '',
                                            'required' => false ),
            "providername" => array( 'name' => "ProviderName",
                'datatype' => 'string',
                'default' => '',
                'required' => false ),
            "scddescription" => array( 'name' => "SCDDescription",
                'datatype' => 'string',
                'default' => '',
                'required' => false ),
            "cardtype" => array( 'name' => "CardType",
                'datatype' => 'string',
                'default' => '',
                'required' => false ),
            "cardmask" => array( 'name' => "CardMask",
                'datatype' => 'string',
                'default' => '',
                'required' => false ),
            "cardbrand" => array( 'name' => "CardBrand",
                'datatype' => 'string',
                'default' => '',
                'required' => false ),
            "expirymonth" => array( 'name' => "ExpiryMonth",
                'datatype' => 'string',
                'default' => '',
                'required' => false ),
            "expiryyear" => array( 'name' => "ExpiryYear",
                'datatype' => 'string',
                'default' => '',
                'required' => false ),
            "hostname" => array( 'name' => "HostName",
                'datatype' => 'string',
                'default' => '',
                'required' => false ),
                                         "initiated_at" => array( 'name' => "Initiated",
                                                             'datatype' => 'integer',
                                                             'default' => time(),
                                                             'required' => true ),
                                         "processed_at" => array( 'name' => "Processed",
                                                             'datatype' => 'integer',
                                                             'default' => null,
                                                             'required' => false ),
                                          "settled_at" => array( 'name' => "Settled",
                                                              'datatype' => 'integer',
                                                              'default' => null,
                                                              'required' => false )),
                      'function_attributes' => array(  ),
                      "keys" => array( "id" ),
                      "increment_key" => "id",
                      "class_name" => "eZSaferPay",
                      "name" => "ezsaferpay" );
    }

    public function initTokens()
    {
        $this->setAttribute('token', $this->createToken());
        $this->setAttribute('cardrefid', $this->createCardId());
        $this->store();
    }

    private function createToken()
    {
        $token = null;
        while(!$this->isUnique($token, 'token'))
        {
            $token = hash("sha256", time() + rand(10000,999999));
        }
        return $token;
    }

    private function createCardId()
    {
        $token = null;
        while(!$this->isUnique($token, 'cardrefid'))
        {
            $token = sha1(time() + rand(10000,999999));
        }
        return $token;
    }

    private function isUnique($token, $field)
    {
        if (!isset($token) || is_null($token))
            return false;
        if ($field == 'token')
            $entry = eZSaferPay::fetchByToken($token);
        else
            $entry = eZSaferPay::fetchByCard($token);
        if ($entry)
            return false;
        else
            return true;
    }

    public function getToken()
    {
        return $this->attribute("token");
    }

    static function fetchByToken($token = '')
    {
        $customConds = sprintf(" WHERE token = '%s'", $token);
        $rows = eZPersistentObject::fetchObjectList( eZSaferPay::definition(),
                              null,
                              null,
                              null,
                              1,
                              true,
                              false,
                              null,
                              null,
                              $customConds );
        if (count($rows) > 0)
            return $rows[0];
        else
            return false;
    }

    static function fetchByCard($token = '')
    {
        $customConds = sprintf(" WHERE cardrefid = '%s'", $token);
        $rows = eZPersistentObject::fetchObjectList( eZSaferPay::definition(),
            null,
            null,
            null,
            1,
            true,
            false,
            null,
            null,
            $customConds );
        if (count($rows) > 0)
            return $rows[0];
        else
            return false;
    }

    static function fetchAnyByOrder($order_id = '')
    {
        $customConds = sprintf(" WHERE order_id = %d", $order_id);
        $rows = eZPersistentObject::fetchObjectList( eZSaferPay::definition(),
            null,
            null,
            null,
            1,
            true,
            false,
            null,
            null,
            $customConds );
        if (count($rows) > 0)
            return $rows[0];
        else
            return false;
    }


    static function fetchByOrder($order_id = '')
    {
        $customConds = sprintf(" WHERE order_id = %d AND status='success'", $order_id);
        $rows = eZPersistentObject::fetchObjectList( eZSaferPay::definition(),
            null,
            null,
            null,
            1,
            true,
            false,
            null,
            null,
            $customConds );
        if (count($rows) > 0)
            return $rows[0];
        else
            return false;
    }

    public function isProcessed()
    {
        $processed_at = (int) $this->attribute('processed_at');
        if ($processed_at > 0)
            return true;
        return false;
    }

    public function getOrder()
    {
        if (empty($this->_row))
            throw new Exception("no transaction, have to load a transaction first");
        else
            return $this->_row->order_id;
        return false;
    }

    public function dataToArray($data)
    {
        $result = array();

        $data = substr($data, 4);
        $data = substr($data, 0, strlen($data) -2);
        $data = trim($data);
        $data = explode("\" ", $data);
        foreach ($data as $item)
        {
            list($key, $value) = explode("=", $item);
            $result[strtolower($key)] = trim($value, '"');
        }

        return $result;

    }

    public function processPayment($data)
    {
        if (!empty($data['data']) && !empty($data['signature']))
        {
            $signature = $data['signature'];
            $data = $this->dataToArray($data['data']);
            $this->setAttribute('signature', $signature);
            $this->setAttribute('auth_id', $data['id']);
            $this->setAttribute('ip', $data['ip']);
            $this->setAttribute('ip_country', $data['ipcountry']);
            $this->setAttribute('card_country', $data['cccountry']);
            $this->setAttribute('providerid', $data['providerid']);
            $this->setAttribute('providername', $data['providername']);
            $this->setAttribute('scddescription', $data['scddescription']);
            $this->setAttribute('cardbrand', $data['cardbrand']);
            $this->setAttribute('cardtype', $data['cardtype']);
            $this->setAttribute('cardmask', $data['cardmask']);
            $this->setAttribute('expirymonth', $data['expirymonth']);
            $this->setAttribute('expiryyear', $data['expiryyear']);
            $this->setAttribute('amount', $data['amount']);
            $this->setAttribute('hostname', $_SERVER['HTTP_HOST']);
            $this->store();
        }
        else
        {
            throw new Exception("no valid data or signature in paymentData");
        }
    }


    public function setStatus($status)
    {
            $this->setAttribute('status', $status);
            $this->setAttribute('processed_at', time());
            $this->store();
    }

    public function setSettled()
    {
            $this->setAttribute('settled_at', time());
            $this->store();
    }

}
