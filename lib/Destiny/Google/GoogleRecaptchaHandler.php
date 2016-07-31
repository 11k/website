<?php
namespace Destiny\Google;

use Destiny\Common\Config;
use Destiny\Common\CurlBrowser;
use Destiny\Common\MimeType;
use Destiny\Common\Exception;
use Destiny\Common\Request;

class GoogleRecaptchaHandler {

    /**
     * @param string $response The user response token provided by the reCAPTCHA to the user and provided to your site on.
     * @param Request $request The request
     * @return bool
     * @throws Exception
     */
    public function resolve($response, Request $request){
        $secret = Config::$a ['g-recaptcha'] ['secret'];
        $remoteip = $request->ipAddress();
        $curl = new CurlBrowser (array (
            'timeout' => 25,
            'url' => 'https://www.google.com/recaptcha/api/siteverify?secret='. $secret .'&response='. $response .'&remoteip=' . $remoteip,
            'contentType' => MimeType::JSON 
        ));
        $data = $curl->getResponse ();

        if(empty($data))
            throw new Exception('Failed to resolve captcha.');

        if($data['success'] != true){
            if(isset($data['error-codes'])){
                switch ($data['error-codes']) {
                    case 'missing-input-secret':
                        throw new Exception('The secret parameter is missing.');
                    case 'invalid-input-secret':
                        throw new Exception('The secret parameter is invalid or malformed.');
                    case 'missing-input-response':
                        throw new Exception('The response parameter is missing.');
                    case 'invalid-input-response':
                        throw new Exception('The response parameter is invalid or malformed.');
                    default:
                        throw new Exception('Failed to resolve captcha.');
                }
            }else{
                throw new Exception('Failed to resolve captcha.');
            }
        }
        return true;
    }

}