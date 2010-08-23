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

    const ACL_PRIVATE = 'private';
    const ACL_PUBLIC_READ = 'public-read';
    const ACL_PUBLIC_READ_WRITE = 'public-read-write';
    const ACL_AUTHENTICATED_READ = 'authenticated-read';
    const ACL_BUCKET_OWNER_READ = 'bucket-owner-read';
    const ACL_BUCKET_OWNER_FULL_CONTROL = 'bucket-owner-full-control';

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
     * The last Response from Google Storage
     *
     * @var string
     */
    protected $_response;

    /**
     * Stores the headers that come back with a response
     * 
     * @var array
     */
    protected $_responseHeaders;

    /**
     * Stores the Request headers for the last request
     *
     * @var array
     */
    protected $_requestHeaders;

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
        $requestDate = $this->_getRequestTime();
        $message = "PUT\n\n\n$requestDate\n/$name";

        $this->_sendRequest($requestDate, $message, array(
            CURLOPT_HEADER => false,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_NOBODY => true,
            ),
            Tws_Service_Google_Storage::GOOGLE_STORAGE_URI.'/'.$name
        );
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
	* Get MIME type for file
	*
	* @internal Used to get mime types
	* @param string &$file File path
	* @return string
	*/
	protected function _getMimeType($file) {
		$type = false;
		// Fileinfo documentation says fileinfo_open() will use the
		// MAGIC env var for the magic file
		if (extension_loaded('fileinfo') && isset($_ENV['MAGIC']) &&
		($finfo = finfo_open(FILEINFO_MIME, $_ENV['MAGIC'])) !== false) {
			if (($type = finfo_file($finfo, $file)) !== false) {
				// Remove the charset and grab the last content-type
				$type = explode(' ', str_replace('; charset=', ';charset=', $type));
				$type = array_pop($type);
				$type = explode(';', $type);
				$type = trim(array_shift($type));
			}
			finfo_close($finfo);

		// If anyone is still using mime_content_type()
		} elseif (function_exists('mime_content_type'))
			$type = trim(mime_content_type($file));

		if ($type !== false && strlen($type) > 0) return $type;

		// Otherwise do it the old fashioned way
		static $exts = array(
			'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png',
			'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'ico' => 'image/x-icon',
			'swf' => 'application/x-shockwave-flash', 'pdf' => 'application/pdf',
			'zip' => 'application/zip', 'gz' => 'application/x-gzip',
			'tar' => 'application/x-tar', 'bz' => 'application/x-bzip',
			'bz2' => 'application/x-bzip2', 'txt' => 'text/plain',
			'asc' => 'text/plain', 'htm' => 'text/html', 'html' => 'text/html',
			'css' => 'text/css', 'js' => 'text/javascript',
			'xml' => 'text/xml', 'xsl' => 'application/xsl+xml',
			'ogg' => 'application/ogg', 'mp3' => 'audio/mpeg', 'wav' => 'audio/x-wav',
			'avi' => 'video/x-msvideo', 'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg',
			'mov' => 'video/quicktime', 'flv' => 'video/x-flv', 'php' => 'text/x-php'
		);
		$ext = strtolower(pathInfo($file, PATHINFO_EXTENSION));
		return isset($exts[$ext]) ? $exts[$ext] : 'application/octet-stream';
	}

    /**
     * Returns all the buckets for a user
     *
     * @return array
     */
    public function getBuckets() 
    {
        $requestDate = $this->_getRequestTime();
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
        $requestDate = $this->_getRequestTime();
        $message = "HEAD\n\n\n$requestDate\n/$object";

        $response = $this->_sendRequest(
            $requestDate, 
            $message, 
            array(
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_NOBODY => true,
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
     * @param string $object Path of the object to download
     * @return string
     */
    public function getObject($object)
    {
        $requestDate = $this->_getRequestTime();
        $message = "GET\n\n\n$requestDate\n/$object";
        
        $response = $this->_sendRequest($requestDate, $message, array(
            CURLOPT_RETURNTRANSFER => true
            ),
            Tws_Service_Google_Storage::GOOGLE_STORAGE_URI.'/'.$object
        );

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
        $requestDate = $this->_getRequestTime();
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
     * Returns the last set of Request headers
     *
     * @return array
     */
    public function getRequestHeaders()
    {
        return $this->_requestHeaders;
    }

    protected function _getRequestTime()
    {
        return date('D, d M Y H:i:s O');
    }

    /**
     * Returns the last set of Response
     *
     * @return array
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Returns the last set of Response headers
     *
     * @return array
     */
    public function getResponseHeaders()
    {
        return $this->_responseHeaders;
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

    /**
     * Uploads a file to the specified path
     *
     * This will post a local FS file to Google Storage. It doesn't set
     * any metadata and uses its own CURL code so needs proper integration.
     * If the file exists, it will overwrite without warning.
     *
     * Still need to:
     *  - Use the $meta argument
     *  - How to handle existing files
     *  - Check to see if bucket exists first
     *  - Depends on finfo, may need to work on that
     *  - Modify _sendRequest() to handle additional headers
     *
     * @todo Clean Up, this code is UGLY
     * @param string $path Path to the file on the local FS
     * @param string $object bucket/filename on Google Storage
     * @param array $meta Meta Data (Not used yet)
     */
    public function putFile($path, $object, $meta = array())
    {
        $this->_responseHeaders = array();
        $this->_requestHeaders = array();
        $this->_response = array();

        $mimetype = $this->_getMimeType($path);

        list($bucket, $file) = explode('/', $object, 2);
        $requestDate = $this->_getRequestTime();
        $headers = array(
            'Content-Length: '.filesize($path),
            'Content-Type: '.$mimetype,
            'Date: '.$requestDate,
            'User Agent: Tws_Service_Google_Storage-PHP (Mac)',
            );

        if(array_key_exists('acl', $meta)) {
            $headers[] = 'x-goog-acl: '.$meta['acl'];
        }

        $message = "PUT\n\n$mimetype\n$requestDate\n".(array_key_exists('acl', $meta) ? 'x-goog-acl:'.$meta['acl']."\n" : '')."/$object";

        $signature = $this->_generateSignature($message);
        $headers[] = 'Authorization: GOOG1 '.self::$_google_access_key.':'.$signature;
        $uploadFile = realpath($path);
        $fh = fopen($uploadFile, 'r');
        $ch = curl_init($bucket.'.'.Tws_Service_Google_Storage::GOOGLE_STORAGE_URI.'/'.$file);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'saveHeaders'));
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_INFILE, $fh);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($path));
        $response = curl_exec($ch);

        $this->_requestHeaders = explode("\n", curl_getinfo($ch, CURLINFO_HEADER_OUT));
        $this->_response = $response;

        curl_close($ch);
        fclose($fh);
    }

    /**
     * Deletes a bucket
     *
     * Removes an empty bucket. Will return false if the bucket
     * is not empty
     *
     * @param string $name Bucket to remove
     * @return bool
     */
    public function removeBucket($name)
    {
        $requestDate = $this->_getRequestTime();
        $message = "DELETE\n\n\n$requestDate\n/$name";
        
        $this->_sendRequest(
            $requestDate, 
            $message, 
            array(
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_NOBODY => true,
            ), 
            Tws_Service_Google_Storage::GOOGLE_STORAGE_URI.'/'.$name
        );
    }

    /**
     * Deletes an object
     *
     * @param string $object Object to delete
     * @return bool
     */
    public function removeObject($object)
    {
        $requestDate = $this->_getRequestTime();
        $message = "DELETE\n\n\n$requestDate\n/$object";
        
        $this->_sendRequest(
            $requestDate, 
            $message, 
            array(
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_NOBODY => true,
            ), 
            Tws_Service_Google_Storage::GOOGLE_STORAGE_URI.'/'.$object
        );
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
        $this->_responseHeaders = array();
        $this->_requestHeaders = array();
        $this->_response = array();

        $signature = $this->_generateSignature($message);
        $ch = curl_init($uri);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Length: 0',
            'Authorization: GOOG1 '.self::$_google_access_key.':'.$signature,
            'Date: '.$requestDate,
            'User Agent: Tws_Service_Google_Storage-PHP (Mac)',
            ));
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'saveHeaders'));
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt_array($ch, $curlopts);

        if($this->_debug) {
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        }

        $response = curl_exec($ch);

        $this->_requestHeaders = explode("\n", curl_getinfo($ch, CURLINFO_HEADER_OUT));
        $this->_response = $response;

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
