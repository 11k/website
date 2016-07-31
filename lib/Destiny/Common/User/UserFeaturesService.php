<?php
namespace Destiny\Common\User;

use Destiny\Common\Exception;
use Destiny\Common\Service;
use Destiny\Common\Application;

/**
 * @method static UserFeaturesService instance()
 */
class UserFeaturesService extends Service {
    
    /**
     * @var array
     */
    protected $features = null;

    /**
     * @return array<featureName, []>
     */
    public function getNonPseudoFeatures(){
        $features = $this->getFeatures();
        $filtered = [];
        foreach (UserFeature::$NON_PSEUDO_FEATURES as $featureName){
            if(isset($features[$featureName])){
                $filtered[$featureName] = $features[$featureName];
            }
        }
        return $filtered;
    }

    /**
     * @return array<featureName, []>
     */
    public function getFeatures() {
        if ($this->features == null) {
            $conn = Application::instance ()->getConnection ();
            $stmt = $conn->prepare ( 'SELECT featureId, featureName, featureLabel FROM dfl_features ORDER BY featureId ASC' );
            $stmt->execute ();
            $this->features = array ();
            while ( $a = $stmt->fetch () ) {
                $this->features [$a ['featureName']] = $a;
            }
        }
        return $this->features;
    }

    /**
     * @param string $featureName
     * @return array
     * @throws Exception
     */
    public function getFeatureIdByName($featureName) {
        $features = $this->getFeatures ();
        if (! isset ( $features [$featureName] )) {
            throw new Exception ( sprintf ( 'Invalid feature name %s', $featureName ) );
        }
        return $features [$featureName]['featureId'];
    }

    /**
     * Get a list of user features
     *
     * @param int $userId
     * @return array
     */
    public function getUserFeatures($userId) {
        $conn = Application::instance ()->getConnection ();
        $stmt = $conn->prepare ( '
            SELECT DISTINCT b.featureName AS `id` FROM dfl_users_features AS a
            INNER JOIN dfl_features AS b ON (b.featureId = a.featureId)
            WHERE userId = :userId
            ORDER BY a.featureId ASC' );
        $stmt->bindValue ( 'userId', $userId, \PDO::PARAM_INT );
        $stmt->execute ();
        $features = array ();
        while ( $feature = $stmt->fetchColumn () ) {
            $features [] = $feature;
        }
        return $features;
    }

    /**
     * Set a list of user features
     *
     * @param int $userId
     * @param array $features
     */
    public function setUserFeatures($userId, array $features) {
        $this->removeAllUserFeatures ( $userId );
        foreach ( $features as $feature ) {
            $this->addUserFeature ( $userId, $feature );
        }
    }

    /**
     * Add a feature to a user
     *
     * @param int $userId
     * @param string $featureName
     * @return int
     */
    public function addUserFeature($userId, $featureName) {
        $featureId = $this->getFeatureIdByName ( $featureName );
        $conn = Application::instance ()->getConnection ();
        $conn->insert ( 'dfl_users_features', array (
            'userId' => $userId,
            'featureId' => $featureId 
        ) );
        return $conn->lastInsertId ();
    }

    /**
     * Remove a feature from a user
     *
     * @param int $userId
     * @param string $featureName
     */
    public function removeUserFeature($userId, $featureName) {
        $featureId = $this->getFeatureIdByName ( $featureName );
        $conn = Application::instance ()->getConnection ();
        $conn->delete ( 'dfl_users_features', array (
            'userId' => $userId,
            'featureId' => $featureId 
        ) );
    }

    /**
     * Remove a feature from a user
     *
     * @param int $userId
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function removeAllUserFeatures($userId) {
        $conn = Application::instance ()->getConnection ();
        $conn->delete ( 'dfl_users_features', array (
            'userId' => $userId 
        ) );
    }

}