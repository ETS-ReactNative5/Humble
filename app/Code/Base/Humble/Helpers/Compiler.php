<?php
namespace Code\Base\Humble\Helpers;
/**
 * 
    __                    
   /   _  _ |_ _ _ || _ _ 
   \__(_)| )|_| (_)||(-|  

    __                    
   /   _  _  _ .| _ _     
   \__(_)||||_)||(-|      
            |             

   Compiles an XML file into a controller program
   
   Part of the Humble Project.
 * 
 * 
 */
class Compiler extends Directory
{
    private $xml            = null;
    private $source         = null;
    private $destination    = null;
    private $includes       = null;
    private $helper         = null;
    private $actionId       = null;
    private $elements       = [];
    private $parameters     = [];
    private $namespace      = null;
    private $component      = null;
    private $arguments      = [];
    protected $_db          = null;
    private $tabs           = 0;
    private $tabstr         = '';

    /**
     *
     */
    public function __construct()   {
        parent::__construct();
        $this->_db = \Humble::getDatabaseConnection($this);
        $this->helper = \Humble::getHelper('humble/data');
    }

    /**
     *
     * @return system
     */
    public function getClassName()   {
        return __CLASS__;
    }

    /**
     *
     */
    private function resetParameters() {
        $this->parameters["GET"]    = [];
        $this->parameters["POST"]   = [];
        $this->arguments            = [];
    }

    /**
     * Provides the structured indentation.  Keeps track of the current number of tabs being used
     * 
     * @param type $num
     * @return type
     */
    private function tabs($num=false) {
        if ($num!==false) {
            $this->tabs     = $this->tabs + $num;
            $this->tabstr   = str_pad('',$this->tabs,"\t");
        }
        return $this->tabstr;
    }

    /**
     *
     * @param type $templater
     */
    private function verifyIncludesExist($templater)   {
        $includes                       = [];
        $includeErrors                  = [];
        $includes['banner']             = 'lib/Common/banner.php';
        $includes['common_header']      = 'lib/Common/header.php';
        $includes['templater_header']   = 'lib/templaters/'.$templater.'/header.php';
        $includes['common_body']        = 'lib/Common/body.php';
        $includes['templater_footer']   = 'lib/templaters/'.$templater.'/footer.php';
        $includes['common_footer']      = 'lib/Common/footer.php';
        foreach ($includes as $type => $include) {
            if (file_exists($include)) {
                $includes[$type] = file_get_contents($include);
            } else {
                $includeErrors[$type] = '[FileNotFound] '.$include;
            }
        }
        if (count($includeErrors)>0) {
            print_r($includeErrors);
            die("\n\nAborting compilation due to missing includes\n");
        }
        $this->includes                 = $includes;
    }

    /**
     * If certain tokens were passed in, this will swap the token to the value of the token
     */
    private function processDefault($default='') {
        if (($default)) {
            switch (strtoupper($default)) {
                case '"UNIQUEID"' :
                    $default = '"'.$this->_uniqueId(true).'"';
                    break;
                case '"TIMESTAMP"' :
                    $default = '"'.date("Y-m-d H:i:s").'"';
                    break;
                case '"TIME"' :
                    $default = '"'.date("H:i:s").'"';
                    break;
                case '"DATESTAMP"':
                case '"DATE"' :
                    $default = '"'.date("Y-m-d").'"';
                    break;
                default:
                    break;
            }
        }
        return $default;
    }
    
    /**
     * 
     * @param type $required
     * @param type $source
     * @param type $field
     */
    private function processRequired($required,$source,$field) {
        $required   = ($required) ? ($required==='true' ? true : false) : false;
        if ($required) {
            print($this->tabs().'if (!isset('.$source."['".$field."'".'])) { throw new \Exceptions\ValidationRequiredException("A value has not been set for the variable <i style=\'color: red\'>'.$field.'</i>",12); }'."\n");
        }
    }
    
    /**
     * 
     * @param type $format
     * @param type $source
     * @param type $field
     * @param type $required
     * @param type $default
     */
    private function processFormat($format,$source,$field,$required=false,$default=false) {
        if ($default && !$required && ($default !== '"now"')) {
            print($this->tabs().'if (!isset('.$source.'["'.$field.'"])) {'."\n");
            print($this->tabs().$source.'["'.$field.'"] = addslashes('.$default.');'."\n");
            print($this->tabs()."}\n");
        }
        switch ($format) {
            case "datestamp":
            case "date" :
                print($this->tabs().'if (isset('.$source.'["'.$field.'"]) && ('.$source.'["'.$field.'"])) {'."\n");
                print($this->tabs(1).$source.'["'.$field.'"] = date("Y-m-d",strtotime('.$source.'["'.$field.'"]'.'));'."\n");
                print($this->tabs(-1)."}\n");
                break;
            case "timestamp" :
                print($this->tabs().'if (isset('.$source.'["'.$field.'"]) && ('.$source.'["'.$field.'"])) {'."\n");
                print($this->tabs(1).$source.'["'.$field.'"] = date("Y-m-d H:i:s",strtotime('.$source.'["'.$field.'"]'.'));'."\n");
                print($this->tabs(-1)."}\n");
                break;
            case "time" :
                print($this->tabs().'if (isset('.$source.'["'.$field.'"]) && ('.$source.'["'.$field.'"])) {'."\n");
                print($this->tabs(1).$source.'["'.$field.'"] = date("H:i:s",strtotime('.$source.'["'.$field.'"]'.'));'."\n");
                print($this->tabs(-1)."}\n");
                break;
            case "password" :
                print($this->tabs().'if (isset('.$source.'["'.$field.'"]) && ('.$source.'["'.$field.'"])) {'."\n");
                print($this->tabs(1).$source.'["'.$field.'"] = MD5('.$source.'["'.$field.'"]'.');'."\n");
                print($this->tabs(-1)."}\n");
                break;
            case "crypt" :
                //need to think this through with a salt and all that
                print($this->tabs().'if (isset('.$source.'["'.$field.'"]) && ('.$source.'["'.$field.'"])) {'."\n");
                print($this->tabs(1).$source.'["'.$field.'"] = crypt('.$source.'["'.$field.'"]'.');'."\n");
                print($this->tabs(-1)."}\n");
                break;
            case "json" :
                print('if (isset('.$source.'["'.$field.'"]) && ('.$source.'["'.$field.'"])) {'."\n");
                print($this->tabs(1).$source.'["'.$field.'"] = json_decode('.$source.'["'.$field.'"]'.',true);'."\n");
                print($this->tabs(-1)."}\n");
                break;
            default     :
                break;
        }
    }
    /**
     *
     * @param type $parameter
     * @throws \Exceptions\ControllerParameterException
     */
    private function processParameter($parameter)  {
        if (count($this->elements) == 0){
            die("There are parameter statements floating around unattached to objects");
        }
        $node   = $this->elements[count($this->elements)-1];
        $source = '$_REQUEST'; $custom = false;
        $isFile = false;
        $func   = false;
        $cast   = '';
        $source = strtoupper($parameter['source']);
        switch ($source) {
            case    "GET"       :   $source = '$_GET';
                                    break;
            case    "POST"      :   $source = '$_POST';
                                    break;
            case    "FILE"      :   $source = '$_FILES';
                                    $isFile = true;
                                    break;
            case    "REQUEST"   :   $source = '$_REQUEST';
                                    break;
            case    "SERVER"    :   $source = '$_SERVER';
                                    break;
            case    "SESSION"   :   $source = '$_SESSION';
                                    break;
            case    "CLASS"     :   $source = 'CLASS';
                                    break;
            case    "MODEL"     :
            case    "MODELS"    :
            case    "ASSIGN"    :   $source = '$models';
                                    break;                                
            case    "PUT"       :
            case    "STREAM"    :   $source = '$_REQUEST';
                                    print("\n".$this->tabs().'$_REQUEST["'.(string)$parameter['name'] .'"] = (string)file_get_contents("php://input");'."\n");
                                    break;
            case    "JSON"      :   $source = '$_JSON';
                                    break;
            case    "LOCAL"     :   $source = '$_REQUEST';
                                    print("\n".$this->tabs().'$_REQUEST["'.(string)$parameter['name'] .'"] = $'.(string)$parameter['name'].";\n");
                                    break;
            default             :   $source = '$models';
                                    $custom = true;
                                    break;
        }
        $field      = (isset($parameter['value']) ? (string)$parameter['value'] : (string)$parameter['name']);
        if (!isset($this->arguments[$source])) {
            $this->arguments[$source] = [];
        }
        $this->arguments[$source][$field] = (string)$parameter['name'];
        $format     = isset($parameter['format'])    ? strtolower((string)$parameter['format']) : false;
        $minlength  = isset($parameter['minlength']) ? (int)$parameter['minlength'] : false;
        $maxlength  = isset($parameter['maxlength']) ? (int)$parameter['maxlength'] : false;
        $timestamp  = false;
        $datestamp  = false;
        $time       = false;
        $default    = (isset($parameter['default']) ? (($parameter['default']!='') ? '"'.$parameter['default'].'"' : 'null') : 'null');
        $default    = $this->processDefault($default);
        if (($default == '"now"') && ($format)) {
            $timestamp = ($format   == 'timestamp') ? 1 : 0;
            $datestamp = (($format  == 'date') || ($format == "datestamp")) ? 1 : 0;
            $time      = ($format   == 'time') ? 1 : 0;
        }
        $required   = (isset($parameter['required']) ? strtolower((string)$parameter['required']) : false);
        $this->processRequired($required,$source,$field);
        $optional   = (isset($parameter['optional']) ? strtolower((string)$parameter['optional']) : false);
        $optional   = ($optional) ? ($optional==='true' ? true : false) : false;

        $type       = (isset($parameter['type']) ? strtolower((string)$parameter['type']) : false);
        if ($type) {
            switch ($type) {
                case "int"      :   $func = "isInteger";
                                    $typeError = 'Not an integer';
                                    break;
                case "float"    :   $func = "isFloat";
                                    $typeError = 'Not a floating point number';
                                    break;
                case "numeric"  :   $func = "ctype_digit";
                                    $typeError = 'Not numeric';
                                    break;
                case "string"   :   $func = "ctype_alnum";
                                    $typeError = 'Not alphanumeric';
                                    break;
                case "boolean"  :   $func = "isBoolean";
                                    $typeError = 'Not boolean';
                                    break;
                case "alpha"    :   $func = "ctype_alpha";
                                    $typeError = 'Not alphabetic';
                                    break;
                case "email"    :
                                    break;
                case "ssn"      :
                                    break;
                case "phone"    :
                                    break;
                default         :   $func = false;
                                    break;
            }
        }
        if ($func) {
            print($this->tabs().'if (!'.$func.'('.$cast.$source."['".$field."'".'])) { throw new \Exceptions\ValidationDatatypeException("The value in the variable <i style=\'color: red\'>'.$field.'</i> is of an incorrect format ['.$typeError.']",10); }'."\n");
        }
        if (($minlength !== false) && $minlength) {
            print($this->tabs().'if (strlen('.$source."['".$field."'".']) < '.$minlength.') { throw new \Exceptions\ValidationDatatypeException("The value in the variable <i style=\'color: red\'>'.$field.'</i> is less than the minimum length ['.$minlength.']",10); }'."\n");
        }
        if (($maxlength)) {
            print($this->tabs().'if (strlen('.$source."['".$field."'".']) > '.$maxlength.') { throw new \Exceptions\ValidationDatatypeException("The value in the variable <i style=\'color: red\'>'.$field.'</i> is greater than the maximum length ['.$maxlength.']",10); }'."\n");
        }
        if ($optional) {
            print($this->tabs().'if (isset('.$source.'["'.$field.'"])) {'."\n");
            $this->tabs(1);
        }
        if ($timestamp || $datestamp || $time) {
            $php = <<<PHP
                                if (!isset({$source}["{$field}"]) || (!{$source}["{$field}"])) {
                                    if ({$datestamp}) {
                                        {$source}["{$field}"] = date('Y-m-d');
                                    } else if ({$timestamp}) {
                                        {$source}["{$field}"] = date('Y-m-d H:i:s');
                                    } else if ({$time}) {
                                        {$source}["{$field}"] = date('H:i:s');
                                    }
                                }

PHP;
           print($php);
        }
        if ($format) {
            $this->processFormat($format,$source,$field,$required,$default);
        }
        if (($source == "GET") || ($source == "POST")) {
            $this->parameters[$source][] = $field;
        }

        if ((string)$parameter['name'] == '*') {
            print('
                                foreach ('.$source.' as $name => $value) {
                                    $method = "set".underscoreToCamelCase($name);
                                    $'.$node['id'].'->$method($value);
                                }
');
        } else if ((string)$parameter['name']=='_id') {
            //Special processing for mongo ID field
            print($this->tabs().'$'.$node['id'].'->set_id'."(new \MongoDB\BSON\ObjectID(isset(".$source.'["'.$field.'"]'.") ? ".$source.'["'.$field.'"]'." : ".$default."));\n");
        } else {
            if ($isFile) {
                 print($this->tabs().'$'.$node['id'].'->set'.ucfirst($this->underscoreToCamelCase($parameter['name']))."( isset(".$source.'["'.$field.'"]'.") ? array('name'=>".$source."['".$field."']['name'],'path'=>".$source."['".$field."']['tmp_name']) : ".$default.");\n");
            } else if ($source == "CLASS") {
                if (isset($parameter['method']) && isset($parameter['id'])) {
                    print($this->tabs().'$'.$node['id'].'->set'.ucfirst($this->underscoreToCamelCase($parameter['name']))."($".$parameter['id']."->".$parameter['method']."());\n");
                } else {
                    ob_end_clean();
                    throw new \Exceptions\ControllerParameterException("The parameter <i style=\'color: red\'>'".$parameter['name']."'</i> of object <i style=\'color: red\'>'".$node['id']."'</i> is misconfigured or missing required values.  Please refer to documentation to correct this.",16);
                }
            } else if ($custom) {
                print($this->tabs().'$'.$node['id'].'->set'.ucfirst($this->underscoreToCamelCase($parameter['name']))."( isset(".$source.'["'.$parameter['source'].'"]'.") ? ".$source.'["'.$parameter['source'].'"]'." : ".$default.");\n");
            } else {
                print($this->tabs().'$'.$node['id'].'->set'.ucfirst($this->underscoreToCamelCase($parameter['name']))."( isset(".$source.'["'.$field.'"]'.") ? ".$source.'["'.$field.'"]'." : ".$default.");\n");
            }
        }
        if (isset($parameter["store"]) && (strtoupper($parameter['store'])=='TRUE')) {
            if ($custom) {
                print($this->tabs().'$_SESSION["'.$parameter['name'].'"] = isset('.$source.'["'.$parameter['value'].'"]) ? '.$source.'["'.$parameter['value'].'"] : '.$default.';'."\n");
            } else {
                print($this->tabs().'$_SESSION["'.$parameter['name'].'"] = isset('.$source.'["'.$field.'"]) ? '.$source.'["'.$field.'"] : '.$default.';'."\n");
            }
        }
        if ($optional) {
            print($this->tabs(-1)."}\n");
        }
    }

    /**
     * 
     * @param string $node
     */
    private function processModel($node) {
        if (!isset($node['id'])) {
            $node['id'] = 'E_'.$this->_uniqueId();
        }
        if (isset($node['blocking'])) {
            $this->blockingStatement($node['blocking']);
        }
        $namespace = (strtolower($node['namespace'])==='inherit') ? "\".Humble::_namespace().\"" : $node['namespace'];
        print($this->tabs().'$'.$node['id'].' = $models["'.$node['id'].'"] = \Humble::getModel("'.$namespace.'/'.$node['class'].'");'."\n");
        array_push($this->elements,$node);
        foreach ($node as $tag => $newNode) {
            $this->processNode($tag,$newNode);
        }
        $x = array_pop($this->elements);
        if (isset($node['method'])) {
            if (isset($node['response']) && (strtolower($node['response'])=='true')) {
                if (isset($node['wrapper'])) {
                    print($this->tabs().'Humble::response('.$node['wrapper'].'($'.$node['id'].'->'.$node['method'].'()));'."\n");
                } else {
                    print($this->tabs().'Humble::response($'.$node['id'].'->'.$node['method'].'());'."\n");
                }
            } else {
                if (isset($node['wrapper'])) {
                    print($this->tabs().$node['wrapper'].'($'.$node['id'].'->'.$node['method'].'());'."\n");
                } else {
                    print($this->tabs().'$'.$node['id'].'->'.$node['method'].'();'."\n");
                }
            }
        }
    }

    /**
     * 
     * @param string $node
     */
    private function processMongo($node) {
        $collection = (isset($node['class']) ? $node['class'] : (isset($node['collection']) ? $node['collection'] : "" ));
       /* if ($collection) {
            $collection = "/".$collection;
        }*/
        if (isset($node['blocking'])) {
            $this->blockingStatement($node['blocking']);
        }
        $namespace = (strtolower($node['namespace'])==='inherit') ? "\".Humble::_namespace().\"" : $node['namespace'];
        print($this->tabs().'$'.$node['id'].' = $models["'.$node['id'].'"] = \Humble::getCollection("'.$namespace.'/'.$collection.'");'."\n");
        //maybe select the collection here, either use 'class=""' or 'collection=""'
        array_push($this->elements,$node);
        foreach ($node as $tag => $newNode) {
            $this->processNode($tag,$newNode);
        }
        $x = array_pop($this->elements);
        if (isset($node['method'])) {
            if (isset($node['response']) && (strtolower($node['response'])=='true')) {
                if (isset($node['wrapper'])) {
                    print($this->tabs().'Humble::response('.$node['wrapper'].'($'.$node['id'].'->'.$node['method'].'()));'."\n");
                } else {
                    print($this->tabs().'Humble::response($'.$node['id'].'->'.$node['method'].'());'."\n");
                }
            } else {
                if (isset($node['wrapper'])) {
                    print($this->tabs().$node['wrapper'].'($'.$node['id'].'->'.$node['method'].'());'."\n");
                } else {
                    print($this->tabs().'$'.$node['id'].'->'.$node['method'].'();'."\n");
                }
            }
        }
    }

    /**
     * 
     * @param string $node
     */
    private function processMongoServer($node) {

        print($this->tabs().'$'.$node['id'].' = $models["'.$node['id'].'"] = \Humble::getCollectionServer("'.$node['namespace'].'");'."\n");
        array_push($this->elements,$node);
        foreach ($node as $tag => $newNode) {
            $this->processNode($tag,$newNode);
        }
        $x = array_pop($this->elements);
        if (isset($node['method'])) {
            if (isset($node['response']) && (strtolower($node['response'])=='true')) {
                if (isset($node['wrapper'])) {
                    print($this->tabs().'Humble::response('.$node['wrapper'].'($'.$node['id'].'->'.$node['method'].'()));'."\n");
                } else {
                    print($this->tabs().'Humble::response($'.$node['id'].'->'.$node['method'].'());'."\n");
                }
            } else {
                if (isset($node['wrapper'])) {
                    print($this->tabs().$node['wrapper'].'($'.$node['id'].'->'.$node['method'].'());'."\n");
                } else {
                    print($this->tabs().'$'.$node['id'].'->'.$node['method'].'();'."\n");
                }
            }
        }
    }
    
    /**
     * We are going to translate JSON input into the super global variables
     * 
     * @param type $node
     */
    private function handleJSONRequest($node=[]) {
        $target = isset($node['method']) && (strtoupper($node['method'])=='GET') ? '$_GET' : '$_POST'; 
        print($this->tabs().'$data = json_decode((string)file_get_contents("php://input"));'."\n");
        print($this->tabs().'foreach ($data as $field => $value) {'."\n");
        print($this->tabs(1).$target.'[$field]= $_REQUEST[$field] = $value;'."\n");
        print($this->tabs(-1).'}'."\n");
    }
    
    /**
     * 
     * @param string $node
     */
    private function processEntity($node) {
        $namespace = (strtolower($node['namespace'])==='inherit') ? "\".Humble::_namespace().\"" : $node['namespace'];
        if (!isset($node['id'])) {
            $node['id'] = 'E_'.$this->_uniqueId();
        }
        if (isset($node['blocking'])) {
            $this->blockingStatement($node['blocking']);
        }
        print($this->tabs().'$'.$node['id'].' = $models["'.$node['id'].'"] = \Humble::getEntity("'.$namespace.'/'.$node['class'].'");'."\n");
        if (isset($node['page'])) {
            print($this->tabs().'$'.$node['id'].'->_page(null);'."\n");  //essentially, you first make sure that pagination is turned off by passing a null
            print($this->tabs().'if (isset($_REQUEST["'.$node['page'].'"])) {'."\n");  //then you look to see if it is turned on
            print($this->tabs(1).'$'.$node['id'].'->_page($_REQUEST["'.$node['page'].'"]);'."\n");  //and then you set the corresponding variable from the request
            print($this->tabs(-1)."}\n");
            if (isset($node['defaultPage'])) {
                print($this->tabs().'if (!isset($_REQUEST["'.$node['page'].'"])) {'."\n");
                print($this->tabs(1).'$'.$node['id'].'->_page('.$node['defaultPage'].')'.";\n");
                print($this->tabs(-1)."}\n");
            }
        }
        if (isset($node['polyglot'])) {
            print($this->tabs().'$'.$node['id'].'->_polyglot('.$node['polyglot'].');'."\n");
        }
        if (isset($node['translation']) && strtoupper($node['translation'])=='TRUE') {
            print($this->tabs().'$'.$node['id'].'->withTranslation(true);'."\n");
        }
        if (isset($node['rows'])) {
            print($this->tabs().'if (!$'.$node['id'].'->_rows()) {'."\n");
            print($this->tabs(1).'$'.$node['id'].'->_rows(null);'."\n");  //essentially, you first make sure that pagination is turned off by passing a null
            print($this->tabs(-1)."}\n");
            if (isset($node['defaultRows'])) {
                print($this->tabs().'$'.$node['id'].'->_rows('.$node['defaultRows'].');'."\n");
            }
            print($this->tabs().'if (isset($_REQUEST["'.$node['rows'].'"])) {'."\n");  //then you look to see if it is turned on
            print($this->tabs(1).'if (intval($_REQUEST["'.$node['rows'].'"])) {'."\n");
            print($this->tabs(1).'$'.$node['id'].'->_rows($_REQUEST["'.$node['rows'].'"]);'."\n");  //and then you set the corresponding variable from the request
            print($this->tabs(-1)."}\n");
            print($this->tabs(-1)."}\n");
        }
        if (isset($node['orderby'])) {
            print($this->tabs().'$'.$node['id'].'->_orderBy(\''.$node['orderby'].'\');'."\n");
        }
        if (isset($node['distinct']) && (strtolower($node['distinct'])==='true')) {
            print($this->tabs().'$'.$node['id'].'->_distinct(true);'."\n");
        }
        if (isset($node['fields'])) {
            print($this->tabs().'$'.$node['id']."->_fieldList('".$node['fields']."');"."\n");
        }
        array_push($this->elements,$node);
        foreach ($node as $tag => $newNode) {
            $this->processNode($tag,$newNode);
        }
        $x = array_pop($this->elements);
        if (isset($node['retain']) && strtolower($node['retain'])=='true') {
            print($this->tabs().'$'.$node['id'].'->_retain();'."\n");
        }
        if (isset($node['method'])) {
            $arglist = '';
            if (isset($node['argument']) || isset($node['arguments'])) {
                $list = (isset($node['arguments'])) ? $node['arguments'] : $node['argument'];
                foreach ($explode(',',$list) as $arg) {
                    if (is_numeric($arg) || (strtolower($arg)=='true') || (strtolower($arg)=='false')) {
                        $arglist = (($arglist) ? ',':'').$arg;
                    } else if (false) {
                        //@TODO: argument is in the models array
                    } else {
                        //pull from the $_REQUEST
                        $arglist = (($arglist) ? ',':'').'$_REQUEST['."'".$arg."']";
                    }
                }
            }
            $assign_str = ''; $method_str = '$'.$node['id'].'->'.$node['method'].'('.$arglist.')';
            if (isset($node['assign'])) {
                $assign_str = '$'."models['".$node['assign']."'] = ".'$'.$node['assign'].' = ';
            }
            if (isset($node['normalize']) && (strtoupper($node['normalize'])=='Y')) {
                $method_str = '$'.$node['id'].'->normalize('.$method_str.')';
            }
            if (isset($node['wrapper'])) {
                $method_str = $node['wrapper'].'('.$method_str.')';
            }
            if (isset($node['response']) && (strtolower($node['response'])=='true')) {
                $method_str = 'Humble::response('.$method_str.')';
            }
            print($this->tabs().$assign_str.$method_str.";\n");
        }
    }

    /**
     * 
     * @param string $node
     */
    private function processOutput($node) {
        if (isset($node['text'])) {
            print($this->tabs()."Humble::response(\"".addslashes($node['text'])."\");\n");
        }
    }
    
    /**
     * 
     * @param string $node
     */
    private function processHelper($node) {
        if (!isset($node['id'])) {
            $node['id'] = 'E_'.$this->_uniqueId();
        }
        $namespace = (strtolower($node['namespace'])==='inherit') ? "\".Humble::_namespace().\"" : $node['namespace'];
        print($this->tabs().'$'.$node['id'].' = $models["'.$node['id'].'"] = \Humble::getHelper("'.$namespace.'/'.$node['class'].'");'."\n");
        array_push($this->elements,$node);
        foreach ($node as $tag => $newNode) {
            $this->processNode($tag,$newNode);
        }
        $x = array_pop($this->elements);
        if (isset($node['method'])) {
            if (isset($node['response']) && (strtolower($node['response'])=='true')) {
                if (isset($node['wrapper'])) {
                    print($this->tabs().'Humble::response('.$node['wrapper'].'($'.$node['id'].'->'.$node['method'].'()));'."\n");
                } else {
                    print($this->tabs().'Humble::response($'.$node['id'].'->'.$node['method'].'());'."\n");
                }
            } else {
                if (isset($node['wrapper'])) {
                    print($this->tabs().$node['wrapper'].'($'.$node['id'].'->'.$node['method'].'());'."\n");
                } else {
                    print($this->tabs().'$'.$node['id'].'->'.$node['method'].'();'."\n");
                }
            }
        }
    }

    /**
     * 
     * @param string $node
     */
    private function processView($node) {
        print($this->tabs().'$view'." = '".$node['name']."';\n");
    }

    /**
     * 
     * @param string $node
     */
    private function processViews($node) {
        print($this->tabs().'$views['.(isset($node['order']) ? $node['order'] : '').']'." = '".$node['name']."';\n");
    }

    /**
     *
     * @param type $node
     */
    private function processChain($node) {
        //nop
        foreach($node as $nIdx => $action) {
            $this->processNode($nIdx,$action);
        }

    }

    /**
     * 
     * @param type $node
     */
    private function processHeader($node) {
        $name   = (isset($node['name']))    ? $node['name']  : false;
        $value  = (isset($node['value']))   ? $node['value'] : false;
        if ($name && $value) {
            print($this->tabs().'header("'.$name.': '.$value.'");'."\n");
        }
    }
    
    /**
     *
     * @param type $action
     */
    private function processChainedAction($action) {
        print($this->tabs().'$chainActions[]'." = '".$action['name']."';\n");
        if (isset($action['controller'])){
            print($this->tabs().'$chainControllers[]'." = '".$action['controller']."';\n");
        } else {
            print($this->tabs().'$chainControllers[]'." = '".$this->controller."';\n");
        }
    }

    /**
     *
     * @param type $node
     */
    private function processRedirect($node) {
        $redirect = "";
        print($this->tabs().'$_SESSION["HUMBLE_REDIRECT_HEADERS"] = headers_list();'."\n");
        if (isset($node['post']) && $node['post']==true) {
            $txt = $this->tabs().'$vars = [];
                        foreach ($_POST as $key => $val) {
                            $vars[$key] = $val;
                        }';
            print($txt."\n");
            if (isset($node['add']) && $node['add']) {
                $txt = '';
                foreach (explode(",",$node['add']) as $key => $val) {
                    $txt .= $this->tabs().'$vars["'.$val.'"] = $'.$val.";\n";
                }
               print($txt."\n");
            }
            print($this->tabs().'$jsonData = json_encode($vars);'."\n");
            $id = $this->_uniqueId();
            print($this->tabs().'$_SESSION["'.$id.'"] = $jsonData;'."\n");
            $delim = (strpos($node['href'],'?')===false) ? "?" : "&";
            $redirect = $delim.'redirect=true&POST='.$id;
        }
        if (isset($node['var'])) {
            $place = '$_REQUEST'."['".$node['var']."']";
            print($this->tabs().'if (!isset($_REQUEST["'.$node['var'].'"])) { throw new \Exceptions\RedirectVariableException("The variable that should contain the redirect URL is not set: <i style=\'color: red\'>'.$node['var'].'</i>",16); }'."\n");
            print($this->tabs().'header("Location: {'.$place.'}'.$redirect.'");'."\n");
        } else {
            print($this->tabs().'header("Location: '.$node['href'].$redirect.'");'."\n");
            
        }
       
        
        /*
        $redirect = "";
        print($this->tabs().'$_SESSION["HUMBLE_REDIRECT_HEADERS"] = headers_list();'."\n");
        if (isset($node['post']) && $node['post']==true) {
            $txt = '    $vars = [];
                        foreach ($_POST as $key => $val) {
                            $vars[$key] = $val;
                        }';
            print($txt."\n");
            print($this->tabs().'$jsonData = json_encode($vars);'."\n");
            $id = $this->_uniqueId();
            print($this->tabs().'$_SESSION["'.$id.'"] = $jsonData;'."\n");
            $delim = (strpos($node['href'],'?')===false) ? "?" : "&";
            $redirect = $delim.'redirect=true&POST='.$id;
        }
        print($this->tabs().'header("Location: '.$node['href'].$redirect.'");'."\n");
         * 
         */
    }

    /**
     * Punches out the code that will throw a user defined exception
     * @param type $node
     */
    private function processException($node) {
        if (isset($node['message']) && isset($node['code'])) {
            print($this->tabs().'throw new \Exceptions\HumbleException("'.$node['message'].'",'.$node['code'].');'."\n");
        }
    }

    /**
     * Punches out the code that creates a switch statement...
     *
     * @param type $node
     */
    private function processSwitch($node) {
        if (isset($node['id'])) {
            print($this->tabs()."switch ($".$node['id'].'->'.$node['method'].'()'.")\n");
        } else if (isset ($node['var'])) {
            print($this->tabs().'switch ($_REQUEST["'.$node['var'].'"])'."\n");
        }
        print($this->tabs()."{\n");
        foreach ($node as $nIdx => $case) {
            $this->tabs(1);
            $nIdx = strtolower($nIdx);
            $val = '"'.$case["value"].'"';
            if ((strtoupper($case["value"]) == 'TRUE') || strtoupper($case["value"]) == 'FALSE') {
                $val = $case["value"];
            }
            if ($nIdx == "default") {
                print($this->tabs()."default:\n");
            } else if ($nIdx == "case") {
                print($this->tabs()."case\t".$val."\t:\n");
            } else {
                continue;
            }
            foreach ($case as $tag => $newNode) {
                $this->tabs(1);
                $this->processNode($tag,$newNode);
                $this->tabs(-1);
            }
            print ($this->tabs()."break;\n");
            $this->tabs(-1);
        }
        print($this->tabs()."}\n");
    }

    /**
     * Punches out the code for if/then/else statements
     *
     * @param type $nodes
     */
    private function processIf($node) {
        $op = '';$val = '';
        if (isset($node['eq'])) {
            $op = ' == '; $val = $node['eq'];
        } else if (isset($node['lt'])) {
            $op = ' < '; $val = $node['lt'];
        } else if (isset($node['gt'])) {
            $op = ' > '; $val = $node['gt'];
        } else if (isset($node['lte'])) {
            $op = ' <= '; $val = $node['lte'];
        } else if (isset($node['gte'])) {
            $op = ' >= '; $val = $node['gte'];
        } else if (isset($node['eqs'])) {
            $op = ' === '; $val = $node['eqs'];
        }  else if (isset($node['ne'])) {
            $op = ' != '; $val = $node['ne'];
        }
      //  if (!((strtoupper($val) === 'TRUE') || (strtoupper($val) === 'FALSE'))) {
            $val = '"'.$val.'"';
     //   }
        $args   = '';
        if (isset($node['arguments'])) {
            $args = "'".$node['arguments']."'";
        }
        if (isset($node['id'])) {
            print($this->tabs()."if ($".$node['id'].'->'.$node['method'].'('.$args.') '.$op." ".$val.") ");
        } else if (isset ($node['var'])) {
            print($this->tabs().'if (isset($_REQUEST["'.$node['var'].'"]) && ($_REQUEST["'.$node['var'].'"] '.$op." ".$val.")) ");
        }
        foreach ($node as $nIdx => $case) {
            $nIdx = strtolower($nIdx);
            if ($nIdx == "else") {
                print(" else {\n");
                foreach ($case as $tag => $newNode) {
                    $this->tabs(1);
                    $this->processNode($tag,$newNode);
                    $this->tabs(-1);
                }
                print($this->tabs()."} \n");
            } else if ($nIdx == "then") {
                print("{\n");
                foreach ($case as $tag => $newNode) {
                    $this->tabs(1);
                    $this->processNode($tag,$newNode);
                    $this->tabs(-1);
                }
                print($this->tabs()."}");
            }
        }
        print("\n");
    }

    /**
     * Punches out the code that signals to abort processing...
     *
     * @param type $node
     */
    private function processAbort($node) {
        print($this->tabs().'$abort = '.$node['value'].';'."\n");
    }

    /**
     *
     * @param type $node
     */
    private function processAssign($node) {
        if (isset($node['value'])) {
            print($this->tabs().'$'.$node['var'].' = $models["'.$node['var'].'"] = '.$node['value'].';'."\n");
        } else {
            if (isset($node['id']) && isset($node['method'])) {
                print($this->tabs().'$'.$node['var'].' = $models["'.$node['var'].'"] = $'.$node['id'].'->'.$node['method'].'();'."\n");
            }
        }
    }

    /**
     * Since PHP establishes a lock on a session file, only one process at a time can write to it.  If there are multiple AJAXS requests hitting the server, they will
     * end up blocking one another.  If your action or component doesn't need to write to the session, it is a good idea to release, or "unblock" the session
     * by setting 'blocking="off"' on your element
     *
     * @param string $statement
     */
    private function blockingStatement($statement) {
        switch (strtolower($statement)) {
            case "n"        :
            case "false"    :
            case "no"       :
            case "off"      :
                print($this->tabs()."Environment::unblock();\n");
                break;
            case "y"        :
            case "true"     :
            case "on"       :
            case "yes"      :
                print($this->tabs()."Environment::block();\n");
                break;
            default : break;
        }
    }

    /**
     * If the <action /> statement has an 'audit=""' attribute, we will add a step to record this action 
     * 
     * @param string $audit
     */
    private function handleAudit($audit=false) {
        $track = ['TRUE'=>true,'YES'=>true,'Y'=>true,'ON'=>true];               //will do an audit if any of these values are present
        if ($audit) {
            if (isset($track[strtoupper($audit)])) {
                print($this->tabs().'$skip = ["m"=>true,"n"=>true,"c"=>true];'."\n");
                print($this->tabs().'$audit = \Humble::getEntity("humble/audit/log");'."\n");
                print($this->tabs().'$audit->setNamespace(\Humble::_namespace());'."\n");
                print($this->tabs().'$audit->setController(\Humble::_controller());'."\n");
                print($this->tabs().'$audit->setAction(\Humble::_action());'."\n");
                print($this->tabs().'$audit->setUid(\Environment::whoAmI())'.";\n");
                print($this->tabs().'$audit->setIdentity(\Environment::whoAmIReally())'.";\n");
                print($this->tabs().'$audit->setTimestamp(date("Y-m-d H:i:s"))'.";\n");
                print($this->tabs().'foreach ($_REQUEST as $var => $val) {'."\n");
                print($this->tabs(1).'if (!isset($skip[$var])) {'."\n");
                print($this->tabs(1).'$method = "set".underscoreToCamelCase($var,true)'.";\n");
                print($this->tabs().'$audit->$method($val);'."\n");
                print($this->tabs(-1)."}\n");
                print($this->tabs(-1)."}\n");
                print($this->tabs().'$audit->save();'."\n");
            }
        }
    }
    
    /**
     * For each XML DOM node encountered, routes to the proper handler
     *
     * @param array $node
     */
    private function processNode($tag,$node) {
        switch ($tag) {
            case    "parameter"     :   $this->processParameter($node);
                                        break;
            case    "model"         :   $this->processModel($node);
                                        break;
            case    "mongo"         :   $this->processMongo($node);
                                        break;
            case    "mongoserver"   :   $this->processMongoServer($node);
                                        break;
            case    "entity"        :   $this->processEntity($node);
                                        break;
            case    "output"        :   $this->processOutput($node);
                                        break;
            case    "helper"        :   $this->processHelper($node);
                                        break;
            case    "view"          :   $this->processView($node);
                                        break;
            case    "views"         :   $this->processViews($node);
                                        break;
            case    "chain"         :   $this->processChain($node);
                                        break;
            case    "redirect"      :   $this->processRedirect($node);
                                        break;
            case    "action"        :   $this->processChainedAction($node);
                                        break;
            case    "switch"        :   $this->processSwitch($node);
                                        break;
            case    "if"            :   $this->processIf($node);
                                        break;
            case    "exception"     :   $this->processException($node);
                                        break;
            case    "abort"         :   $this->processAbort($node);
                                        break;
            case    "assign"        :   $this->processAssign($node);
                                        break;
            case    "header"        :   $this->processHeader($nodw);
                                        break;
            default                 :   break;

        }
    }

    /**
     * 
     * @param string $xml
     */
    private function generateController($xml)    {
        $this->controller = $xml['name'];
        $info             = $this->getInfo();
        $templater        = (isset($xml['use']) ? $xml['use'] : $info['templater']);
        $this->verifyIncludesExist($templater);
        print($this->includes['banner']);
        print("<?php\n");
        print($this->tabs().'$templater  =  '."'".$templater."';"."\n");
        print($this->tabs().'$controller =  '."'".$this->controller."';"."\n");
        print("?>\n");
        print($this->includes['common_header']);
        print($this->includes['templater_header']);
        $controller = $xml[0];
        $defaultAction    = false;
        foreach ($controller as $tag3 => $actions) {
            print("<?php\n");
            print($this->tabs(1).'$models["firePHP"] = \Log::getConsole();'."\n");
            print($this->tabs().'function processMethod($method) {'."\n");
            print($this->tabs(1).'global $models;'."\n");
            print($this->tabs().'global $view;'."\n");
            print($this->tabs().'global $views;'."\n");
            print($this->tabs().'global $chainActions;'."\n");
            print($this->tabs().'global $chainControllers;'."\n");
            print($this->tabs().'global $abort;'."\n");
            print($this->tabs().'switch ($method) {'."\n");
            foreach ($actions as $tag2 => $action ) {
                if ($action['name'] == 'default') {
                    $defaultAction = $action;
                    continue;
                }
                print($this->tabs(1).'case "'.$action['name'].'":'."\n");
                $this->resetParameters();
                $this->actionId = $this->helper->_uniqueId();
                if (isset($action['request']) && (strtoupper($action['request']) == 'JSON')) {
                    $this->handleJSONRequest($action);
                }                
                print($this->tabs().'$P_'.$this->actionId.' = [];'."\n");
                if (isset($action['output'])) {
                    switch (strtolower($action['output'])) {
                        case 'html'     :   print($this->tabs()."header('content-type: text/html');\n");
                                            break;
                        case 'csv'      :   print($this->tabs()."header('content-type: text/csv');\n");
                                            break;
                        case 'xml'      :   print($this->tabs()."header('content-type: text/xml');\n");
                                            break;
                        case 'json'     :   print($this->tabs()."header('content-type: application/json');\n");
                                            break;
                        case 'javascript':
                        case 'js'       :   print($this->tabs()."header('content-type: application/javascript');\n");
                                            break;
                        case 'pdf'       :  print($this->tabs()."header('content-type: application/pdf');\n");
                                            break;
                        case 'yaml'     :   print($this->tabs()."header('content-type: text/yaml');\n");
                                            break;
                        case 'text'     :   print($this->tabs()."header('content-type: text/plain');\n");
                                            break;                                        
                        default         :   print($this->tabs()."header('content-type: application/octet-stream');\n");
                                            break;
                    }
                }
                if (isset($action['disposition']) || isset($action['filename'])) {
                    $filename    = isset($action['filename']) ? '; filename="'.$action['filename'].'"' : "";
                    $disposition = isset($action['disposition']) ? $action['disposition'] : 'attachment'; 
                    print($this->tabs()."header('Content-Disposition: ".$disposition.$filename."');\n");
                }                
                //Handles if we are receiving raw json data...
                if (isset($action['input'])) {
                    switch (strtolower($action['input'])) {
                        case "json" :
                            print($this->tabs().'$_JSON = json_decode((string)file_get_contents("php://input"),true);'."\n");
                            break;
                        case "xml"  : 
                            //@TODO: do something here7
                            break;
                    }
                }
                if (isset($action['blocking'])) {
                    $this->blockingStatement($action['blocking']);
                }
                //Handles options for the variables you want to "pass-along" to the view
                if (isset($action['passalong'])) {
                    $fields = explode(",",$action['passalong']);
                    
                    foreach ($fields as $field) {
                        $format = ''; $required = false; $default = ''; $value = $field; //Remember, "value" is the name of the field in the initial request object
                        if (strpos($field,':')!==false) {
                            $f      = explode(':',$field);
                            $field  = $f[0]; 
                            $value  = $f[0];
                            $ll     = count($f);
                            for ($ii=1; $ii < $ll; $ii++) {
                                $param  = $f[$ii];
                                $optval = true;
                                if (strpos($param,'=')) {
                                    $opt    = explode('=',$param);
                                    $param  = $opt[0];
                                    $optval = $opt[1];
                                }
                                switch (strtolower($param)) {
                                    case "format"   :
                                        $format = $optval;
                                        break;
                                    case "value"    :
                                        $value = $optval;
                                        break;
                                    case "default" :
                                        $default = $optval;
                                        break;
                                    case "required" :
                                        $required = $optval;
                                        break;
                                    default         : break;
                                }
                            }
                        }
                        if ($required) {
                            $this->processRequired($required,'$_REQUEST',$field);
                        }
                        if ($format) {
                            $this->processFormat(strtolower($format),'$_REQUEST',$field,$required,$default);                            
                        }
                        if ($default) {
                            $default = ((strtolower($default)==='true') || (strtolower($default)==='false')) ? $default : '"'.$default.'"';
                        }
                        print($this->tabs().'$models[\''.$value.'\'] = isset($_REQUEST[\''.$field.'\']) ? $_REQUEST[\''.$field.'\'] : '.($default ? $default : 'null').';'."\n");
                    }
                }
                if (isset($action['audit'])) {
                    $this->handleAudit($action['audit']);
                }                
                if (isset($action['required'])) {
                    $fields = explode(",",$action['required']);                 //I forgot I put this in there... must have been drinking and coding...
                    foreach ($fields as $field) {
                        print($this->tabs().'if (!isset($_REQUEST["'.$field.'"])) { throw new \Exceptions\ValidationRequiredException("A value has not been set for the variable <i style=\'color: red\'>'.$field.'</i>",12); }'."\n");
                    }
                }
                foreach ($action as $tag => $node) {
                    $this->processNode($tag,$node);
                }
                /**
                 * IF A class was specified on the action, instantiate the class, and call the "execute" method after passing in all the models
                 */
                if (isset($action['class']) && isset($action['namespace'])) {
                    $class  = $action['class'];
                    $ns     = $action['namespace'];
                    $id     = (isset($action['id'])) ? $action['id'] : $this->helper->_uniqueId();
                    print($this->tabs().'$v_'.$id." = \Humble::getModel('".$ns."/".$class."');\n");
                    //might need to add the action model to the list of models for the view... what are the pros and cons?  DEBATE!
                    print($this->tabs().'foreach ($models as $mdl => $val) {'."\n");
                    $this->tabs(1);
                    print($this->tabs().'$mthd = "set".underscoreToCamelCase($mdl);'."\n");
                    print($this->tabs().'$v_'.$id.'->$mthd($val);'."\n");
                    $this->tabs(-1);
                    print($this->tabs()."}\n");
                    print($this->tabs().'$v_'.$id.'->execute();'."\n");
                }
                /**
                 * IF the 'event' flag was set on the action, then create a new trigger event and pass in all of the data this action received
                 */
                if (isset($action['event'])) {
                    $trigger = \Humble::getEntity('paradigm/workflow_components');
                    $trigger->setNamespace($this->namespace);
                    $trigger->setComponent(ucfirst($this->component));
                    $trigger->setMethod($action['name']);
                    $trigger->setEvent('Y');
                    $trigger->save();
                    $e = \Humble::getEntity('paradigm/events');
                    $e->setEvent($action['event']);
                    $e->setComment($action['comment']);
                    $e->setNamespace($this->namespace);
                    $e->save();
                    if (isset($action['comment'])) {
                        $comment = \Humble::getEntity('paradigm/workflow_comments');
                        $comment->setNamespace($this->namespace);
                        $comment->setClass($this->component);
                        $comment->setMethod($action['name']);
                        $comment->setComment($action['comment']);
                        $comment->save();
                    }
                    $id      = $this->helper->_uniqueId();
                    print($this->tabs().'$TRIGGER_'.$id.' = Event::getTrigger();'."\n");
                    print($this->tabs().'$TRIGGER_'.$id.'->_namespace("'.$this->namespace.'");'."\n");
                    print($this->tabs().'$TRIGGER_'.$id.'->_controller("'.$this->component.'");'."\n");
                    print($this->tabs().'$TRIGGER_'.$id.'->_method("'.$action['name'].'");'."\n");
                    if (isset($action['passalong'])) {
                        $fields = explode(",",$action['passalong']);
                        foreach ($fields as $field) {
                            $field_opts = [];
                            $value      = $field;
                            if (strpos($field,':')) {
                                $field_opts = explode(':',$field);
                                $value = $field = $field_opts[0];
                                for ($i=1; $i<count($field_opts); $i++) {
                                    $o = explode('=',$field_opts[$i]);
                                    if (strtolower($o[0])==='value') {
                                        $value = (isset($o[1])) ? $o[1] : $field;
                                    }
                                }
                            }
                            print($this->tabs().'if (isset($_REQUEST["'.$field.'"])) {'."\n");
                            print($this->tabs(1).'$TRIGGER_'.$id.'->_arguments("'.$value.'",$_REQUEST["'.$field.'"]);'."\n");
                            print($this->tabs(-1).'}'."\n");
                        }
                    }
                    foreach ($this->arguments as $source => $arguments) {
                        if (($source !== '$models') && ($source !== "CLASS")) {
                            foreach ($arguments as $field => $arg) {
                                print($this->tabs().'if (isset('.$source.'["'.$field.'"])) {'."\n");
                                print($this->tabs(1).'$TRIGGER_'.$id.'->_arguments("'.$arg.'",'.$source.'["'.$field.'"]);'."\n");
                                print($this->tabs(-1).'}'."\n");
                            }
                        }
                    }
                    print($this->tabs().'$TRIGGER_'.$id.'->emit("'.$action['event'].'");'."\n");
                }
                print($this->tabs(-1)."break;\n");
            }
            if ($defaultAction !== false) {
                //handle default action here
                print($this->tabs().'default :  $models["action"]=Humble::_action();'."\n");
                foreach ($defaultAction as $tag => $node) {
                    $this->tabs(1);
                    $this->processNode($tag,$node);
                    $this->tabs(-1);
                }
                print($this->tabs()."break;\n");
            } else {
                print($this->tabs()."default :\n");
                print($this->tabs(1).'die("<pre>No action defined for method: {$method}.</pre>");'."\n");
                print($this->tabs(-1).'break; '."\n");
            }
            print($this->tabs(-1)."}\n");
            print($this->tabs(-1)."}\n".'processMethod($method);'."\n?>\n");
            print($this->includes['common_body']);
            print($this->includes['templater_footer']);
            print($this->includes['common_footer']);
        }
    }

    /**
     * 
     * @param type $identifier
     * @param type $stamp
     */
    private function stampIt($identifier,$stamp)   {
        $module = explode('/',$identifier);
        $query = <<<SQL
            insert into humble_controllers
                (namespace,controller,compiled)
            values
                ('{$module[0]}','{$module[1]}','{$stamp}')
              on duplicate key
                update compiled = '{$stamp}'
SQL;
       $this->_db->query($query);
    }

    /**
     * 
     * @param string $identifier
     * @param type $force
     * @throws \Exceptions\MalformedXMLException
     * @throws \Exceptions\MissingControllerXMLException
     */
    public function compile($identifier=false,$force=true)    {
        if ($identifier===false) {
            $source          = $this->getFile();
            $controller      = explode("/",$source);
            $controller      = $controller[count($controller)-1];
            $this->component = $controller = substr($controller,0,strpos($controller,'.xml'));
        } else {
            $data            = explode('/',$identifier);
            $this->namespace = $namespace      = $data[0];
            $this->component = $controller     = $data[1];
            $source          = $this->getSource();
            if (!$source) {
                $mod    = \Humble::getModule($namespace);
                $source = 'Code/'.$mod['package'].'/'.str_replace(['_'],['/'],$mod['controller']);
            }
            $source          = $source.'/'.$controller.'.xml';
        }
        if (!$this->getDestination()) {
                $mod    = \Humble::getModule($namespace);
                $this->setDestination($mod['package'].'/'.str_replace(['_'],['/'],$mod['controller_cache']));
        }
        if (file_exists($source)) {
            $this->xml = file_get_contents($source);
            if ($this->isValidXML($this->xml)) {
                ob_start();
                $this->generateController(new \SimpleXMLElement($this->xml));
                $result = ob_get_clean();
                if (!is_dir($this->getDestination())) {
                    @mkdir($this->getDestination());
                }
                $output = $this->getDestination().'/'.$controller.'Controller.php';
                if (!file_put_contents($output,$result)) {
                    print("Wasn't able to write out compiled controller to $output \n");
                }
                if (!$identifier) {
                    $module = $this->getInfo();
                    $identifier = $module['namespace'].'/'.$controller;
                }
                $this->stampIt($identifier,date("F d Y, H:i:s", filemtime($source)));
            } else {
                $message='';
                foreach ($this->errors as $idx => $error) {
                    $message .= 'LINE: '.$error->line.', COL: '.$error->column.' - '.$error->message."\<br />";
                }
                throw new \Exceptions\MalformedXMLException("The Controller XML [".$source."] is malformed.<ul>".$message.'</ul>',20);
                print("\nThe XML is not valid or malformed [".$source."]\n\n");
            }
        } else {
            throw new \Exceptions\MissingControllerXMLException("The Controller XML [".$source."] was not found.",20);
            print("Could not find source file: ".$source);
        }
    } 

    /**
     *
     *
     * @param type $file
     */
    public function compileFile($file=false) {
       if ($file) {
           $parts       = explode(DIRECTORY_SEPARATOR,$file);
           $module      = \Humble::getEntity('humble/modules')->setModule($parts[2])->load(true);
           $controller  = explode('.',$parts[count($parts)-1]);
           print("\n".'Compiling controller '.$file."\n\n");
           $this->compile($module['namespace'].'/'.$controller[0]);
       }
    }

    /**
     * Accessors and Mutators
     * 
     */
    public function getXML()  {
        return $this->xml;
    }
    public function setNamespace($arg)   {   $this->namespace         = $arg;             }
    public function setComponent($arg)   {   $this->component         = $arg;             }
    public function setSource($arg)      {   $this->source            = 'Code/'.$arg;     }
    public function getSource()          {   return $this->source;                        }
    public function getDestination()     {   return $this->destination;                   }
    public function setDestination($arg) {   $this->destination       = 'Code/'.$arg;     }
}
?>
