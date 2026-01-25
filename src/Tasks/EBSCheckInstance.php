<?php

namespace OP;

use Exception;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\Model\List\GroupedList;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Model\ArrayData;
use SilverStripe\EnvironmentCheck\EnvironmentCheck;

class EBSCheckInstance implements EnvironmentCheck
{
    protected $title = "EBSCheckInstance";
    protected $description = 'EBSCheckInstance';
    private static $token; // JSON authentication token

    protected $url;
    protected $prod;
    protected $ignoreCert;

    public function __construct($url = '', $prod = true, $ignoreCert = false)
    {
        $this->url = $url;
        $this->prod = $prod;
        $this->ignoreCert = $ignoreCert;
    }

    public function check()
    {
        $retMessage = "";
        $retCheck = EnvironmentCheck::OK;

        if ($this->prod) {
            $errorType = EnvironmentCheck::ERROR;
        } else {
            $errorType = EnvironmentCheck::WARNING;
        }

        if ($this->ignoreCert) {
            $retMessage .= "SSL cert ignored. \n";
        }


        $connect = $this->connect($this->url);
        if ($connect[0]) {
            $retMessage .= "$connect[1]";
        } else {
            $retCheck = $errorType;
            $retMessage .= "Auth: " . $connect[1];
        }

        $endpoint = Config::inst()->get(EBSCheckInstance::class, 'checkendpoint');
        $room_list_request = $this->request($this->url . $endpoint);

        if ($room_list_request->Code() == 200) {
            $retMessage .= "\n Able to access - endpoint";
        } else {
            $retCheck = $errorType;
            $retMessage .= "\n Could not access endpoint - $endpoint";
        }

        //if not ok, add url to message
        if ($retCheck > 1) {
            $retMessage .= "\n URL: " . $this->url;
        }

        return [$retCheck, $retMessage];
    }

    public function connect($url)
    {
        if (!Environment::getEnv('EBSUSERNAME') || !Environment::getEnv('EBSPASSWORD')) {
            user_error('EBS EBSWebservice authentication not set in .env file');
        }

        $auth = base64_encode(Environment::getEnv('EBSUSERNAME') . ":" . Environment::getEnv('EBSPASSWORD'));
        $this::$token = "Authorization: Basic $auth";

        if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
            Debug::dump($this::$token);
        }

        $result = $this->request($url . "Authentication");

        if ($result->Code() == 200) {

            $message = $result->Content();
            if ($message->Success) {

                // reset with correct token
                $this::$token = $message->Token;
                if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
                    Debug::dump($this::$token);
                }
                return [true, "Authentication: Worked: " . substr($this::$token, 0, 10)];
            } else {
                return [false, "Failed to connect to EBS: invalid credentials"];
            }
        } else {
            return [false, 'Failed to connect to EBS: ' . $result->Code()];
        }

        return null;
    }


    /**
     * requests data from EBS
     * @param string  $url the webservice to launch (string)
     * @param string  $method GET, PUT, POST.
     * @param boolean $body POST or PUT data
     * @return EBSResponse with the parsed data
     */
    public function request($url, $method = "GET", $body = "", $isLongRequest = false)
    {
        $session = curl_init($url);
        if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
            Debug::dump($url);
        }

        // allow self signed certs in dev mode
        if ($this->ignoreCert || Director::isDev() || Director::isTest()) {
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
                curl_setopt($session, CURLOPT_CUSTOMREQUEST, "PUT");
                break;

            case "GET":
                curl_setopt($session, CURLOPT_HTTPGET, true);
        }

        $headers = array("Content-Type: application/json", "Accept: application/json");

        if (!empty($this::$token)) {
            array_push($headers, "Authorization: " . $this::$token);
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

        return new EBSResponse($content, $code, $url);
    }
}
