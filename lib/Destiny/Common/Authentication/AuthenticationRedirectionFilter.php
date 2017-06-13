<?php
namespace Destiny\Common\Authentication;

use Destiny\Common\Log;
use Destiny\Common\User\UserService;
use Destiny\Common\User\UserRole;
use Destiny\Common\Exception;
use Destiny\Common\Session;

class AuthenticationRedirectionFilter {

    /**
     * @param AuthenticationCredentials $authCreds
     * @return string
     * @throws Exception
     */
    public function execute(AuthenticationCredentials $authCreds) {
        $authService = AuthenticationService::instance ();
        $userService = UserService::instance ();
        
        // Make sure the creds are valid
        if (! $authCreds->isValid ()) {
            Log::error ( sprintf ( 'Error validating auth credentials %s', var_export ( $authCreds, true ) ) );
            throw new Exception ( 'Invalid auth credentials' );
        }

        if ($authCreds->getEmail())
            $authService->validateEmail( $authCreds->getEmail(), null, true );

        // Account merge
        if (Session::set ( 'accountMerge' ) === '1') {
            // Must be logged in to do a merge
            if (! Session::hasRole ( UserRole::USER )) {
                throw new Exception ( 'Authentication required for account merge' );
            }
            $authService->handleAuthAndMerge ( $authCreds );
            return 'redirect: /profile/authentication';
        }

        // Follow url *notice the set, returning and clearing the var
        $follow = Session::set( 'follow' );

        // If the user profile doesn't exist, go to the register page
        if (! $userService->getUserAuthProviderExists ( $authCreds->getAuthId (), $authCreds->getAuthProvider () )) {
            Session::set ( 'authSession', $authCreds );
            $url = '/register?code=' . urlencode ( $authCreds->getAuthCode () );
            if (! empty( $follow )) {
                $url .= '&follow=' . urlencode ( $follow );
            }
            return 'redirect: '. $url;
        }
        
        // User exists, handle the auth
        $authService->handleAuthCredentials ( $authCreds );
        
        if (! empty ( $follow ) && substr( $follow, 0, 1 ) == '/' ) {
            return 'redirect: ' . $follow;
        }
        return 'redirect: /profile';
    }
}