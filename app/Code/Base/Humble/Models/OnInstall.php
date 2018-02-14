<?php
namespace Code\Base\Core\Models;
use Humble;
use Log;
use Environment;
/**
 *
 * Installation Class
 *
 * Methods used during the installation process
 *
 * PHP version 7.2+
 *
 * @category   Logical Model
 * @package    Core
 * @author       <rick@enicity.com>
 * @copyright  2005-present Humble Project
 * @license    https://enicity.com/license.txt
 * @version    <INSERT VERSIONING MECHANISM HERE />
 * @link       https://enicity.com/docs/class-OnInstall.html
 * @since      File available since Release 1.0.0
 */
class OnInstall extends Model
{

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

    public function execute() {
        
    }
}