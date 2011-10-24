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
                                         "token_success" => array( 'name' => "TokenSuccess",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "token_failed" => array( 'name' => "TokenFailed",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "token_back" => array( 'name' => "TokenBack",
                                                                 'datatype' => 'string',
                                                                 'default' => '',
                                                                 'required' => false ),
                                         "token_notify" => array( 'name' => "TokenNotify",
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
        $this->setAttribute('token_success', $this->createToken());
        $this->setAttribute('token_failed', $this->createToken());
        $this->setAttribute('token_back', $this->createToken());
        $this->setAttribute('token_notify', $this->createToken());
        $this->store();
    }

    private function createToken()
    {
        $token = null;
        while(!$this->isUnique($token))
        {
            $token = sha1(time() + rand(10000,999999));
        }
        return $token;
    }

    private function isUnique($token)
    {
        if (!isset($token))
            return false;
        $entry = eZSaferPay::fetchByToken($token);
        $log = sprintf("testing for token: %s - got back: %s\n", $token, print_r($entry, true));
        file_put_contents('/tmp/saferpay.log', $log, FILE_APPEND);
        if ($entry)
            return false;
        else
            return true;
    }

    public function getToken($name)
    {
        $token_name = sprintf("token_%s", $name);
        if ($this->hasAttribute($token_name))
                return $this->attribute($token_name);
        return false;
    }

    static function fetchByToken($token = '')
    {
        $customConds = sprintf(" WHERE token_success = '%s' OR token_notify = '%s' OR token_failed = '%s' OR token_back = '%s'", $token, $token, $token, $token);
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

    public function getTokenStatus($token)
    {
        $keys = array('success', 'failed', 'back', 'notify');
        foreach ($keys as $key)
        {
            $token_name = sprintf("token_%s", $key);
            if ($this->attribute($token_name) == $token)
                return $key;
        }
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

    private function dataToArray($data)
    {
        $result = array();

        $data = substr($data, 4);
        $data = substr($data, 0, strlen($data) -2);
        $data = trim($data);
        $data = explode(" ", $data);
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
            file_put_contents('/tmp/saferpay.log', print_r($data, true), FILE_APPEND);
            $this->setAttribute('signature', $signature);
            $this->setAttribute('auth_id', $data['id']);
            $this->setAttribute('ip', $data['ip']);
            $this->setAttribute('ip_country', $data['ipcountry']);
            $this->setAttribute('card_country', $data['cccountry']);
            $this->setAttribute('amount', $data['amount']);
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
