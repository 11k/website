<?php
namespace Destiny\Controllers;

use Destiny\Common\Annotation\ResponseBody;
use Destiny\Common\Exception;
use Destiny\Common\Session;
use Destiny\Common\ViewModel;
use Destiny\Common\Utils\FilterParams;
use Destiny\Messages\PrivateMessageService;
use Destiny\Common\Annotation\Controller;
use Destiny\Common\Annotation\Route;
use Destiny\Common\Annotation\HttpMethod;
use Destiny\Common\Annotation\Secure;
use Destiny\Common\User\UserService;
use Destiny\Common\User\UserRole;
use Destiny\Chat\ChatIntegrationService;
use Destiny\Common\SessionCredentials;

/**
 * @Controller
 */
class PrivateMessageController {

    /**
     * @Route ("/profile/messages")
     * @Secure ({"USER"})
     * @HttpMethod ({"GET"})
     *
     * @param ViewModel $viewModel
     * @return string
     */
    public function inbox(ViewModel $viewModel) {
        $userId = Session::getCredentials ()->getUserId ();
        $username = Session::getCredentials ()->getUsername ();

        $privateMessageService = PrivateMessageService::instance();
        $inbox = $privateMessageService->getInboxMessagesByUserId( $userId );

        $viewModel->inbox = $inbox;
        $viewModel->username = $username;
        $viewModel->title = 'Messages';
        return 'profile/inbox';
    }

    /**
     * @Route ("/profile/messages/send")
     * @Secure ({"USER"})
     * @HttpMethod ({"POST"})
     * @ResponseBody
     *
     * Expects the following GET|POST variables:
     *     message=string
     *     recipients[]=username|group
     *
     * @param array $params
     * @return array
     */
    public function sendMessage(array $params) {
        $privateMessageService = PrivateMessageService::instance();
        $chatIntegrationService = ChatIntegrationService::instance();
        $userService = UserService::instance();
        $result = ['success' => false, 'message' => ''];

        try {

            FilterParams::required($params, 'message');
            FilterParams::isarray($params, 'recipients');

            $sessionCredentials = Session::getCredentials ();
            $userId = $sessionCredentials->getUserId ();
            $username = strtolower($sessionCredentials->getUsername());
            $user = $userService->getUserById ( $userId );
            $recipients = array_unique(array_map('strtolower', $params['recipients']));

            if(empty($recipients))
                throw new Exception('Invalid recipients list');

            if(count($recipients) === 1 && $recipients[0] == $username)
                throw new Exception('Cannot send messages to yourself.');

            // Remove the user if its in the list
            $recipients = array_diff($recipients, array($username));

            $ban = $userService->getUserActiveBan ( $userId );
            if (!empty($ban))
                throw new Exception ("You cannot send messages while you are banned.");

            $oldEnough = $userService->isUserOldEnough ( $userId );
            if (!$oldEnough)
                throw new Exception ("Your account is not old enough to send messages.");

            // Because batch sending makes it difficult to run checks on each recipient
            // we only use the batch sending for admins e.g. sending to tiers etc.
            if(Session::hasRole(UserRole::ADMIN)){

                $messages = $privateMessageService->batchAddMessage( $userId, $params['message'], $recipients );
                $chatIntegrationService->publishPrivateMessages($messages);

            }else{

                $recipients = $userService->getUserIdsByUsernames( $params['recipients'] );

                if(empty($recipients))
                    throw new Exception('Invalid recipient value(s)');

                if(count($recipients) > 20)
                    throw new Exception('You may only send to maximum 20 users.');

                $credentials = new SessionCredentials ( $user );
                foreach ($recipients as $recipientId) {
                    $canSend = $privateMessageService->canSend( $credentials, $recipientId );
                    if (! $canSend)
                        throw new Exception ("You have sent too many messages, throttled.");

                    $targetuser = $userService->getUserById ( $recipientId );
                    $message = array(
                        'userid' => $userId,
                        'targetuserid' => $recipientId,
                        'message' => $params['message'],
                        'isread' => 0
                    );
                    $message['id'] = $privateMessageService->addMessage( $message );
                    $chatIntegrationService->publishPrivateMessage(array(
                        'messageid' => $message['id'],
                        'message' => $message['message'],
                        'username' => $sessionCredentials->getUsername(), // non-lowercase
                        'userid' => $userId,
                        'targetusername' => $targetuser['username'],
                        'targetuserid' => $targetuser['userId']
                    ));
                }
            }

            $result['message'] = 'Message sent';
            $result['success'] = true;

        } catch (\Exception $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * @Route ("/profile/messages/openall")
     * @Secure ({"USER"})
     * @ResponseBody
     */
    public function openAll() {
        $privateMessageService = PrivateMessageService::instance();
        $userId = Session::getCredentials()->getUserId();
        $privateMessageService->markAllMessagesRead($userId);
        return ['success' => true];
    }

    /**
     * @Route ("/profile/messages/{targetuserid}")
     * @Secure ({"USER"})
     * @HttpMethod ({"GET"})
     *
     * @param array $params
     * @param ViewModel $viewModel
     * @return string
     * @throws Exception
     * @throws \Destiny\Common\Utils\FilterParamsException
     */
    public function message(array $params, ViewModel $viewModel) {
        FilterParams::required($params, 'targetuserid');

        $privateMessageService = PrivateMessageService::instance();
        $userService = UserService::instance();

        $userId = Session::getCredentials ()->getUserId ();
        $username = Session::getCredentials ()->getUsername ();

        $targetuser = $userService->getUserById($params['targetuserid']);
        if(empty($targetuser))
            throw new Exception('Invalid user');

        $messages = $privateMessageService->getMessagesBetweenUserIdAndTargetUserId( $userId, $params['targetuserid'], 0, 1000 );
        $privateMessageService->markMessagesRead( $userId, $params['targetuserid'] );

        $viewModel->targetuser = $targetuser;
        $viewModel->messages = $messages;
        $viewModel->username = $username;
        $viewModel->userId = $userId;
        $viewModel->title = 'Message';
        return 'profile/message';
    }

}
