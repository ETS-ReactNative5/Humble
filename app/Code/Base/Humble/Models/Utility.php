<?php
namespace Code\Base\Humble\Models;
use Humble;
use Environment;
use Log;
/**
 * Short description for file
 *
 * Long description for file (if any)...
 *
 * PHP version 7.2+
 *
 * LICENSE:
 *
 * @category   Utility
 * @package    Core
 * @author     Original Author <rick@humbleprogramming.com>
 * @copyright  2007-Present, Rick Myers <rick@humbleprogramming.com>
 * @license    https://humbleprogramming.com/LICENSE.txt
 * @version    1.0
 * @since      File available since Version 1.0.1
 */
class Utility extends Model
{

    use \Code\Base\Humble\Event\Handler;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Required method for determing who errored
     *
     * @return system
     */
    public function getClassName()
    {
        return __CLASS__;
    }

    /**
     * Compiles a controller
     */
    public function compile()
    {
        $module = Humble::getModule($this->getModule());
        $sourcedir = 'Code/'.$this->getPackage().'/'.str_replace('_','/',$module['controller']);
        $dir = dir($sourcedir);
        while (($entry = $dir->read()) !== false) {
            if (($entry == '.') || ($entry == '..'))
                continue;
            if (is_dir($sourcedir.'/'.$entry))
                continue;
            $file = explode('.',$entry);
            $controller = $file[0];
            $identifier = $module['namespace'].'/'.$controller;
            $compiler   = Environment::getCompiler();
            $compiler->setInfo($module);
            $compiler->setController($controller);
            $compiler->setSource($module['package'].'/'.str_replace('_','/',$module['controller']));
            $compiler->setDestination($module['package'].'/'.str_replace('_','/',$module['controller_cache']));
            $compiler->compile($identifier);
        }
    }

    /**
     * Installs a module
     */
    public function install()
    {
        $installer  = Environment::getInstaller();
        $package    = $this->getPackage();
        $module     = $this->getXml();
        $etc        = 'Code/'.$package.'/'.str_replace("_","/",$module).'/config.xml';
        if (file_exists($etc)) {
            $installer->install($etc);
        } else {
            \Log::console("Configuration File Not Found: $etc");
        }
    }

    /**
     * Uninstalls a module
     */
    public function uninstall()    {
        $status     = false;
        $installer  = Environment::getInstaller();
        $package    = $this->getPackage();
        $ns = $this->getNamespace();
        $module     = Humble::getModule($this->getNamespace(),true);
        if ($module['configuration']) {
            $etc        = 'Code/'.$package.'/'.str_replace("_","/",$module['configuration']).'/config.xml';
            if (file_exists($etc)) {
                $installer->uninstall($etc);
                $status = true;
            } else {
                print("\n\nConfiguration File Not Found: $etc\n\n");
                Log::console("Configuration File Not Found: $etc");
                if ($installer->deregister($ns)) {
                    print("\n\nAn incomplete uninstallation occurred\n\n");
                    $status = true;
                };
            }
        } else {
            print("Configuration location is not set in the config.xml. Uninstall is not possible until this is set.  Please set the value then update the module.");
        }
        return $status;
    }

    /**
     * From the administration section, will create the directory structure of a module component
     */
    public function createPath()
    {
        $dir = 'Code/'.$this->getPackage().'/'.str_replace('_','/',$this->getDirectory());
        $paths = explode('/',$dir);
        $dir = '';
        foreach ($paths as $idx => $path) {
            $dir = $dir.$path.'/';
            if (!is_dir($dir)) {
                print('trying to create '.$dir);
                @mkdir($dir,0775,true);
            }
        }
    }

    /**
     * Command line enabling
     */
    public function enable()
    {
        print("\nCurrently not working\n\n");
    }

    /**
     * Command line disabling
     */
    public function disable()
    {
        print("\nCurrently not working\n\n");
    }

    /**
     * Returns the first location that exists out of an array of file location possibilities
     * 
     * @param array $paths
     */
    private function resolveLocation($paths=[]) {
        $c      = count($paths);
        $found  = false;
        $i      = 0;
        while (!$found && ($i<$c)) {
            if (!$found = file_exists($paths[$i])) {
                $i++;
            }
        }
        return ($found) ? $paths[$i] : false;
    }

    /**
     * Will set the enabled/disabled flag on a module and trigger a recache of the module data
     */
    public function toggleEnableFlag() {
        $ns     = $this->getNamespace();
        $flag   = $this->getEnabled();
        if ($ns) {
            $flag = (!$flag) ? "N" : (($flag=='N') ? 'N' : 'Y');
            Humble::getEntity('humble/modules')->setNamespace($ns)->setEnabled($flag)->save();
            Humble::cache('module-',Humble::getModule($ns));
        }
    }
    
    /**
     * Create a new module
     */
    public function createModule() {
        $user   = Humble::getEntity('humble/users');
        $user->setUid($this->getUid())->load();
        $data   = Humble::getEntity('humble/user_identification')->setId($this->getUid())->load();
        $cmd    = 'php Module.php --b email='.$user->getEmail().' package='.$this->getPackage().' namespace='.$this->getNamespace().' module='.ucfirst($this->getModule()).' prefix='.$this->getPrefix().' author="'.$data['first_name'].' '.$data['last_name'].'"';
        \Log::console($cmd);
        $result = exec($cmd,$output);
        return $result;
    }

    /**
     * Create a new component
     */
    public function createComponent() {
        $data           = Humble::getEntity('humble/users')->setUid($this->getUid())->load();
        $user           = Humble::getEntity('humble/user_identification')->setId($this->getUid())->load();
        $project        = Environment::getProject();
        $module         = Humble::getModule($this->getNamespace());
        $custom_root    = 'Code/'.$project->package.'/'.$project->module.'/lib/sample/component';
        $module_root      = 'Code/'.$module['package'].'/'.$module['module'].'/lib/sample/component';
        $templates      = [];
        $templates['models']   = file_exists($custom_root.'/Model.php.txt')  ? $custom_root.'/Model.php.txt' : 'Code/Base/Humble/lib/sample/component/Model.php.txt';
        $templates['models']   = file_exists($module_root.'/Model.php.txt')  ? $module_root.'/Model.php.txt' : $templates['models'];
        $templates['entities'] = file_exists($custom_root.'/Entity.php.txt') ? $custom_root.'/Entity.php.txt' : 'Code/Base/Humble/lib/sample/component/Entity.php.txt';
        $templates['entities'] = file_exists($module_root.'/Entity.php.txt') ? $module_root.'/Entity.php.txt' : $templates['entities'];        
        $templates['helpers']  = file_exists($custom_root.'/Helper.php.txt') ? $custom_root.'/Helper.php.txt' : 'Code/Base/Humble/lib/sample/component/Helper.php.txt';
        $templates['helpers']  = file_exists($module_root.'/Helper.php.txt') ? $module_root.'/Helper.php.txt' : $templates['helpers'];        
        $roots          = ['models'=>'Model','helpers'=>'Helper','entities'=>'Entity'];
        
        $ns             = 'Code_'.$module['package']."_".$module[$this->getType()];
        $root           = $roots[$this->getType()];
        $trait          = ($this->getGeneratesEvents()=='Y') ? "use \\Code\\Base\\Humble\\Event\\Handler;\n\n\t" : "" ;
        $ns             = str_replace(['_','/'],['\\','\\'],$ns);
        $root           = str_replace(['_','/'],['\\','\\'],$root);
        $parts          = explode('_',$this->getName());
        foreach ($parts as $idx => $part) {
            $parts[$idx] = ucfirst($part);
        }
        $class = $parts[count($parts)-1];
        if (count($parts)>1) {
            $root = "\\".$ns.'\\'.$root;
            for ($i=0; $i<count($parts)-1; $i++) {
                $ns .= '\\'.$parts[$i];
            }
        }
        @mkdir(str_replace('_','/',$ns),0775,true);
        $dest           = $ns.'_'.$class.'.php';
        $template       = $templates[$this->getType()];
        $description    = wordwrap($this->getDescription(),70,"\n * ");
        $project        = file_exists('../Humble.project') ? json_decode(file_get_contents('../Humble.project'),true) : ["factory_name" => 'Humble','project_name'=>'Please set your project name in the Humble.project file'];
        $srch           = array(
            '&&NAMESPACE&&',
            '&&ROOT&&',
            '&&TRAIT&&',
            '&&CLASS&&',
            '&&AUTHOREMAIL&&',
            '&&AUTHORNAME&&',
            '&&PACKAGE&&',
            '&&CATEGORY&&',
            '&&SHORTDESC&&',
            '&&LONGDESC&&',
            '&&FACTORY&&',
            '&&PROJECT&&',
            '&&MODULE&&',
            '&&base_package&&',
            '&&base_module&&'
        );
        if (strpos($root,'_')!==false) {
            $root = '\\'+$root;
        }
        $repl           = array(
            $ns,
            $root,
            $trait,
            $class,
            $data['email'],
            $user['first_name'].' '.$user['last_name'],
            $this->getPackage(),
            $this->getCategory(),
            $this->getTitle(),
            $description,
            $project['factory_name'],
            $project['project_name'],
            $class,
            $project['package'],
            $project['module']
        );
        //ADD A CHECK!
        file_put_contents(str_replace('_','/',$dest),str_replace($srch,$repl,file_get_contents($template)));
    }
    
    /**
     * Create a new controller
     */
    public function createController($useLanding=false) {
        $templaters     = Humble::getEntity('humble/templaters')->fetch();
        $exts           = []; //building an XREF for simplicity...
        foreach ($templaters as $engine) {
            $exts[$engine['templater']] = $engine['extension'];
        }
        $data           = Humble::getEntity('humble/users')->setUid($this->getUid())->load();
        $user           = Humble::getEntity('humble/user/identification')->setId($this->getUid())->load();
        //need to look for other custom controller template as well...
        $project        = \Environment::getProject();
        $templates      = [];
        $main           = Humble::getModule($project->namespace);
        $current        = Humble::getModule($this->_namespace());
        $templates[]    = 'Code'.$current['package'].'/'.$current['module'].'/lib/sample/component/controller.xml';
        $templates[]    = 'Code'.$main['package'].'/'.$main['module'].'/lib/sample/component/controller.xml';
        $templates[]    = 'Code/Base/Humble/lib/sample/component/controller.xml';
        $template       = $this->resolveLocation($templates);
        
        
        if (!$module         = Humble::getModule($this->getNamespace())) {
            return "The ".$this->getNamespace()." module is disabled or does not exist";
        }
        $dest           = 'Code/'.$module['package']."/".$module['controller'];
        $dest           = $dest.'/'.$this->getName().'.xml';
        if (file_exists($dest)) {
            return "A controller with that name already exists [".$dest."]";
        }
        $srch           = array(
            '&&NAME&&',
            '&&ENGINE&&',
            '&&AUTHOREMAIL&&',
            '&&AUTHORNAME&&',
            '&&DESCRIPTION&&',
            '&&ACTIONDESCRIPTION&&',
            '&&ACTION&&'
        );
        $repl           = array(
            $this->getName(),
            $this->getEngine(),
            $data['email'],
            $user['first_name'].' '.$user['last_name'],
            $this->getDescription(),
            $this->getActionDescription(),
            $this->getAction()
        );
        $newDir = str_replace('_','/','Code/'.$module['package'].'/'.$module['views'].'/'.$this->getName().'/'.$this->getEngine());
        @mkdir($newDir,0775,true);
        if (isset($exts[$this->getEngine()])) {
            file_put_contents($newDir.'/'.$this->getAction().'.'.$exts[$this->getEngine()],'');
            if ($useLanding) {
                $loc = getcwd().$newDir;
                file_put_contents($newDir.'/'.$this->getAction().'.'.$exts[$this->getEngine()],str_replace('&&HOME&&',$loc,file_get_contents('Code/Base/Humble/lib/sample/module/Views/actions/Smarty3/landing.tpl')));
            }
        }
        if (file_put_contents(str_replace('_','/',$dest),str_replace($srch,$repl,file_get_contents($template)))) {
            return "Ok";
        } else {
            return "Unable to create the controller";
        }
    }

    /**
     * Runs the documentation engine
     *
     * @workflow use(event)
     */
    public function generateDocumentation() {
        chdir('../lib/apigen');
        $cmd = 'php apigen.php -c apigen.ini';

        $output = shell_exec($cmd);
        $output = explode("\n",$output);
        foreach ($output as $row) {
            if (strlen($row)<300) {
                Humble::response($row."\n");
            }
        }
        chdir('../../app');
        $this->trigger('documentationGenerated',__CLASS__,__METHOD__,["generated"=>date('Y-m-d H:i:s'),'user_id'=>Environment::user()]);
    }

    /**
     * Creates a copy of the component templates in the main modules directory structure
     */
    public function clone() {
        if ($namespace = \Environment::getProject('namespace')) {
            if ($module = Humble::getModule($namespace)) {
                $base = 'Code/Base/Humble/lib';
                $dest = 'Code/'.$module['package'].'/'.$module['module'].'/lib';
                @mkdir($dest,0775,true);
                \Humble::getHelper('humble/directory')->copyDirectory($base,$dest);
            }
        }
    }
    
}
?>