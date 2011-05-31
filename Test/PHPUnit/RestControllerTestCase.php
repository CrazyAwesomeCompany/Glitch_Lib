<?php

abstract class Glitch_Test_PHPUnit_RestControllerTestCase
    extends Zend_Test_PHPUnit_ControllerTestCase
{
    protected $_application;

    protected function setUp()
    {
        $this->bootstrap = array($this, 'appBootstrap');
        parent::setUp();

        // Reset the database to the original settings
        $config = Glitch_Config_Ini::getConfig();
        $sql = file_get_contents($config->resources->db->phpunit->initial_data);
        $db = Glitch_Registry::getDb();
        foreach (preg_split("|;\n|m", $sql, -1, PREG_SPLIT_NO_EMPTY) as $sqlline)
        {
            $db->query($sqlline);
        }
    }

    public function appBootstrap ()
    {
        // Bootstrap the application
        $this->_application = new Zend_Application(GLITCH_APP_ENV, Glitch_Config_Ini::getConfig());
        $this->_application->bootstrap();

        // Set the bootstrapper parameter (this is normally done by the "run" method of zend application
        $front = Zend_Controller_Front::getInstance();
        if($front->getParam('bootstrap') === null) {
            $front->setParam('bootstrap', $this->_application->getBootstrap());
        }
    }

    protected function _doDispatch($requestMethod, $uri, $postData, $httpCode,
                                     $module, $controller, $action, $displayBody=false)
    {
        $this->getFrontController()->setDispatcher(
            Glitch_Controller_Dispatcher_Rest::cloneFromDispatcher(
                $this->getFrontController()->getDispatcher()
        ));
        $this->_request = new Glitch_Controller_Request_RestTestCase();

        // Set dispatch data
        if ($postData != null) {
            $this->_request->setPost($postData);
        }

        $this->_request->setMethod($requestMethod);
        $this->_response = $this->dispatch($uri);

        if ($displayBody) {
            // @codeCoverageIgnoreStart

            // Display (debug) data
            print 'STATUSCODE: ' . $this->_response->getHttpResponseCode()."\n";;
//            print_r($this->_request->getHeaders());
            print_r($this->_response->getHeaders());
            echo $this->_response->getBody();
            flush();
            // @codeCoverageIgnoreEnd
        }

        return $this->_response;
    }

    public function dispatch($url = null)
    {
        // redirector should not exit
        $redirector = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        $redirector->setExit(false);

        // json helper should not exit
        $json = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
        $json->suppressExit = true;

        $request    = $this->getRequest();
        if (null !== $url) {
            $request->setRequestUri($url);
        }
        $request->setPathInfo(null);

        $controller = $this->getFrontController();
        $this->frontController
             ->setRequest($request)
             ->setResponse($this->getResponse())
             ->throwExceptions(false)
             ->returnResponse(true);

        return $this->frontController->dispatch();
    }

    /**
     * @param  $requestMethod
     * @param  $uri
     * @param  $postData
     * @param  $httpCode
     * @param  $module
     * @param  $controller
     * @param  $action
     * @param bool $displayBody
     * @param bool $checkRest
     * @return void
     */
    protected function _testDispatch($requestMethod, $uri, $postData, $httpCode,
                                     $module, $controller, $action, $displayBody=false)
    {
        // Reset to a clean response
        $this->resetResponse();

        // Dispatch to the requested MCA
        $response = $this->_doDispatch($requestMethod, $uri, $postData, $httpCode, $module, $controller, $action, $displayBody);

        // Test if we got the correct response returned
        $this->assertResponseCode($httpCode);

        // Test MCA
        $this->assertModule($module);
        $this->assertController($controller);
        $this->assertAction($action);

        // Reset to a clean request
        $this->resetRequest();

        return $response;
    }

    protected function _testDispatchToError($requestMethod, $uri, $postData, $httpCode,
                                            $module, $controller, $action, $displayBody=false)
    {
        $this->resetResponse();

        $this->_doDispatch($requestMethod, $uri, $postData, $httpCode, $module, $controller, $action, $displayBody);

        $this->assertRoute('rest');
        $this->assertResponseCode($httpCode);

        // Make sure it's the error controller we end up in
        $this->assertModule('general');
        $this->assertController('error');
        $this->assertAction('resterror');

        // But the parameters should contain the 'correct' MCA values
        $this->assertEquals($this->_request->getParam('module'), $module);
        $this->assertEquals($this->_request->getParam('controller'), $controller);
        $this->assertEquals($this->_request->getParam('action'), $action);

        $this->resetRequest();
    }

    protected function _getHeaderFromResponse($name)
    {
        $headers = $this->getResponse()->getHeaders();
        foreach ($headers as $header)
        {
            if ($header['name'] == $name) {
                return $header['value'];
            }
        }
    }

    /**
     * Assert that the last handled request used the given controller
     *
     * @param  string $controller
     * @param  string $message
     * @return void
     */
    public function assertController($controller, $message = '')
    {
        $this->_incrementAssertionCount();
        $calledController = get_class($this->frontController->getDispatcher()
                                                    ->getLastController());
        if ($controller != $calledController) {
            $msg = sprintf('Failed asserting last controller used <"%s"> was "%s"',
                $calledController,
                $controller
            );
            if (!empty($message)) {
                $msg = $message . "\n" . $msg;
            }
            $this->fail($msg);
        }
    }

    /**
     * Assert that the last handled request used the given controller
     *
     * @param  string $controller
     * @param  string $message
     * @return void
     */
    public function assertAction($controller, $message = '')
    {
        $this->_incrementAssertionCount();
        $calledController = $this->frontController->getDispatcher()
                                                    ->getLastActionMethod();
        if ($controller != $calledController) {
            $msg = sprintf('Failed asserting last action method used <"%s"> was "%s"',
                $calledController,
                $controller
            );
            if (!empty($message)) {
                $msg = $message . "\n" . $msg;
            }
            $this->fail($msg);
        }
    }

        /**
     * Assert against DOM selection; should contain exact number of nodes
     *
     * @param  string $path CSS selector path
     * @param  string $count Number of nodes that should match
     * @param  string $message
     * @return void
     */
    public function assertQueryCount($path, $count, $message = '')
    {
        $this->_incrementAssertionCount();
        $constraint = new Zend_Test_PHPUnit_Constraint_DomQuery($path);
        $content    = $this->response->getBody();


        if (!$constraint->evaluate($content, __FUNCTION__, $count)) {
            $constraint->fail($path, $message);
        }
    }

}