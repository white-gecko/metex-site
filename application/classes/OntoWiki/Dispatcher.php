<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @category   OntoWiki
 * @package    OntoWiki
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version   $Id: Dispatcher.php 4095 2009-08-19 23:00:19Z christian.wuerker $
 */

/** 
 * Required Zend classes
 */
require_once 'Zend/Controller/Dispatcher/Standard.php';

/** 
 * Required Erfurt classes
 */
require_once 'Erfurt/Sparql/SimpleQuery.php';

/**
 * OntoWiki dispatcher
 *
 * Overwrites Zend_Controller_Dispatcher_Standard in order to allow for
 * multiple (component) controller directories.
 *
 * @category   OntoWiki
 * @package    OntoWiki
 * @copyright Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @author    Norman Heino <norman.heino@gmail.com>
 */
class OntoWiki_Dispatcher extends Zend_Controller_Dispatcher_Standard
{
    /** 
     * The component manager 
     * @var OntoWiki_Component_Manager 
     */
    protected $_componentManager = null;
    
    /**
     * Sets the component manager
     */
    public function setComponentManager(OntoWiki_Component_Manager $componentManager)
    {
        $this->_componentManager = $componentManager; 
    }
    
    /**
     * Get controller class name
     *
     * Try request first; if not found, try pulling from request parameter;
     * if still not found, fallback to default
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return string|false Returns class name on success
     */
    public function getControllerClass(Zend_Controller_Request_Abstract $request)
    {
        $controllerName = $request->getControllerName();

        if (empty($controllerName)) {
            if (!$this->getParam('useDefaultControllerAlways')) {
                return false;
            }
            $controllerName = $this->getDefaultControllerName();
            $request->setControllerName($controllerName);
        }

        $className = $this->formatControllerName($controllerName);

        $controllerDirs      = $this->getControllerDirectory();
        $this->_curModule    = $this->_defaultModule;
        $this->_curDirectory = $controllerDirs[$this->_defaultModule];
        $module = $request->getModuleName();

        if ($this->isValidModule($module)) {
            $this->_curModule    = $module;
            $this->_curDirectory = $controllerDirs[$module];
        } else {
            $request->setModuleName($this->_curModule);
        }

        // PATCH
        // if component manager has controller registered
        // redirect to specific controller dir index        
        if (null !== $this->_componentManager && $this->_componentManager->isComponentRegistered($controllerName)) {
            $this->_curDirectory = $controllerDirs[$this->_componentManager->getComponentPrefix() . $controllerName];
        }

        return $className;
    }
    
    /**
     * Returns TRUE if the Zend_Controller_Request_Abstract object can be
     * dispatched to a controller.
     *
     * Use this method wisely. By default, the dispatcher will fall back to the
     * default controller (either in the module specified or the global default)
     * if a given controller does not exist. This method returning false does
     * not necessarily indicate the dispatcher will not still dispatch the call.
     *
     * @param Zend_Controller_Request_Abstract $action
     * @return boolean
     */
    public function isDispatchable(Zend_Controller_Request_Abstract $request)
    {
        $className = $this->getControllerClass($request);
        if ($className) {
            if (class_exists($className, false)) {
                return true;
            }

            $fileSpec    = $this->classToFilename($className);
            $dispatchDir = $this->getDispatchDirectory();
            $test        = $dispatchDir . DIRECTORY_SEPARATOR . $fileSpec;
            // component controller found
            if (Zend_Loader::isReadable($test)) {
                // return that request is dispatchable
                return true;
            }
        }
        
        /**
         * @trigger onIsDispatchable 
         * Triggered if no suitable controller has been found. Plug-ins can 
         * attach to this event in order to modify request URLs or provide 
         * mechanisms that do not allow a controller/action mapping from URL
         * parts.
         */
        require_once 'Erfurt/Event.php';
        $event = new Erfurt_Event('onIsDispatchable');
        $event->uri     = OntoWiki_Application::getInstance()->config->urlBase . ltrim($request->getPathInfo(), '/');
        $event->request = $request;
        
        // We need to make sure that registered plugins return a boolean value!
        // Otherwise we return false.
        $eventResult = $event->trigger();
        if (is_bool($eventResult)) {
            return $eventResult;
        }
        
        return false;
    }
}

