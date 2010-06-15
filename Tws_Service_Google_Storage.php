<?php

class Tws_Service_Google_Storage
{
    const GOOGLE_STORAGE_URI = 'commondatastorage.googleapis.com';

    static protected $_google_access_key;
    static protected $_google_access_key_secret;

    protected $_responseHeaders;

    public function __construct($google_access_key = null, $google_access_key_secret = null)
    {
        if ($google_access_key != null) {
            self::$_google_access_key = $google_access_key;
        }

        if ($google_access_key_secret != null) {
            self::$_google_access_key_secret = $google_access_key_secret;
        }
    }

    public function createBucket($name)
    {
        $requestDate = date('D, d M Y H:i:s T');
        $message = "PUT\n\n\n$requestDate\n/$name";
        $signature = $this->_generateSignature($message);

        $ch = curl_init('commondatastorage.googleapis.com/'.$name);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Length: 0',
            'Authorization: GOOG1 '.self::$_google_access_key.':'.$signature,
            'Date: '.$requestDate,
            'User Agent: Tws_Service_Google_Storage-PHP (Mac)',
            ));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_NOBODY, true);

        curl_exec($ch);
        curl_close($ch);
    }

    public function cleanBucket($name)
    {
    }


    protected function _generateSignature($message)
    {
        $message = utf8_encode($message);
        $hash = base64_encode(hash_hmac('sha1', $message, self::$_google_access_key_secret, true));
        return $hash;
    }

    public function getBuckets() 
    {
        $requestDate = date('D, d M Y H:i:s T');
        $message = "GET\n\n\n$requestDate\n/";

        $response = $this->_sendRequest($requestDate, $message, array(CURLOPT_RETURNTRANSFER => true));
        $xml = simplexml_load_string($response);

        $bucketList = array();
        foreach($xml->Buckets as $name => $buckets) {
            foreach($buckets as $bucket) {
                $bucketList[(string)$bucket->Name] = array('CreationDate' => (string)$bucket->CreationDate);
            }
        }

        return $bucketList;
    }

    public function getInfo($object)
    {
        $requestDate = date('D, d M Y H:i:s T');
        $message = "HEAD\n\n\n$requestDate\n/$object";

        $response = $this->_sendRequest(
            $requestDate, 
            $message, 
            array(
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_NOBODY => true,
                CURLOPT_HEADERFUNCTION, array($this, 'saveHeaders'),
            ),
            Tws_Service_Google_Storage::GOOGLE_STORAGE_URI.'/'.$object
        );

        $response = $this->_responseHeaders;
        $info = array();
        foreach($response as $line) {
            if(strpos($line, ':')) {
                list($header, $data) = explode(':', $line, 2);
                $info[$header] = trim($data);
            }
        }

        return $info;
    }

    public function getObject($object)
    {
        $requestDate = date('D, d M Y H:i:s T');
        $message = "GET\n\n\n$requestDate\n/$object";
        $signature = $this->_generateSignature($message);

        $ch = curl_init('commondatastorage.googleapis.com/'.$object);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Length: 0',
            'Authorization: GOOG1 '.self::$_google_access_key.':'.$signature,
            'Date: '.$requestDate,
            'User Agent: Tws_Service_Google_Storage-PHP (Mac)',
            ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    public function getObjectsByBucket($bucket)
    {
        $requestDate = date('D, d M Y H:i:s T');
        $message = "GET\n\n\n$requestDate\n/$bucket/";

        $response = $this->_sendRequest(
            $requestDate, 
            $message, 
            array(
                CURLOPT_RETURNTRANSFER => true
            ), 
            $bucket.'.'.Tws_Service_Google_Storage::GOOGLE_STORAGE_URI
        );

        $xml = simplexml_load_string($response);

        $fileList = array();
        foreach($xml->Contents as $name => $object) {
            $fileList[(string)$object->Key] = array(
                'LastModified' => (string)$object->LastModified,
                'ETag' => (string)$object->ETag,
                'Size' => (string)$object->Size,
                'StorageClass' => (string)$object->StorageClass,
            );
        }

        return $fileList;
    }

    public function isBucketAvailable($name)
    {
    }

    public function isObjectAvailable($object)
    {
    }

    public function putObject($object, $data, $meta)
    {
    }

    public function putFile($path, $object, $meta)
    {
    }

    public function removeBucket($name)
    {
        $requestDate = date('D, d M Y H:i:s T');
        $message = "DELETE\n\n\n$requestDate\n/$name";
        $signature = $this->_generateSignature($message);

        $ch = curl_init('commondatastorage.googleapis.com/'.$name);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Length: 0',
            'Authorization: GOOG1 '.self::$_google_access_key.':'.$signature,
            'Date: '.$requestDate,
            'User Agent: Tws_Service_Google_Storage-PHP (Mac)',
            ));
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_NOBODY, true);

        curl_exec($ch);
        curl_close($ch);
    }

    public function removeObject($object)
    {
    }

    public function saveHeaders($ch, $header)
    {
        $this->_responseHeaders[] = $header;
        return strlen($header);
    }

    protected function _sendRequest($requestDate, $message, $curlopts = array(), $uri = Tws_Service_Google_Storage::GOOGLE_STORAGE_URI)
    {
        $signature = $this->_generateSignature($message);
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Length: 0',
            'Authorization: GOOG1 '.self::$_google_access_key.':'.$signature,
            'Date: '.$requestDate,
            'User Agent: Tws_Service_Google_Storage-PHP (Mac)',
            ));
        curl_setopt_array($ch, $curlopts);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    static public function setKeys($google_access_key, $google_access_key_secret)
    {
        self::$_google_access_key = $google_access_key;
        self::$_google_access_key_secret = $google_access_key_secret;
    }
}
