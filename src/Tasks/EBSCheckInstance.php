<?php

namespace OP;

use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\GroupedList;
use SilverStripe\Security\Member;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\View\ArrayData;

class EBSCheckInstance extends BuildTask
{
    protected $title = "EBSCheckInstance";
    protected $description = 'EBSCheckInstance';
    private static $token; // JSON authentication token
    private $bad = '<span style="color:red"> Not Working</span>';
    private $good = '<span style="color:green"> Working</span>';


    public function run($request)
    {
        foreach(self::config()->get('testurl') as $url)
        {
            echo "<strong>$url</strong><br>";
            $this->connect($url);


            $endpoint=self::config()->get('checkendpoint');
            $room_list_request = $this->request($url . $endpoint);

            if ($room_list_request->Code() == 200) {
                echo "$endpoint: " . $this->good . "<br>";
            }else
            {
                echo "$endpoint: " . $this->bad . "<br>";
            }

            echo "<br>";
        }
    }



    public function connect($url) {

        $authentication = EBSWebservice::config()->get('authentication');
        if (!isset($authentication['username']) || !isset($authentication['password'])) {
            user_error('EBS EBSWebservice authentication not set in .yml file');
        }

        $auth = base64_encode($authentication['username'] . ":" . $authentication['password']);
        $this::$token = "Authorization: Basic $auth";

        if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
            Debug::dump($this::$token);
        }


        $result = $this->request($url."Authentication");

        if ($result->Code() == 200) {

            $message = $result->Content();
            if ($message->Success) {

                // reset with correct token
                $this::$token = $message->Token;
                if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
                    Debug::dump($this::$token);
                }
                echo "Authentication: " . $this->good . "<br>";
                return $this::$token;
            } else {
                echo 'Failed to connect to EBS: invalid credentials ' . $this->bad . "<br>";
            }
        } else {
            echo 'Failed to connect to EBS: ' . $result->Code() . ' ' . $this->bad . "<br>";
        }
        //var_dump($errors);
        return null;
    }


    /**
     * requests data from EBS
     * @param type $url the webservice to launch (string)
     * @param type $method GET, PUT, POST.
     * @param type $body POST or PUT data
     * @return array with the parsed data
     */
    public function request($url, $method = "GET", $body = "", $isLongRequest = false) {
        $session = curl_init($url);
        if (isset($_REQUEST['debug']) && (Director::isDev() || Director::isTest())) {
            Debug::dump($url);
        }

        // allow self signed certs in dev mode
        if (Director::isDev() || Director::isTest()) {
            curl_setopt($session, CURLOPT_SSL_VERIFYHOST, '2');
            curl_setopt($session, CURLOPT_SSL_VERIFYPEER, '0');
        }
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_CONNECTTIMEOUT, 5);

        if(Environment::getEnv('SS_OUTBOUND_PROXY') && Environment::getEnv('SS_OUTBOUND_PROXY_PORT')) {
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

        curl_close($session);

        return new EBSResponse($content, $code, $url);
    }

}
