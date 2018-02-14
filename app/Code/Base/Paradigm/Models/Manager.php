<?php
namespace Code\Base\Paradigm\Models;
use Humble;
/**
 * Advocate for the paradigm workflow editor
 *
 * Performs actions on behalf of the paradigm workflow editor such as save, load
 *  generate, delete, configure, etc.
 *
 * PHP version 7.2+
 *
 * LICENSE:
 *
 * @category   Component
 * @package    Workflow
 * @author     Rick Myers <rick@enicity.com>
 * @copyright  2007-Present, Rick Myers <rick@enicity.com>
 * @license    https://enicity.com/license.txt
 * @version    <INSERT VERSIONING MECHANISM HERE />
 * @link       https://enicity.com/docs/class-Paradigm_Models_Manager.html
 * @since      File available since Version 1.0.1
 */
class Manager extends Model
{

    public function __construct()    {
        parent::__construct();
    }

    /**
     *
     * @return system
     */
    public function getClassName()    {
        return __CLASS__;
    }

    /**
     * Saves a workflow with associated information.  If ID is not passed, it does an add, otherwise save.
     *
     * @return type
     */
    public function save() {
        $workflow = Humble::getEntity('paradigm/workflows');
        $major_version = ($this->getMajorVersion()) ? $this->getMajorVersion() : '0';
        $minor_version = ($this->getMinorVersion()) ? $this->getMinorVersion() : '1';
        $workflow->setMajorVersion($major_version);
        $workflow->setMinorVersion($minor_version);
        $workflow->setCreator($this->getCreator());
        $workflow->setTitle($this->getTitle());
        $workflow->setWorkflow($this->getWorkflow());
        $workflow->setDescription($this->getDescription());
        $workflow->setImage($this->getImage());
        $workflow->setNamespace($this->getNamespace());
        $workflow->setSaved(date('Y-m-d H:i:s'));
        $id = $this->getId();
        if ($id) {
            $workflow->setId($id);
            $workflow->save();
        } else {
            $id = $workflow->add();
        }
        return $id;
    }

    /**
     * Gets the configuration screen and also any pre-configured data
     *
     * What we are going to do is to get the specific instance information of this workflow component from Mongo
     * and add in information about the type of component from MySQL.  Depending on whether this component has been
     * configured we will go through one of two paths.  If not configured yet, then we show the basic component selection
     * screen where you can assign a method to the component.  If that has been done, then we go looking for any additional
     * configuration screens for this component.
     *
     * @return mixed
     */
    public function configureElement() {
        $id         = $this->getId();
        $element    = Humble::getCollection('paradigm/elements');
        $element->setId($id);
        $results    = $element->load();
        $windowId   = $this->getWindowId();
        $config     = null;
        $configURL  = false;
        //First, check to see if the element has some configuration data
        // if not, then load base configuration screen
        // else fetch detailed configuration data
        if ($results) {
            if (isset($results['configured']) && $results['configured']) {
                $this->setResults($results);
                $element = Humble::getEntity('paradigm/workflow_components');
                $element->setNamespace(isset($results['namespace']) ? $results['namespace'] : null);
                $element->setComponent(isset($results['component']) ? $results['component'] : null);
                $element->setMethod(isset($results['method']) ? $results['method'] : null);
                $data    = $element->load(true);
                $this->setData($data);
                $this->setElement($element);
                if (!$data) {
                    //see if this is a type of element that has a defacto configuration URL...
                    switch ($results['type']) {
                        case    "terminus"      :
                            $configURL  = "/workflow/default/terminus";
                            break;
                        case    "begin"         :
                            $configURL  = "/workflow/default/begin";
                            break;
                        case    "system"        :
                            $configURL  = "/workflow/elements/system";
                            break;
                        case    "webservice"    :
                            $configURL  = "/workflow/elements/webservice";
                            break;
                        case    "sensor"        :
                            $configURL  = "/workflow/elements/sensor";
                            break;
                        case    "detector"      :
                            $configURL  = "/workflow/detector/elements";
                            break;
                        case    "operation"     :
                            $configURL  = "/workflow/elements/operation";
                            break;
                        case    "external"      :
                            $configURL  = "/workflow/elements/external";
                        default                 :
                            break;
                    }
                } else {
                    $configURL = isset($data['configuration']) ? $data['configuration'] : false;
                }
                if ($configURL) {
                    $configURL = (substr($configURL,0,1)=='/') ? $configURL : '/'.$configURL;
                    //now do the manual configuration screen fetch passing all relative parameters and the settings
                    $args = array(
                        'id' => $id,
                        'windowId' => $windowId,
                        'window_id' => $windowId,
                        'sessionId' => session_id()
                    );
                    foreach ($results as $var => $val) {
                        if ($var !== '_id') { //do we still need to do this? I still think so...
                            $args[$var] = $val;
                        }
                    }
                    /*
                     * PHP has a safety feature so that only one process can be reading from a session at a time.  Since we are
                     * going to be "eating our own dogfood", we have to suspend this session so the service we are calling can
                     * have access to the shared session variable.
                     */
                    $this->setSessionId(true);  //enable session passing
                    $config = $this->_hurl($configURL,$args,"POST",true,false,false);
                }
            } else {
                /*
                 * PHP has a safety feature so that only one process can be reading from a session at a time.  Since we are
                 * going to be "eating our own dogfood", we have to suspend this session so the service we are calling can
                 * have access to the shared session variable.
                 */
                $config  = 'get'.ucfirst($results['type']).'Configuration';
                $this->setData(json_encode($results));
                $this->setSessionId(true);  //enable session passing
                $config = $this->$config();
            }
        }
        return $config;
    }

    /**
     *
     */
    public function removeElement() {
        $element = Humble::getCollection('paradigm/elements');
        $element->setId($this->getId());
        if ($data = $element->load()) {
            //######################################################################
            //
            // Depending on type of component we are deleting, some additional steps
            //  may be necessary
            //
            //######################################################################
            switch ($data['type']) {
                case    'webservice'    :
                     $service    = Humble::getEntity('paradigm/webservices');
                     $service->setWebserviceId($data['_id'])->load(true);
                     if ($service->getUri() && $service->getId()) {
                        Humble::getEntity('paradigm/webservice_workflows')->setWebserviceId($service->getId())->delete(true);
                        $service->delete();
                     }
                    break;
                default                 :
                    break;
            }
            $element->delete();
        }
    }

    /**
     *
     */
    public function updateElement() {
        $settings = json_decode($this->getData(),true);
        $element  = Humble::getCollection('paradigm/elements');
        $this->setWindowId($settings['windowId']);
        $element->setId($settings['id']);
        if ($data = $element->load()) {
            foreach ($settings as $key => $val) {
                if ($key == 'windowId') {
                    continue;
                }
                $method = 'set'.ucfirst($key);
                $element->$method($val);
            }
            $element->setConfigured(true);
            $element->save();
        }
    }

    /**
     * Will take a JSON construct representing a workflow and copy it's contents into the local MongoDB and MySQL instances
     *
     */
    public function import() {

    }

    /**
     * Will generate the JSON construct required to import a workflow
     */
    public function export() {

    }

}
?>