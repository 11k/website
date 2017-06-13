<?php
namespace Destiny\Common;

use Destiny\Common\Utils\Date;
use Destiny\Common\Utils\Options;
use Psr\Log\LoggerInterface;

class Scheduler {
    
    /**
     * @var array
     */
    public $schedule = array ();

    /**
     * @var array
     */
    private $struct = array(
        'action' => '',
        'lastExecuted' => '',
        'frequency' => '',
        'period' => '',
        'executeCount' => 0
    );

    /**
     * [logger,schedule]
     *
     * @param array $args
     */
    public function __construct(array $args = array()) {
        Options::setOptions ( $this, $args );
    }

    /**
     * @return void
     */
    public function loadSchedule() {
        TaskAnnotationLoader::loadClasses (
            new DirectoryClassIterator ( _BASEDIR . '/lib/', 'Destiny/Tasks/' ),
            Application::instance()->getAnnotationReader (),
            $this
        );
    }

    /**
     * @return void
     */
    public function execute() {
        foreach ( $this->schedule as $i => $action ) {
            $task = $this->getTask ( $this->schedule [$i] ['action'] );
            if (empty ( $task )) {
                $this->schedule [$i] ['lastExecuted'] = date ( \DateTime::ATOM );
                $this->schedule [$i] ['executeCount'] = 0;
                $this->insertTask ( $this->schedule [$i] );
            } else {
                $this->schedule [$i] = array_merge ( $this->schedule [$i], $task );
            }
        }
        $startTime = microtime ( true );
        try {
            Log::debug ( 'Schedule starting' );
            foreach ( $this->schedule as $i => $action ) {
                $nextExecute = Date::getDateTime ( $this->schedule [$i] ['lastExecuted'] );
                $nextExecute->modify ( '+' . $this->schedule [$i] ['frequency'] . ' ' . $this->schedule [$i] ['period'] );
                if (time () > $nextExecute->getTimestamp ()) {
                    $this->schedule [$i] ['executeCount'] = intval ( $this->schedule [$i] ['executeCount'] ) + 1;
                    $this->schedule [$i] ['lastExecuted'] = date ( \DateTime::ATOM );
                    $this->updateTask ( $this->schedule [$i] );
                    $this->executeTask ( $this->schedule [$i] );
                }
            }
            Log::debug ( 'Schedule complete' );

        } catch ( Exception $e ) {
            Log::error ( $e->getMessage () );
        } catch ( \Exception $e ) {
            Log::critical ( $e->getMessage () );
        }
        Log::debug ( 'Completed in ' . (microtime ( true ) - $startTime) . ' seconds' );
    }

    /**
     * @param array $task
     */
    public function addTask(array $task){
        $this->schedule[] = array_merge($this->struct, $task);
    }

    /**
     * @param string $name
     * @return array
     */
    protected function getTask($name) {
        $conn = Application::instance ()->getConnection ();
        $stmt = $conn->prepare ( 'SELECT * FROM dfl_scheduled_tasks WHERE action = :action LIMIT 0,1' );
        $stmt->bindValue ( 'action', $name, \PDO::PARAM_STR );
        $stmt->execute ();
        return $stmt->fetch ();
    }

    /**
     * @param array $task
     */
    protected function updateTask(array $task) {
        $conn = Application::instance ()->getConnection ();
        $conn->update ( 'dfl_scheduled_tasks', array (
            'lastExecuted' => $task ['lastExecuted'],
            'executeCount' => $task ['executeCount'] 
        ), array (
            'action' => $task ['action'] 
        ), array (
            \PDO::PARAM_INT,
            \PDO::PARAM_STR,
            \PDO::PARAM_STR 
        ) );
    }

    /**
     * @param array $task
     */
    protected function insertTask(array $task) {
        $conn = Application::instance ()->getConnection ();
        $conn->insert ( 'dfl_scheduled_tasks', array (
            'action' => $task ['action'],
            'lastExecuted' => $task ['lastExecuted'],
            'frequency' => $task ['frequency'],
            'period' => $task ['period'],
            'executeCount' => $task ['executeCount']
        ), array (
            \PDO::PARAM_STR,
            \PDO::PARAM_STR,
            \PDO::PARAM_INT,
            \PDO::PARAM_STR,
            \PDO::PARAM_INT,
            \PDO::PARAM_INT 
        ) );
    }

    /**
     * @param string $name
     * @return array
     */
    public function getTaskByName($name) {
        foreach ( $this->schedule as $i => $action ) {
            if (strcasecmp ( $action ['action'], $name ) === 0) {
                return $this->schedule [$i];
            }
        }
        return null;
    }

    /**
     * @param string $name
     */
    public function executeTaskByName($name) {
        Log::debug ( sprintf ( 'Schedule task %s', $name ) );
        $task = $this->getTaskByName ( $name );
        if (! empty ( $task )) {
            $task ['executeCount'] = intval ( $task ['executeCount'] ) + 1;
            $task ['lastExecuted'] = date ( \DateTime::ATOM );
            $this->updateTask ( $task );
            $this->executeTask ( $task );
        }
    }

    /**
     * @param array $task
     * @throws Exception
     */
    protected function executeTask(array $task) {
        Log::debug ( sprintf ( 'Execute start %s', $task ['action'] ) );
        $actionClass = 'Destiny\\Tasks\\' . $task ['action'];
        if (class_exists ( $actionClass, true )) {
            $actionObj = new $actionClass ($task);
            /* @var $actionObj \Destiny\Common\TaskInterface */
            $actionObj->execute ();
        } else {
            throw new Exception ( sprintf ( 'Action not found: %s', $actionClass ) );
        }
        Log::debug ( sprintf ( 'Execute end %s', $task ['action'] ) );
    }

    public function getSchedule() {
        return $this->schedule;
    }

    public function setSchedule(array $schedule) {
        $this->schedule = $schedule;
    }

}