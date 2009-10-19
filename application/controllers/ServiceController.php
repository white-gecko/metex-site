<?php

require_once 'Zend/Controller/Action.php';

/**
 * OntoWiki index controller.
 * 
 * @package    application
 * @subpackage mvc
 * @author     Norman Heino <norman.heino@gmail.com>
 * @copyright  Copyright (c) 2008, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 * @version    $Id: ServiceController.php 4287 2009-10-12 14:00:43Z jonas.brekle@gmail.com $
 */
class ServiceController extends Zend_Controller_Action
{
    /** @var OntoWiki_Application */
    protected $_owApp = null;
    
    /** @var Zend_Config */
    protected $_config = null;
    
    /**
     * Attempts an authentication to the underlying Erfurt framework via 
     * HTTP GET/POST parameters.
     */
    public function authAction()
    {
        if (!$this->_config->service->allowGetAuth) {
            // disallow get
            if (!$this->_request->isPost()) {
                $this->_response->setRawHeader('HTTP/1.0 405 Method Not Allowed');
                $this->_response->setRawHeader('Allow: POST');
                exit();
            }
        }
    
        // fetch params
        if (isset($this->_request->logout)) {
            $logout = $this->_request->logout == 'true' ? true : false;
        } elseif (isset($this->_request->u)) {
            $username = $this->_request->u;
            $password = $this->_request->getParam('p', '');
        } else {
            $this->_response->setRawHeader('HTTP/1.0 400 Bad Request');
            // $this->_response->setRawHeader('');
            exit();
        }
      
        if ($logout) {
            // logout
            require_once 'Erfurt/Auth.php';
            Erfurt_Auth::getInstance()->clearIdentity();
            session_destroy();
            $this->_response->setRawHeader('HTTP/1.0 200 OK');
            exit();
        } else {
            // authenticate
            $result = $owApp->erfurt->authenticate($username, $password);
        }
      
        // return HTTP result
        if ($result->isValid()) {
            // return success (200)
            $this->_response->setRawHeader('HTTP/1.0 200 OK');
            exit();
        } else {
            // return fail (401)
            $this->_response->setRawHeader('HTTP/1.0 401 Unauthorized');
            exit();
        }
    }
    
    /**
     * Entity search
     */
    public function entitiesAction()
    {
        $type  = (string) $this->_request->getParam('type', 's');
        $match = (string) $this->_request->getParam('match');
        
        $type = $type[0]; // use only first letter
        
        if ($this->_owApp->selectedModel && strlen($match) > 2) {
            $namespaces = $this->_owApp->selectedModel->getNamespaces();
            
            $namespacesFlipped = array_flip($namespaces);
            $nsFilter = array();
            foreach ($namespacesFlipped as $prefix => $uri) {
                if (stripos($prefix, $match) === 0) {
                    $nsFilter[] = 'FILTER (regex(str(?' . $type . '), "' . $uri . '"))';
                }
            }
            
            $store = $this->_owApp->selectedModel->getStore();
            require_once 'Erfurt/Sparql/SimpleQuery.php';
            $query = Erfurt_Sparql_SimpleQuery::initWithString(
                'SELECT DISTINCT ?' . $type . '
                FROM <' . $this->_owApp->selectedModel->getModelIri() . '>
                WHERE {
                    ?s ?p ?o.
                    ' . implode(PHP_EOL, $nsFilter) . '
                }'
            );
            
            // var_dump((string) $query);
            // var_dump($store->sparqlQuery($query));
        }
    }

    public function hierarchyAction()
    {
        $options = array();
        if (isset($this->_request->entry)) {
            $options['entry'] = $this->_request->entry;
        }
        
        require_once 'OntoWiki/Model/Hierarchy.php';
        $model = new OntoWiki_Model_Hierarchy(Erfurt_App::getInstance()->getStore(), 
                                              $this->_owApp->selectedModel, 
                                              $options);
        
        $this->view->open = true;
        $this->view->classes = $model->getHierarchy();
        $this->_response->setBody($this->view->render('partials/hierarchy_list.phtml'));
        // $this->_response->setBody(json_encode($model->getHierarchy()));
    }
    
    /**
     * Constructor
     */
    public function init()
    {
        // init controller variables
        $this->_owApp   = OntoWiki_Application::getInstance();
        $this->_config  = $this->_owApp->config;
        $this->_session = $this->_owApp->session;
        
        // prepare Ajax context
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('view', 'html')
                    ->addActionContext('form', 'html')
                    ->addActionContext('process', 'json')
                    ->initContext();
    }
    
    /**
     * Menu Action to generate JSON serializations of OntoWiki_Menu for context-, module-, component-menus
     */
    public function menuAction()
    {
        $module   = $this->_request->getParam('module');
        $resource = $this->_request->getParam('resource');

        $translate = $this->_owApp->translate;

        // create empty menu first
        require_once 'OntoWiki/Menu/Registry.php';
        $menuRegistry = OntoWiki_Menu_Registry::getInstance();
        $menu = $menuRegistry->getMenu(EF_RDFS_RESOURCE);

        if (!empty($module)) {
            $moduleRegistry = OntoWiki_Module_Registry::getInstance();
            $menu = $moduleRegistry->getModule($module)->getContextMenu();
        }
        
        if (!empty($resource)) {
            $models = array_keys($this->_owApp->erfurt->getStore()->getAvailableModels(true));
            $isModel = in_array($resource, $models);
            
            // $menu->prependEntry('Edit Resource', $this->_config->urlBase . 'resource/edit/?r=')

            if ($this->_owApp->erfurt->getAc()->isModelAllowed('edit', $this->_owApp->selectedModel) ) {
                // Delete resource option
                $url = new OntoWiki_Url(
                    array('controller' => 'resource', 'action' => 'delete'),
                    array()
                );
                if ($isModel) {
                    $url->setParam('m',$resource,false);
                }
                $url->setParam('r',$resource,true);
                $menu->prependEntry(
                    'Delete Resource',
                    (string) $url
                );
                
            
                
            }
            
            // add resource menu entries
            $url = new OntoWiki_Url(
                array( 'action' => 'view'),
                array()
            );
            if ($isModel) {
                $url->setParam('m',$resource,false);
            }
            $url->setParam('r',$resource,true);

            $menu->prependEntry(
                'View Resource',
                (string) $url
            );
            
            
            
            
            
            
            if ($isModel) {    
                // add a seperator
                $menu->prependEntry(OntoWiki_Menu::SEPARATOR);
                
                
                // can user delete models?
                if ( $this->_owApp->erfurt->getAc()->isModelAllowed('edit', $resource) &&
                     $this->_owApp->erfurt->getAc()->isActionAllowed('ModelManagement') 
                ) {

                    $url = new OntoWiki_Url(
                        array('controller' => 'model', 'action' => 'delete'),
                        array()
                    );
                    $url->setParam('m',$resource,false);

                    $menu->prependEntry(
                        'Delete Knowledge Base',
                        (string) $url
                    );
                }
                
                
                // add entries for supported export formats
                require_once 'Erfurt/Syntax/RdfSerializer.php';
                foreach (array_reverse(Erfurt_Syntax_RdfSerializer::getSupportedFormats()) as $key => $format) {

                    $url = new OntoWiki_Url(
                        array('controller' => 'model', 'action' => 'export'),
                        array()
                    );
                    $url->setParam('m',$resource,false);
                    $url->setParam('f',$key);

                    $menu->prependEntry(
                        'Export Knowledge Base as ' . $format,
                        (string) $url
                    );
                }
                
                
                // check if model could be edited (prefixes and data)
                if ($this->_owApp->erfurt->getAc()->isModelAllowed('edit', $resource)) {

                    $url = new OntoWiki_Url(
                        array('controller' => 'model', 'action' => 'add'),
                        array()
                    );
                    $url->setParam('m',$resource,false);
                    $menu->prependEntry(
                        'Add Data to Knowledge Base',
                        (string) $url
                    );

                    $url = new OntoWiki_Url(
                        array('controller' => 'model', 'action' => 'config'),
                        array()
                    );
                    $url->setParam('m',$resource,false);
                    $menu->prependEntry(
                        'Configure Knowledge Base',
                        (string) $url
                    );
                }
                

                // Select Knowledge Base
                $url = new OntoWiki_Url(
                    array('controller' => 'model', 'action' => 'select'),
                    array()
                );
                $url->setParam('m',$resource,false);
                $menu->prependEntry(
                    'Select Knowledge Base',
                    (string) $url
                );
            } else {
                $query = Erfurt_Sparql_SimpleQuery::initWithString(
                    'SELECT * 
                     FROM <' . (string) $this->_owApp->selectedModel . '> 
                     WHERE {
                        <' . $resource . '> a ?type  .  
                     }'
                );
                $results[] = $this->_owApp->erfurt->getStore()->sparqlQuery($query);

                $query = Erfurt_Sparql_SimpleQuery::initWithString(
                    'SELECT * 
                     FROM <' . (string) $this->_owApp->selectedModel . '>
                     WHERE {
                        ?inst a <' . $resource . '> .    
                     } LIMIT 2'
                );

                if ( sizeof($this->_owApp->erfurt->getStore()->sparqlQuery($query)) > 0 ) {
                    $hasInstances = true;
                } else {
                    $hasInstances = false;
                }

                $typeArray = array();
                foreach ($results[0] as $row) {
                    $typeArray[] = $row['type'];
                }

                if (in_array(EF_RDFS_CLASS, $typeArray) ||
                    in_array(EF_OWL_CLASS, $typeArray)  ||
                    $hasInstances
                ) {
                    
                    // add a seperator
                    $menu->prependEntry(OntoWiki_Menu::SEPARATOR);

                    $url = new OntoWiki_Url(
                        array('action' => 'list'),
                        array()
                    );
                    $url->setParam('r',$resource,true);

                    // add class menu entries
                    if ($this->_owApp->erfurt->getAc()->isModelAllowed('edit', $this->_owApp->selectedModel) ) {
                        $menu->prependEntry(
                            'Create Instance',
                            "javascript:createInstanceFromClassURI('$resource');"
                        );
                    }
                    $menu->prependEntry(
                        'List Instances',
                        (string) $url
                    );
                     // ->prependEntry('Create Instance', $this->_config->urlBase . 'index/create/?r=')
                     // ->prependEntry('Create Subclass', $this->_config->urlBase . 'index/create/?r=');
                }

            }        
        }
        
        // Fire a event;
        require_once 'Erfurt/Event.php';
        $event = new Erfurt_Event('onCreateMenu');
        $event->menu = $menu;
        $event->resource = $resource;
        
        if (isset($isModel)) {
            $event->isModel = $isModel;
        }
        
        
        $event->model = $this->_owApp->selectedModel;
        $event->trigger();

        echo $menu->toJson();
    }
    
    public function preDispatch()
    {
        // disable auto-rendering
        $this->_helper->viewRenderer->setNoRender();
        
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
    }
    
    public function sessionAction()
    {
        if (!isset($this->_request->name)) {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception("Missing parameter 'name'.");
            exit;
        }
               
        if (isset($this->_request->namespace)) {
            $namespace = $this->_request->namespace;
        } else {
            $namespace = _OWSESSION;
        }
        
        $session = new Zend_Session_Namespace($namespace);
        $name    = $this->_request->name;
        $method = 'set'; // default
        if (isset($this->_request->method)) {
            $method = $this->_request->method;
        }

        if (isset($this->_request->value)) {
            $value = $this->_request->value;
        } else if($method!='unsetArray' && $method!='unsetArrayKey' && !($method=='unset' && !is_array($session->$name))) {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Missing parameter "value".');
            exit;
        }

        if (isset($this->_request->value) && isset($this->_request->valueIsSerialized) && $this->_request->valueIsSerialized == "true") {
            $value = unserialize(urldecode(stripslashes($value)));
        }
        
        if (isset($this->_request->key)) {
            $key = $this->_request->key;
        } else if($method=='setArrayValue' || $method=='unsetArrayKey'){
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Missing parameter "key".');
            exit;
        } 

        switch ($method) {
            case 'set':
                $session->$name = $value;
                break;
             case 'setArrayValue':
                if(!is_array($session->$name)) $session->$name = array();
                $array = $session->$name;
                $array[$key] = $value;
                $session->$name = $array; //strange (because the __get and __set interceptors)
                break;
            case 'push':
                if (!is_array($session->$name)) {
                    $session->$name = array();
                }
                array_push($session->$name, $value);
                break;
            case 'merge':
                if (!is_array($session->$name)) {
                    $session->$name = array();
                }
                $session->$name = array_merge($session->$name, $value);
                break;
            case 'unset':
                // unset a value by inverting the array
                // and unsetting the specified key
                if (is_array($session->$name)) {
                    $valuesAsKeys = array_flip($session->$name);
                    unset($valuesAsKeys[$value]);
                    $session->$name = array_flip($valuesAsKeys);
                } else {
                    //unset a non-array
                    unset($session->$name);
                }
                break;
            case 'unsetArrayKey':
                //done this way because of interceptor-methods...
                $new = array();
                if(is_array($session->$name)){
                   foreach($session->$name as $comparekey => $comparevalue){
                        if($comparekey != $key){
                            $new[] = $comparevalue;
                        }
                    }
                }
                $session->$name = $new;
                break;
            case 'unsetArray':
                // unset the array
                // (the above unsets only values in arrays)
                unset($session->$name);
                break;
        }
        
        $msg = 'sessionStore: ' 
             . $name 
             . ' = ' 
             . print_r($session->$name, true);
        
        $this->_owApp->logger->debug($msg);
    }
    
    /**
     * OntoWiki Sparql Endpoint
     *
     * Implements the SPARQL protocol according to {@link http://www.w3.org/TR/rdf-sparql-protocol/}.
     */
    public function sparqlAction()
    {
        // service controller needs no view renderer
        $this->_helper->viewRenderer->setNoRender();
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        
        $store    = OntoWiki_Application::getInstance()->erfurt->getStore();
        $response = $this->getResponse();
        
        // fetch params
        // TODO: support maxOccurs:unbound
        $queryString  = $this->_request->getParam('query', '');
        if (get_magic_quotes_gpc()) {
            $queryString = stripslashes($queryString);
        }
        $defaultGraph = $this->_request->getParam('default-graph-uri', null);
        $namedGraph   = $this->_request->getParam('named-graph-uri', null);
        
        if (!empty($queryString)) {
            require_once 'Erfurt/Sparql/SimpleQuery.php';
            $query = Erfurt_Sparql_SimpleQuery::initWithString($queryString);

            // overwrite query-specidfied dataset with protocoll-specified dataset
            if (null !== $defaultGraph) {
                $query->setFrom((array) $defaultGraph);
            }
            if (null !== $namedGraph) {
                $query->setFromNamed((array) $namedGraph);
            }

            // check graph availability
            require_once 'Erfurt/App.php';
            $ac = Erfurt_App::getInstance()->getAc();
            foreach (array_merge($query->getFrom(), $query->getFromNamed()) as $graphUri) {
                if (!$ac->isModelAllowed('view', $graphUri)) {
                    if (Erfurt_App::getInstance()->getAuth()->getIdentity()->isAnonymousUser()) {
                        // In this case we allow the requesting party to authorize...
                        $response->setRawHeader('HTTP/1.1 401 Unauthorized');
                        $response->setHeader('WWW-Authenticate', 'FOAF+SSL');
                        $response->sendResponse();
                        exit;
                        
                    } else {
                        $response->setRawHeader('HTTP/1.1 500 Internal Server Error')
                                 ->setBody('QueryRequestRefused')
                                 ->sendResponse();
                        exit;
                    }
                    
                    
                    
                }
            }
            
            $typeMapping = array(
                'application/sparql-results+xml'  => 'xml', 
                'application/sparql-results+json' => 'json'   // we have to transform to JSON ourselves
            );
            
            try {
                $type = OntoWiki_Utils::matchMimetypeFromRequest($this->_request, array_keys($typeMapping));
            } catch (Exeption $e) {
                // 
            }
            
            // set default to xml
            if (!isset($typeMapping[$type])) {
                $type = 'application/sparql-results+xml';
            }

            try {
                // get result for mimetype
                $result = $store->sparqlQuery($query, array('result_format' => $typeMapping[$type]));
            } catch (Exception $e) {
                $response->setRawHeader('HTTP/1.1 400 Bad Request')
                         ->setBody('MalformedQuery: ' . $e->getMessage())
                         ->sendResponse();
                exit;
            }

            $response->setHeader('Content-Type', $type);
            $response->setBody($result);
            $response->sendResponse();
            exit;
        }
    }
    
    /**
     * OntoWiki Update Endpoint
     *
     * Only data inserts and deletes are implemented at the moment (e.g. no graph patterns).
     * @todo LOAD <> INTO <>, CLEAR GRAPH <>, CREATE[ SILENT] GRAPH <>, DROP[ SILENT] GRAPH <>
     */
    public function updateAction()
    {
        // service controller needs no view renderer
        $this->_helper->viewRenderer->setNoRender();
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();
        
        $store      = OntoWiki_Application::getInstance()->erfurt->getStore();
        $response   = $this->getResponse();
        $namedGraph = $this->_request->getParam('named-graph-uri', null);
        
        if (isset($this->_request->query)) {            
            // we have a query, enter SPARQL/Update mode
            $query = $this->_request->getParam('query', '');
            
            $matches = array();
            // insert
            preg_match('/INSERT\s+DATA(\s+INTO\s*<(.+)>)?\s*{\s*([^}]*)/i', $query, $matches);
            $insertGraph   = isset($matches[2]) ? $matches[2] : '';
            $insertTriples = isset($matches[3]) ? $matches[3] : '';
            
            // delete
            preg_match('/DELETE\s+DATA(\s+FROM\s*<(.+)>)?\s*{\s*([^}]*)/i', $query, $matches);
            $deleteGraph   = isset($matches[2]) ? $matches[2] : '';
            $deleteTriples = isset($matches[3]) ? $matches[3] : '';
            
            require_once 'Erfurt/Syntax/RdfParser.php';
            $parser = Erfurt_Syntax_RdfParser::rdfParserWithFormat('nt');
            $insert = $parser->parse($insertTriples, Erfurt_Syntax_RdfParser::LOCATOR_DATASTRING);
            $parser->reset();
            $delete = $parser->parse($deleteTriples, Erfurt_Syntax_RdfParser::LOCATOR_DATASTRING);            
        } else {
            // no query, inserts and delete triples by JSON via param
            $insert = json_decode($this->_request->getParam('insert', '{}'), true);
            $delete = json_decode($this->_request->getParam('delete', '{}'), true);
        }
        
        if (empty($insert) or empty($delete)) {
            // TODO: error
        }
        
        // update the graph
        $model = null;
        try {
            $model = $store->getModel($namedGraph);
        } catch (Erfurt_Store_Exception $e) {
            // TODO: error
        }
        
        if ($model and $model->isEditable()) {
            // TODO: this should be a transaction
            $model->deleteMultipleStatements((array) $delete);
            $model->addMultipleStatements((array) $insert);
        } else {
            // When no user is given (Anoymous) give the requesting party a chance to authenticate.
            if (Erfurt_App::getInstance()->getAuth()->getIdentity()->isAnonymousUser()) {
                // In this case we allow the requesting party to authorize...
                $response->setRawHeader('HTTP/1.1 401 Unauthorized');
                $response->setHeader('WWW-Authenticate', 'FOAF+SSL');
                $response->sendResponse();
                exit;
            }
        }
    }
    
    /**
     * Renders a template and responds with the output.
     *
     * All GET and POST parameters are populated into the view object
     * and therefore available in the view script. You have to know
     * which parameters the script uses and objects obviously cannot
     * be passed via GET/POST.
     */
    public function templateAction()
    {
        // fetch folder parameter
        if (isset($this->_request->f)) {
            $folder = $this->_request->getParam('f');
        } else {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Missing parameter f!');
            exit;
        }

        // fetch template parameter
        if (isset($this->_request->t)) {
            $template = $this->_request->getParam('t');
        } else {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Missing parameter t!');
            exit;
        }

        if (!preg_match('/^[a-z_]+$/', $folder) || !preg_match('/^[a-z_]+$/', $template)) {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Illegal characters in folder or template name!');
            exit;
        }

        $path = _OWROOT . $this->_config->themes->path . $this->_config->themes->default . 'templates/' . $folder . DIRECTORY_SEPARATOR;
        $file = $template . '.' . $this->_helper->viewRenderer->getViewSuffix();

        if (!is_readable($path . $file)) {
            // $this->log('Template file not readable: ' . $path .  $file, Zend_Log::ERR);
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Template file not readable. ' . $path .  $file);
            exit;
        }

        // set script path
        $this->view->setScriptPath($path);

        // assign get and post parameters to view
        $this->view->assign($this->_request->getParams());

        // set header
        $this->_response->setRawHeader('Content-type: text/html');

        // render script
        $this->_response->setBody($this->view->render($file));
    }


    /**
     * JSON outputs of the transitive closure of resources to a given start
     * resource and an transitive attribute
     */
    public function transitiveclosureAction()
    {
        // service controller needs no view renderer
        $this->_helper->viewRenderer->setNoRender();
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();

        $store    = OntoWiki_Application::getInstance()->erfurt->getStore();
        $response = $this->getResponse();

        // fetch start resource parameter
        if (isset($this->_request->sr)) {
            $resource = $this->_request->getParam('sr', null, true);
        } else {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Missing parameter sr (start resource)!');
            exit;
        }

        // fetch property resource parameter
        if (isset($this->_request->p)) {
            $property = $this->_request->getParam('p', null, true);
        } else {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Missing parameter p (property)!');
            exit;
        }

        // m is automatically used and selected
        if ((!isset($this->_request->m)) && (!$this->_owApp->selectedModel)) {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('No model pre-selected model and missing parameter m (model)!');
            exit;
        } else {
            $model = $this->_owApp->selectedModel;
        }
        
        // fetch inverse parameter
        $inverse = $this->_request->getParam('inverse', 'true');
        switch ($inverse) {
            case 'false':   /* fallthrough */
            case 'no':      /* fallthrough */
            case 'off':     /* fallthrough */
            case '0':       
                $inverse = false;
                break;
            default:
                $inverse = true;
        }

        $store = $model->getStore();
        
        // get the transitive closure
        $closure = $store->getTransitiveClosure((string) $model, $property, array($resource), $inverse);

        // send the response
        $response->setHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($closure));
        $response->sendResponse();
        exit;
    }
    
    /**
     * JSON output of the RDFauthor selection Cache File of the current model or
     * of the model given in parameter m
     */
    public function rdfauthorcacheAction()
    {
        // service controller needs no view renderer
        $this->_helper->viewRenderer->setNoRender();
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();

        $store    = OntoWiki_Application::getInstance()->erfurt->getStore();
        $response = $this->getResponse();
        $model    = $this->_owApp->selectedModel;

        if (isset($this->_request->m)) {
            $model = $store->getModel($this->_request->m);
        }
        if (empty($model)) {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Missing parameter m (model) and no selected model in session!');
            exit;
        }

        $output = array();

        $properties = $model->sparqlQuery('SELECT DISTINCT ?uri {
            ?uri a ?propertyClass.
            FILTER(
                sameTerm(?propertyClass, <'.EF_OWL_OBJECT_PROPERTY.'>) ||
                sameTerm(?propertyClass, <'.EF_OWL_DATATYPE_PROPERTY.'>) ||
                sameTerm(?propertyClass, <'.EF_RDF_PROPERTY.'>)
            )} LIMIT 200 ');
        if (!empty($properties)) {

            // push all URIs to titleHelper
            require_once 'OntoWiki/Model/TitleHelper.php';
            $titleHelper = new OntoWiki_Model_TitleHelper($model);
            foreach($properties as $property) {
                 $titleHelper->addResource($property['uri']);
            }

            $lastProperty = end($properties);
            foreach($properties as $property) {
                $newProperty = array();

                // return title from titleHelper
                $newProperty['title'] = $titleHelper->getTitle($property['uri']);

                $pdata = $model->sparqlQuery('SELECT DISTINCT ?key ?value
                    WHERE {
                        <'.$property['uri'].'> ?key ?value
                        FILTER(
                         sameTerm(?key, <'.EF_RDF_TYPE.'>) ||
                         sameTerm(?key, <'.EF_RDFS_RANGE.'>)
                        )
                        FILTER(isUri(?value))
                    }
                LIMIT 20');

                if (!empty($pdata)) {
                    $types = array();
                    $ranges = array();
                    // prepare the data in arrays
                    foreach($pdata as $data) {
                        if ( ($data['key'] == EF_RDF_TYPE) && ($data['value'] != EF_RDF_PROPERTY) ) {
                            $types[] = $data['value'];
                        } elseif ($data['key'] == EF_RDFS_RANGE) {
                            $ranges[] = $data['value'];
                        }
                    }

                    if (!empty($types)) {
                        $newProperty['types'] = $types;
                    }

                    if (!empty($ranges)) {
                        $newProperty['ranges'] = $ranges;
                    }

                }
                $output[ $property['uri'] ] = $newProperty;
            }
        }

        // send the response
        $response->setHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($output));
        $response->sendResponse();
        exit;
    }


    /**
     * JSON output of the RDFauthor init config, which is a RDF/JSON Model
     * without objects where the user should be able to add data
     *
     * get/post parameters:
     *   mode - class, resource or clone
     *          class: prop list based on one class' resources
     *          resource: prop list based on one resource
     *          clone: prop list and values based on one resource (with new uri)
     *   uri  - parameter for mode (class uri, resource uri)
     */
    public function rdfauthorinitAction()
    {
        // service controller needs no view renderer
        $this->_helper->viewRenderer->setNoRender();
        // disable layout for Ajax requests
        $this->_helper->layout()->disableLayout();

        $store    = OntoWiki_Application::getInstance()->erfurt->getStore();
        $response = $this->getResponse();
        $model    = $this->_owApp->selectedModel;

        if (isset($this->_request->m)) {
            $model = $store->getModel($this->_request->m);
        }
        if (empty($model)) {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Missing parameter m (model) and no selected model in session!');
            exit;
        }

        if ( (isset($this->_request->uri)) && (Zend_Uri::check($this->_request->uri)) ) {
            $parameter = $this->_request->uri;
        } else {
            require_once 'OntoWiki/Exception.php';
            throw new OntoWiki_Exception('Missing or invalid parameter uri (clone uri) !');
            exit;
        }

        if (isset($this->_request->mode)) {
            $workingModus = $this->_request->mode;
        } else {
            $workingModus = 'resource';
        }


        $newResourceURI = $model->getBaseUri().md5(date('F j, Y, g:i:s:u a'));

        if ($workingModus == 'class') {
            $properties = $model->sparqlQuery('SELECT DISTINCT ?uri ?value {
                ?s ?uri ?value.
                ?s a <'.$parameter.'>.
                } LIMIT 20 ');
        } elseif ($workingModus == 'clone') {
            # BUG: more than one values of a property are not supported right now
            # BUG: Literals are not supported right now
            $properties = $model->sparqlQuery('SELECT ?uri ?value {
                <'.$parameter.'> ?uri ?value.
                FILTER (isUri(?value))
                } LIMIT 20 ');
        } else { // resource
            $properties = $model->sparqlQuery('SELECT DISTINCT ?uri ?value {
                <'.$parameter.'> ?uri ?value.
                } LIMIT 20 ');
        }

        $output = (object) array();
        if (!empty($properties)) {

            // push all URIs to titleHelper
            require_once 'OntoWiki/Model/TitleHelper.php';
            $titleHelper = new OntoWiki_Model_TitleHelper($model);
            foreach($properties as $property) {
                 $titleHelper->addResource( $property['uri'] );
            }

            $newProperties = (object) array();
            foreach($properties as $property) {
                $uri = $property['uri'];
                $value = (object) array();
                
                // return title from titleHelper
                $value->title = $titleHelper->getTitle($uri);

                if (($uri == EF_RDF_TYPE) && 
                  (($workingModus == 'resource') || ($workingModus == 'clone')) ) {
                    $value->value = $property['value'];
                    $value->type = 'uri';
                    $value->hidden = true;
                } elseif (($uri == EF_RDF_TYPE) && ($workingModus == 'class') ) {
                    $value->value = $parameter;
                    $value->type = 'uri';
                    $value->hidden = true;
                }
                if (($workingModus == 'clone')&&($uri != EF_RDF_TYPE)) {
                    $value->type = 'uri';
                    $value->value = $property['value'];
                }

                $newProperties->$uri = array ($value);
            }

            $output->$newResourceURI = $newProperties;
        } else {
            $newProperties = (object) array();
            if ($workingModus == "class") {
                $value = (object) array();
                $uri = EF_RDF_TYPE;
                $value->value = $parameter;
                $value->type = 'uri';
                $value->hidden = true;
                $newProperties->$uri = array ($value);

                $value = (object) array();
                $uri = EF_RDFS_LABEL;
                $value->type = 'literal';
                $value->title = "label";
                $newProperties->$uri = array ($value);
            } else { // resource
                $value = (object) array();
                $uri = EF_RDFS_LABEL;
                $value->type = 'literal';
                $value->title = "label";
                $newProperties->$uri = array ($value);
            }
            $output->$newResourceURI = $newProperties;
        }

        // send the response
        $response->setHeader('Content-Type', 'application/json');
        $response->setBody(json_encode($output));
        $response->sendResponse();
        exit;
    }

}
