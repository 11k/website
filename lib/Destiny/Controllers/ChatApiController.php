<?php
namespace Destiny\Controllers;

use Destiny\Common\Exception;
use Destiny\Common\Annotation\Controller;
use Destiny\Common\Annotation\Route;
use Destiny\Common\Annotation\HttpMethod;
use Destiny\Common\Utils\FilterParams;
use Destiny\Common\User\UserService;
use Destiny\Common\Config;
use Destiny\Common\Response;
use Destiny\Common\MimeType;
use Destiny\Common\Utils\Http;
use Destiny\Chat\ChatIntegrationService;
use Destiny\Messages\PrivateMessageService;
use Destiny\Common\SessionCredentials;
use Destiny\Common\Authentication\AuthenticationService;

/**
 * @Controller
 */
class ChatApiController {

    /**
     * Check the private against the local configuration
     *
     * @param string $privatekey
     * @return boolean
     */
    protected function checkPrivateKey($privatekey){
        return (Config::$a['privateKeys']['chat'] === $privatekey);
    }

    /**
     * @Route ("/api/messages/send")
     * @HttpMethod ({"POST"})
     *
     * Expects the following GET|POST variables:
     *     privatekey=XXXXXXXX
     *     message=string
     *     userid=999
     *     targetuserid=999
     *
     * @param array $params
     * @return Response
     */
    public function sendMessage(array $params) {
        $privateMessageService = PrivateMessageService::instance();
        $chatIntegrationService = ChatIntegrationService::instance();
        $userService = UserService::instance();
        $response = array();

        try {

            FilterParams::required($params, 'privatekey');
            FilterParams::required($params, 'message');
            FilterParams::required($params, 'userid');
            FilterParams::required($params, 'targetuserid');

            if(! $this->checkPrivateKey($params['privatekey']))
                throw new Exception ('Invalid shared private key.');

            if($params['userid'] == $params['targetuserid'])
                throw new Exception ('Cannot send messages to yourself.');

            $ban = $userService->getUserActiveBan ( $params['userid'] );
            if (! empty ( $ban ))
                throw new Exception ("privmsgbanned");

            $oldEnough = $userService->isUserOldEnough ( $params['userid'] );
            if (! $oldEnough)
                throw new Exception ("privmsgaccounttooyoung");

            $user = $userService->getUserById ( $params['userid'] );
            $credentials = new SessionCredentials ( $user );
            $credentials->addRoles ( $userService->getUserRolesByUserId ( $params['userid'] ) );
            $targetuser = $userService->getUserById ( $params['targetuserid'] );
            
            if(empty($targetuser))
                throw new Exception ('notfound');
                
            $canSend = $privateMessageService->canSend( $credentials, $params['targetuserid'] );
            if (! $canSend)
                throw new Exception ("throttled");

            if(empty($user))
                throw new Exception ('notfound');

            $message = array(
                'userid' => $params['userid'],
                'targetuserid' => $params['targetuserid'],
                'message' => $params['message'],
                'isread' => 0
            );

            $message['id'] = $privateMessageService->addMessage( $message );
            $chatIntegrationService->publishPrivateMessage(array(
                'messageid' => $message['id'],
                'message' => $message['message'],
                'username' => $user['username'],
                'userid' => $user['userId'],
                'targetusername' => $targetuser['username'],
                'targetuserid' => $targetuser['userId']
            ));
            $response = new Response ( Http::STATUS_NO_CONTENT );

        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
            $response = new Response ( Http::STATUS_BAD_REQUEST, json_encode ( $response ) );
            $response->addHeader ( Http::HEADER_CONTENTTYPE, MimeType::JSON );
        }
        return $response;
    }


    /**
     * @Route ("/api/twitchsubscriptions")
     * @HttpMethod ({"GET"})
     *
     * Expects the following GET variables:
     *     privatekey=XXXXXXXX
     *
     * @param array $params
     * @return Response
     */
    public function getSubscription(array $params) {
        $response = array();

        try {

            FilterParams::required($params, 'privatekey');
            if(! $this->checkPrivateKey($params['privatekey']))
                throw new Exception ('Invalid shared private key.');

            $userService = UserService::instance();
            $response['authids'] = $userService->getActiveTwitchSubscriptions();

            $response = new Response ( Http::STATUS_OK, json_encode ( $response ) );
            $response->addHeader ( Http::HEADER_CONTENTTYPE, MimeType::JSON );

        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
            $response = new Response ( Http::STATUS_BAD_REQUEST, json_encode ( $response ) );
            $response->addHeader ( Http::HEADER_CONTENTTYPE, MimeType::JSON );
        }
        return $response;
    }

    /**
     * @Route ("/api/twitchsubscriptions")
     * @HttpMethod ({"POST"})
     *
     * Expects the following POST variables:
     *     privatekey=XXXXXXXX
     *
     * @param array $params
     * @return Response
     */
    public function postSubscription(array $params) {
        $response = array();

        try {

            FilterParams::required($params, 'privatekey');
            if(! $this->checkPrivateKey($params['privatekey']))
                throw new Exception ('Invalid shared private key.');

            /*
             * The expected json schema is: {"123": 1, "431": 0}
             * where the key is the twitch user id and the value is whether
             * the user is a subscriber or not
             */
            $subs = json_decode( file_get_contents('php://input'), true );
            $userService = UserService::instance();
            $users = $userService->updateTwitchSubscriptions( $subs );

            $chatIntegrationService = ChatIntegrationService::instance();
            $authenticationService = AuthenticationService::instance ();
            foreach( $users as $user ) {
                $authenticationService->flagUserForUpdate ( $user['userId'] );

                if ( !$user['istwitchsubscriber'] ) // do not announce non-subs
                    continue;

                $chatIntegrationService->sendBroadcast(
                    sprintf("%s is now a Twitch subscriber!", $user['username'] )
                );
            }

            $response = new Response ( Http::STATUS_NO_CONTENT );

        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
            $response = new Response ( Http::STATUS_BAD_REQUEST, json_encode ( $response ) );
            $response->addHeader ( Http::HEADER_CONTENTTYPE, MimeType::JSON );
        }
        return $response;
    }

    /**
     * @Route ("/api/twitchresubscription")
     * @HttpMethod ({"POST"})
     *
     * Expects the following GET variables:
     *     privatekey=XXXXXXXX
     *
     * @param array $params
     * @return Response
     */
    public function postReSubscription(array $params) {
        $response = array();

        try {

            FilterParams::required($params, 'privatekey');
            if(! $this->checkPrivateKey($params['privatekey']))
                throw new Exception ('Invalid shared private key.');

            $subs = json_decode( file_get_contents('php://input'), true );
            $userService = UserService::instance();
            $users = $userService->updateTwitchSubscriptions( $subs );
            foreach( $users as $user ) {
                if ( !$user['istwitchsubscriber'] )
                    continue;

                $chatIntegrationService = ChatIntegrationService::instance();
                $chatIntegrationService->sendBroadcast(
                    sprintf("%s has resubscribed on Twitch!", $user['username'])
                );
            }

            $response = new Response ( Http::STATUS_NO_CONTENT );

        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
            $response = new Response ( Http::STATUS_BAD_REQUEST, json_encode ( $response ) );
            $response->addHeader ( Http::HEADER_CONTENTTYPE, MimeType::JSON );
        }
        return $response;
    }

    /**
     * @Route ("/api/addtwitchsubscription")
     * @HttpMethod ({"POST"})
     *
     * Expects the following POST variables:
     *     privatekey=XXXXXXXX
     *
     * @param array $params
     * @return Response
     */
    public function addSubscription(array $params) {
        $response = array(); // TODO GET RID OF THE COPY PASTE

        try {

            FilterParams::required($params, 'privatekey');
            if(! $this->checkPrivateKey($params['privatekey']))
                throw new Exception ('Invalid shared private key.');

            /*
             * The expected json schema is: {"123": 1, "431": 0}
             * where the key is the twitch user id and the value is whether
             * the user is a subscriber or not
             */
            $data = json_decode( file_get_contents('php://input'), true );
            $userService = UserService::instance();
            $authid = $userService->getTwitchIDFromNick( $data['nick'] );
            if ( $authid ) {
                $users = $userService->updateTwitchSubscriptions( array( $authid => 1 ) );

                $chatIntegrationService = ChatIntegrationService::instance();
                $authenticationService = AuthenticationService::instance ();
                foreach( $users as $user ) {
                    $authenticationService->flagUserForUpdate ( $user['userId'] );

                    if ( !$user['istwitchsubscriber'] ) // do not announce non-subs
                        continue;

                    $chatIntegrationService->sendBroadcast(
                        sprintf("%s is now a Twitch subscriber!", $user['username'] )
                    );
                }
            }
            $response = new Response ( Http::STATUS_OK, json_encode ( ['id' => $authid] ) );
            $response->addHeader ( Http::HEADER_CONTENTTYPE, MimeType::JSON );

        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = $e->getMessage();
            $response = new Response ( Http::STATUS_BAD_REQUEST, json_encode ( $response ) );
            $response->addHeader ( Http::HEADER_CONTENTTYPE, MimeType::JSON );
        }
        return $response;
    }
}
