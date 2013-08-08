<?php
namespace Destiny\Action\Admin;

use Destiny\Common\Service\SubscriptionsService;
use Destiny\Common\ViewModel;
use Destiny\Common\AppException;
use Destiny\Common\Application;
use Destiny\Common\Annotation\Action;
use Destiny\Common\Annotation\Route;
use Destiny\Common\Annotation\HttpMethod;
use Destiny\Common\Annotation\Secure;

/**
 * @Action
 */
class Subscribers {

	/**
	 * @Route ("/admin/subscribers")
	 * @Secure ({"ADMIN"})
	 *
	 * @param array $params
	 * @throws AppException
	 */
	public function execute(array $params, ViewModel $model) {
		$subService = SubscriptionsService::instance ();
		$model->subscribersT2 = $subService->getSubscriptionsByType ( 'premium' );
		$model->subscribersT1 = $subService->getSubscriptionsByType ( 'standard' );
		return 'admin/subscribers';
	}

}