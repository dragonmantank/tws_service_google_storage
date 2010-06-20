<?php
/**
 * A PHP Wrapper for interacting with the Google Storage service
 *
 * PHP Version 5
 *
 * LICENSE: This source file is subject to the New BSD License,
 * available at http://github.com/dragonmantank/tws_service_google_storage/blob/master/README
 * If you did not receive a copy of the New BSD License and are unable
 * to obtain it via the web, please send a note to chris@ctankersley.com so
 * that a copy can be sent to you.
 *
 * @package Tws_Service_Google_Storage
 * @author Chris Tankersley <chris@ctankersley.com>
 * @copyright 2010 Chris Tankersley
 * @license http://github.com/dragonmantank/tws_service_google_storage/blob/master/README New BSD
 * @link http://github.com/dragonmantank/tws_service_google_storage/
 */

/**
 * A PHP Wrapper for interacting with the Google Storage Service
 *
 * This class allows a user to interact with the Google Storage service's buckets and
 * objects. Users can add, delete, and modify them using the format laid out by the
 * Zend Framework Zend_Service_Amazon_S3 class.
 *
 * @package Tws_Service_Google_Storage
 * @author Chris Tankersley <chris@ctankersley.com>
 * @copyright 2010 Chris Tankersley
 * @license http://github.com/dragonmantank/tws_service_google_storage/blob/master/README New BSD
 * @link http://github.com/dragonmantank/tws_service_google_storage/
 */
class Tws_Service_Google_Storage
{
    /**
     * URL to Google Storage
     * @var string
     */
    const GOOGLE_STORAGE_URI = 'commondatastorage.googleapis.com';

    /**
     * Flag for Debug Mode
     *
     * @var bool
     */
    protected $_debug = false;

    /**
     * Key provided by Google for accessing services
     * 
     * @var string
     */
    static protected $_google_access_key;

    /**
     * Secret key that goes along with the Google Access Key
     *
     * Secret key is used for signing signatures
     *
     * @var string
     */
    static protected $_google_access_key_secret;

    /**
     * Stores the headers that come back with a response
     * 
     * @var array
     */
    protected $_responseHeaders;

    /**
     * Creates a Google Storage object
     *
     * @param string $google_access_key Google Access Key
     * @param string $google_access_key_secret Google Access Key Secret
     */
    public function __construct($google_access_key = null, $google_access_key_secret = null)
    {
        if ($google_access_key != null) {
            self::$_google_access_key = $google_access_key;
        }

        if ($google_access_key_secret != null) {
            self::$_google_access_key_secret = $google_access_key_secret;
        }
    }

    /**
     * Creates a new bucket
     *
     * @todo Change to use _sendRequest()
     * @param string $name Name of the new bucket
     */ 
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

    /**
     * Removes all the objects from a bucket
     *
     * @todo Implement
     * @param string $name Bucket Name
     */
    public function cleanBucket($name)
    {
    }

    /**
     * Creates the signing signature for a request
     *
     * @todo Support extended headers
     * @param string $message Message to sign
     * @return string
     */
    protected function _generateSignature($message)
    {
        $message = utf8_encode($message);
        $hash = base64_encode(hash_hmac('sha1', $message, self::$_google_access_key_secret, true));
        return $hash;
    }

    /**
     * Returns all the buckets for a user
     *
     * @return array
     */
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

    /**
     * Returns the information for an object
     *
     * @param string $object Path of object to query
     * @return array
     */
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

    /**
     * Returns the data from an object
     * 
     * Returns the object into memory, which can be written to
     * disk with file_get_contents()
     *
     * @todo Change to use _sendRequest()
     * @param string $object Path of the object to download
     * @return string
     */
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

    /**
     * Returns all the objects with basic info in a bucket
     *
     * @param string $bucket Bucket to list
     * @return array
     */
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

    /**
     * Returns if the bucket is available
     *
     * @param string $name Bucket to query
     * @return bool
     */
    public function isBucketAvailable($name)
    {
    }

    /**
     * Returns if the object is available
     *
     * @param string $object Object to query
     * @return bool
     */
    public function isObjectAvailable($object)
    {
    }

    public function putObject($object, $data, $meta)
    {
    }

    public function putFile($path, $object, $meta)
    {
    }

    /**
     * Deletes a bucket
     *
     * Removes an empty bucket. Will return false if the bucket
     * is not empty
     *
     * @todo Change to use _sendRequest()
     * @param string $name Bucket to remove
     * @return bool
     */
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

    /**
     * Deletes an object
     *
     * @param string $object Object to delete
     * @return bool
     */
    public function removeObject($object)
    {
    }

    /**
     * Saves the headers from a response
     *
     * @param resource $ch cURL resource
     * @param string $header Header
     * @return int
     */
    public function saveHeaders($ch, $header)
    {
        $this->_responseHeaders[] = $header;
        return strlen($header);
    }

    /**
     * Sends a request to Google Storage
     *
     * @param string $requestDate Time of the request
     * @param string $message Message to sign
     * @param array $curlopts Additional cURL options for the request
     * @param string $uri Full URI for the request
     * @return string
     */
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

        if($this->_debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }

        $response = curl_exec($ch);

        if($this->_debug) {
            echo $response;
        }

        curl_close($ch);

        return $response;
    }

    public function setDebug($value)
    {
        if(is_bool($value)) {
            $this->_debug = $value;
        } else {
            throw new Exception("Debug Flag must be a boolean value");
        }
    }

    /**
     * Sets the keys for Google storage
     *
     * @param string $google_access_key Google Access Key
     * @param string $google_access_key_secret Google Access Key Secret
     */
    static public function setKeys($google_access_key, $google_access_key_secret)
    {
        self::$_google_access_key = $google_access_key;
        self::$_google_access_key_secret = $google_access_key_secret;
    }
}
