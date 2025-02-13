<?php
namespace Code\Base\Humble\Models;
use Humble;
use Environment;
/**    
 * System related functions
 *
 * see title
 *
 * PHP version 7.2+
 *
 * @category   Utility
 * @package    Core
 * @author     Rick Myers <rick@humbleprogramming.com>
 * @copyright  2007-Present, Rick Myers <rick@humbleprogramming.com>
 * @license    https://humbleprogramming.com/license.txt
 * @version    1.0
 * @link       https://humbleprogramming.com/docs/class-Humble_Models_System.html
 * @since      File available since Version 1.0.1
 */
class System extends Model
{
    use \Code\Base\Humble\Event\Handler;

    private $xml = false;

    /**
     * Constructor
     */
    public function __construct() {
        parent::__construct();
    }

    /**
     * Required for Helpers, Models, and Events, but not Entities
     *
     * @return system
     */
    public function getClassName() {
        return __CLASS__;
    }

    public function status() {
        return Environment::status();
    }
    
    /**
     * Returns a unique identifier to help identify request threads when you come from a client who might have the site open in multiple tabs
     * 
     * @return string
     */
    public function browserTabId() {
        if (!isset($_SESSION['BROWSER_TABS'])) {
            $_SESSION['BROWSER_TABS'] = [];
        }
        $_SESSION['BROWSER_TABS'][$tab_id = $this->_uniqueId()] = '';
        return $tab_id;
    }
    
    /**
     * Used to foil cross-site request forgeries.   A combination of the tab_id token and the csrf token will be used to make sure the request is kosher
     * 
     * @param string $tab_id
     * @return string
     */
    public function csrfBuster($tab_id) {
        if (!isset($_SESSION['BROWSER_TABS'])) {
            $_SESSION['BROWSER_TABS'] = [];
        }        
        return $_SESSION['BROWSER_TABS'][$tab_id] = $this->_uniqueId();
    }
    public function save() {
        $root       = Environment::getRoot('humble');
        $rain       = Environment::getInternalTemplater($root.'/lib/sample/install','xml');
        $rain->assign('state',      (($this->getState())         ? $this->getState() : 'DEVELOPMENT'));
        $rain->assign('enabled',    (($this->getEnabled())       ? 1 : 0));
        $rain->assign('installer',  (($this->getInstaller())     ? 1 : 0));
        $rain->assign('authorized', (($this->getAuthorization()) ? 1 : 0));
        $rain->assign('polling',    (($this->getPolling()) ? 1 : 0));
        $rain->assign('interval',   (($this->getInterval())      ? $this->getInterval()  : 15));
        $rain->assign('landing',    (($this->getLanding())       ? $this->getLanding()   : ""));
        $rain->assign('login',      (($this->getLogin())         ? $this->getLogin()     : ""));
        $rain->assign('logout',     (($this->getLogout())        ? $this->getLogout()    : ""));
        $rain->assign('quiescing',  (($this->getQuiescing())     ? $this->getQuiescing() : ""));
        $rain->assign('SSO',        (($this->getSso())           ? $this->getSso()       : ""));
        $rain->assign('version',    (($this->getVersion())       ? $this->getVersion()   : "0.0.0.0.1"));
        $rain->assign('support_name',(($this->getSupportName())  ? $this->getSupportName() : ""));
        $rain->assign('support_email',(($this->getSupportEmail())? $this->getSupportEmail() : ""));
        file_put_contents('../application.xml',$rain->render('application',true));
        Humble::response('Saved...');
    }

    /**
     *
     *
     * @return type
     */
    public function _landing() {
        $status = Environment::status();
        return $status['landing'];
    }

    /**
     * Returns whether the system is in the process of shutting down
     *
     * @return integer
     */
    public function isQuiescing() {
        if (!$this->xml) {
            $this->xml  = Environment::status(true);
        }
        return $this->xml['status']['quiescing'];
    }

    /**
     * Returns the current status of the system
     *
     * @return integer
     */
    public function isActive() {
        if (!$this->xml) {
            $this->xml  = Environment::status(true);
        }
        return $this->xml['status']['enabled'];
    }
    
    /**
     * If available, returns the current state or the default DEVELOPMENT if not set
     * 
     * @return string
     */
    public function state() {
        $xml    = Environment::status(true);
        return (isset($xml['state'])) ? $xml['state'] : 'DEVELOPMENT';
    }
    
    /**
     * Changes the state, values are DEVELOPMENT, PRODUCTION, and DEBUG
     */
    public function changeState() {
        $xml = simplexml_load_file('../application.xml');
        $xml->state = $this->getState();
        file_put_contents('../application.xml',$xml->asXML());
        Environment::recacheApplication();
    }
    
    /**
     * Sets the system quiescing bit...
     *
     *
     */
    public function quiesce() {
        $xml = simplexml_load_file('../application.xml');
        $xml->status->quiescing = $this->getValue();
        file_put_contents('../application.xml',$xml->asXML());
    }

    /**
     * Sets the system offline bit...
     *
     *
     */
    public function online() {
        $xml = simplexml_load_file('../application.xml');
        $xml->status->enabled = 1;
        file_put_contents('../application.xml',$xml->asXML());
    }

    /**
     * Sets the system offline bit...
     *
     *
     */
    public function offline() {
        $xml = simplexml_load_file('../application.xml');
        $xml->status->enabled = 0;
        file_put_contents('../application.xml',$xml->asXML());
    }

    /**
     * Returns TRUE if SSO (Single Sign On) is enabled
     *
     * @workflow use(decision)
     * @param type $EVENT
     * @return boolean
     */
    public function SSOEnabled($EVENT=false) {
        $application =  Environment::status(true);
        return ($application && (isset($application['status']['SSO'])) && ($application['status']['SSO']['enabled'] == 1));

    }

    /**
     * Triggers the re-caching to memcache of the application
     */
    public function recache() {
        Environment::recacheApplication();
    }
    
    /**
     * System Alert handler, allows for handling system wide alerts through workflows
     * 
     * 
     * @workflow use(EVENT) emit(SystemAlert)
     * @param type $type
     * @param type $data
     */
    public function systemAlert($type=false,$data) {
        if ($type) {
            $this->trigger('SystemAlert',__CLASS__,__METHOD__,['type'=>$type,'details'=>$data]);
        }
    }
    
    /**
     * System Notification handler, allows for handling system wide notifications through workflows
     * 
     * 
     * @workflow use(EVENT) emit(SystemNotification)
     * @param type $type
     * @param type $data
     */
    public function systemNotification($type=false,$data) {
        if ($type) {
            $this->trigger('SystemNotification',__CLASS__,__METHOD__,['type'=>$type,'details'=>$data]);
        }
    }    
}