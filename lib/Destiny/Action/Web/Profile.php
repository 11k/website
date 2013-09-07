<?php
namespace Destiny\Action\Web;

use Destiny\Common\Utils\Date;
use Destiny\Common\Service\AuthenticationService;
use Destiny\Common\Service\UserFeaturesService;
use Destiny\Common\Service\UserService;
use Destiny\Common\Service\SubscriptionsService;
use Destiny\Common\Session;
use Destiny\Common\Exception;
use Destiny\Common\Utils\Country;
use Destiny\Common\ViewModel;
use Destiny\Common\Config;
use Destiny\Common\UserFeature;
use Destiny\Common\Annotation\Action;
use Destiny\Common\Annotation\Route;
use Destiny\Common\Annotation\HttpMethod;
use Destiny\Common\Annotation\Secure;
use Destiny\Common\Annotation\Transactional;

/**
 * @Action
 */
class Profile {

	/**
	 * @Route ("/profile")
	 * @HttpMethod ({"GET"})
	 * @Secure ({"USER"})
	 *
	 * @param array $params
	 * @param ViewModel $model
	 * @return string
	 */
	public function executeGet(array $params, ViewModel $model) {
		$userService = UserService::instance ();
		$subscription = SubscriptionsService::instance ()->getUserActiveSubscription ( Session::getCredentials ()->getUserId () );
		if (empty ( $subscription )) {
			$subscription = SubscriptionsService::instance ()->getUserPendingSubscription ( Session::getCredentials ()->getUserId () );
		}
		$model->title = 'Profile';
		$model->user = $userService->getUserById ( Session::getCredentials ()->getUserId () );
		$model->subscription = $subscription;
		return 'profile';
	}

	/**
	 * @Route ("/profile")
	 * @HttpMethod ({"POST"})
	 * @Secure ({"USER"})
	 * @Transactional
	 *
	 * @param array $params
	 * @param ViewModel $model
	 * @throws Exception
	 * @return string
	 */
	public function executePost(array $params, ViewModel $model) {
		// Get user
		$userService = UserService::instance ();
		$userFeaturesService = UserFeaturesService::instance ();
		$authService = AuthenticationService::instance ();
		$user = $userService->getUserById ( Session::getCredentials ()->getUserId () );
		if (empty ( $user )) {
			throw new Exception ( 'Invalid user' );
		}
		
		$username = (isset ( $params ['username'] ) && ! empty ( $params ['username'] )) ? $params ['username'] : $user ['username'];
		$email = (isset ( $params ['email'] ) && ! empty ( $params ['email'] )) ? $params ['email'] : $user ['email'];
		$country = (isset ( $params ['country'] ) && ! empty ( $params ['country'] )) ? $params ['country'] : $user ['country'];
		
		try {
			AuthenticationService::instance ()->validateUsername ( $username, $user );
			AuthenticationService::instance ()->validateEmail ( $email, $user );
			if (! empty ( $country )) {
				$countryArr = Country::getCountryByCode ( $country );
				if (empty ( $countryArr )) {
					throw new Exception ( 'Invalid country' );
				}
				$country = $countryArr ['alpha-2'];
			}
		} catch ( Exception $e ) {
			$model->title = 'Profile';
			$model->user = $user;
			$model->error = $e;
			return 'profile';
		}
		
		// Date for update
		$userData = array (
			'username' => $username,
			'country' => $country,
			'email' => $email 
		);
		
		// Is the user changing their name?
		if (strcasecmp ( $username, $user ['username'] ) !== 0) {
			$nameChangeCount = intval ( $user ['nameChangedCount'] );
			// have they hit their limit
			if ($nameChangeCount >= Config::$a ['profile'] ['nameChangeLimit']) {
				throw new Exception ( 'You have reached your name change limit' );
			} else {
				$userData ['nameChangedDate'] = Date::getDateTime ( 'NOW' )->format ( 'Y-m-d H:i:s' );
				$userData ['nameChangedCount'] = $nameChangeCount + 1;
			}
		}
		
		// Update user
		$userService->updateUser ( $user ['userId'], $userData );
		$authService->flagUserForUpdate ( $user ['userId'] );
		
		$subscription = SubscriptionsService::instance ()->getUserActiveSubscription ( $user ['userId'] );
		if (empty ( $subscription )) {
			$subscription = SubscriptionsService::instance ()->getUserPendingSubscription ( $user ['userId'] );
		}
		
		$model->title = 'Profile';
		$model->user = $userService->getUserById ( $user ['userId'] );
		$model->subscription = $subscription;
		$model->profileUpdated = true;
		return 'profile';
	}

}
