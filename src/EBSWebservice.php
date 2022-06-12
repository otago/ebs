<?php

namespace OP;

use Exception;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\Debug;
use SilverStripe\Core\Environment;
use SilverStripe\SiteConfig\SiteConfig;

/**
 * EBS webservice object used to interface with your Student Management System
 */
class EBSWebservice
{
    use Configurable;
    use Injectable;

    private static $instance;   // static ebs connection instance
    private static $token; // JSON authentication token
    private static $errors = []; // connection errors
    private static $jsonPutFix = false; // some PHP environments you may need to use this cURL PUT

    /**
     * Connects to EBS. If it fails it will return a null object. You can see
     * errors by looking at EBSWebservice::getErrors()
     * @return EBSWebservice|null
     */

    public static function connect()
    {
        $siteconf = SiteConfig::current_site_config();
        if ($siteconf->DisableEBSConnectivity) {
            return null;
        }
        if (EBSWebservice::$instance) {
            return EBSWebservice::$instance;
        }

        if (!Environment::getEnv('EBSUSERNAME') || !Environment::getEnv('EBSPASSWORD')) {
            user_error('EBS EBSWebservice authentication not set in .env file');
        }

        // get a cache key made in the last 30 minutes
        $ebscache = EBSWebserviceCache::get()
            ->filter([
                'Name' => Environment::getEnv('EBSUSERNAME'),
                'Created:GreaterThan' => strtotime('-30 minutes')
            ]);

        if ($ebscache->count() > 0) {
            EBSWebservice::$instance = new static();
            EBSWebservice::$token = $ebscache->first()->Token;
            if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
                Debug::dump(EBSWebservice::$token);
            }
            return EBSWebservice::$instance;
        }

        $auth = base64_encode(Environment::getEnv('EBSUSERNAME') . ":" . Environment::getEnv('EBSPASSWORD'));
        EBSWebservice::$token = "Authorization: Basic $auth";

        if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
            Debug::dump(EBSWebservice::$token);
        }
        EBSWebservice::$instance = new static();

        $result = EBSWebservice::$instance->request("Authentication");

        if ($result->Code() == 200) {
            $message = $result->Content();
            if ($message->Success) {
                // reset with correct token
                EBSWebservice::$token = $message->Token;
                if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
                    Debug::dump(EBSWebservice::$token);
                }
                $caches = EBSWebserviceCache::get();
                foreach ($caches as $cache) {
                    $cache->delete();
                }
                $cache = EBSWebserviceCache::create();
                $cache->Token = EBSWebservice::$token;
                $cache->Name = Environment::getEnv('EBSUSERNAME');
                $cache->write();
                return EBSWebservice::$instance;
            } else {
                EBSWebservice::$errors [] = 'Failed to connect to EBS: invalid credentials';
            }
        } else {
            EBSWebservice::$errors [] = 'Failed to connect to EBS: ' . $result->Code();
        }

        EBSWebservice::$token = null;
        EBSWebservice::$instance = null;
        return null;
    }

    /**
     * returns an array of errors
     * @return array of errors
     */
    public static function getErrors()
    {
        return EBSWebservice::$errors;
    }

    /**
     * checks the environment type, and returns the connection string
     * @return type string
     */
    public static function getURL()
    {
        return Environment::getEnv('EBSLOCATION');
    }

    /**
     * requests data from EBS
     * @param type $url the webservice to launch (string)
     * @param type $method GET, PUT, POST.
     * @param type $body POST or PUT data
     * @return EBSResponse with the parsed data
     */
    function request($url, $method = "GET", $body = "", $isLongRequest = false)
    {
        $session = curl_init(EBSWebservice::getURL() . $url);
        if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
            Debug::dump(EBSWebservice::getURL() . $url);
        }

        // allow self signed certs in dev mode
        if (Director::isDev() || Director::isTest()) {
            curl_setopt($session, CURLOPT_SSL_VERIFYHOST, '2');
            curl_setopt($session, CURLOPT_SSL_VERIFYPEER, '0');
        }
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_CONNECTTIMEOUT, 5);

        if (Environment::getEnv('SS_OUTBOUND_PROXY') && Environment::getEnv('SS_OUTBOUND_PROXY_PORT')) {
            curl_setopt($session, CURLOPT_PROXY, Environment::getEnv('SS_OUTBOUND_PROXY'));
            curl_setopt($session, CURLOPT_PROXYPORT, Environment::getEnv('SS_OUTBOUND_PROXY_PORT'));
        }

        if (!$isLongRequest) {
            curl_setopt($session, CURLOPT_TIMEOUT, 60);
        } else {
            curl_setopt($session, CURLOPT_TIMEOUT, 180);
        }

        switch ($method) {
            case "POST";
                curl_setopt($session, CURLOPT_POST, true);
                break;

            case "PUT":
                if (EBSWebservice::$jsonPutFix) {
                    curl_setopt($session, CURLOPT_PUT, true);
                    // use a max of 256KB of RAM before going to disk
                    $fp = fopen('php://temp/maxmemory:256000', 'w');
                    if (!$fp) {
                        throw new Exception('could not open temp memory data');
                    }
                    fwrite($fp, $body);
                    fseek($fp, 0);

                    curl_setopt($session, CURLOPT_BINARYTRANSFER, true);
                    curl_setopt($session, CURLOPT_INFILE, $fp); // file pointer
                    curl_setopt($session, CURLOPT_INFILESIZE, strlen($body));
                } else {
                    // this works in older versions of PHP
                    curl_setopt($session, CURLOPT_CUSTOMREQUEST, "PUT");
                }
                break;

            case "GET":
                curl_setopt($session, CURLOPT_HTTPGET, true);
        }

        $headers = ["Content-Type: application/json", "Accept: application/json"];

        if (!empty(EBSWebservice::$token)) {
            array_push($headers, "Authorization: " . EBSWebservice::$token);
        }

        curl_setopt($session, CURLOPT_HTTPHEADER, $headers);

        if (!empty($body)) {
            curl_setopt($session, CURLOPT_POSTFIELDS, $body);
        }

        $content = curl_exec($session);
        $code = curl_getinfo($session, CURLINFO_HTTP_CODE);

        if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
            Debug::dump(curl_error($session));
            Debug::dump(curl_errno($session));
        }

        curl_close($session);

        return new EBSResponse($content, $code, $url);
    }

    /**
     * Protected constructor to prevent creating a new instance of the
     * *Singleton* via the 'connect' operator from outside of this class.
     */
    protected function __construct()
    {
    }

    /**
     * Private clone method to prevent cloning of the instance of the
     * *Singleton* instance.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton*
     * instance.
     *
     * @return void
     */
    public function __wakeup()
    {
    }
}
