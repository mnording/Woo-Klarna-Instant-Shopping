<?php 
class KlarnaInstantShoppingLogger
{
    private $logdebug = false;
    private $wclogger;
    private $logContext;
    function __construct($logdebug, $logger)
    {
        $this->logContext = array('source' => 'woo-klarna-instant-shopping');
        $this->wclogger = $logger;
        $this->logdebug = $logdebug;
    }
    function logDebug($message)
    {
        if ($this->logdebug) {
            $this->wclogger->debug($message, $this->logContext);
        }
    }
    function logError($message)
    {
        $this->wclogger->error($message, $this->logContext);
    }
}?>