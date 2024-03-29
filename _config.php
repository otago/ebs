<?php

namespace OP;
use SilverStripe\Core\Config\Config;
use SilverStripe\EnvironmentCheck\EnvironmentCheckSuite;

$testingurl = Config::inst()->get(EBSCheckInstance::class, 'testingurl');
$prod = Config::inst()->get(EBSCheckInstance::class, 'prod');

EnvironmentCheckSuite::register("check", "OP\EBSCheckInstance('$prod')", "EBS - Prod-Live");

if ($testingurl) {
    foreach($testingurl as $key => $url)
    {
        EnvironmentCheckSuite::register("check", "OP\EBSCheckInstance('$url',false,true)", "EBS - $key");
    }
}
