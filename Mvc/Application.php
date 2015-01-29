<?php


namespace Core\Mvc;

use Phalcon\Mvc\Application as MvcApplication,
    Phalcon\Events\Manager as EventsManager,
    Phalcon\Mvc\Dispatcher,
    Phalcon\Mvc\View,
    //
    Phalcon\DI\FactoryDefault as DiFactory,
    Phalcon\Http\Response,
    Phalcon\Config,
    //
    Phalcon\Debug,
    //
    Phalcon\Mvc\View\Engine\Volt as PhVolt,
    //
    Exception;

class Application extends MvcApplication
{
    /**
     * @var boolean
     */
    protected static $debugMode = false;

    /**
     * @var array
     */
    protected $defaultBootstrapListeners = [
        // bootstrap:init
        'Core\Bootstrap\RegisterModulesPathsListener',
        'Core\Bootstrap\RegisterModulesListener',
        'Core\Bootstrap\LoadModulesListener',

        // bootstrap:beforeMergeConfig
        'Core\Bootstrap\RegisterViewStrategyListener',

        //Database
        'Core\Bootstrap\RegisterDatabaseListener',

        // bootstrap:mergeConfig
        'Core\Bootstrap\ConfigCacheListener',
        'Core\Bootstrap\MergeGlobConfigListener',
        'Core\Bootstrap\MergeModulesConfigListener',

        // bootstrap:afterMergeConfig
        'Core\Bootstrap\RegisterDIListener',
        'Core\Bootstrap\RegisterRoutesListener',

        // bootstrap:bootstrapModules
        'Core\Bootstrap\BootstrapModulesListener',
    ];

    /**
     * @param boolean $flag
     * @return void
     */
    public static function setDebugMode($flag = true)
    {
        $reportingLevel = $flag ? E_ALL | E_STRICT : 0;
        error_reporting($reportingLevel);

        static::$debugMode = (bool) $flag;
    }

    /**
     * @return boolean
     */
    public static function isDebugMode()
    {
        return static::$debugMode;
    }

    /**
     * @param array $configuration
     * @return Core\Mvc\Application
     */
    public static function init($configuration = [])
    {
        static $application;

        if ($application instanceof Application) {
            return $application;
        }

        Application::setDebugMode(Application::isDebugMode());

        $config = new Config($configuration);
        $di = new DiFactory();
        $di->setShared('config', $config);

        $application = new Application();
        $application->setDI($di);

        $eventsManager = $di->getShared('eventsManager');
        $application->setEventsManager($eventsManager);

        $dispatcher = new Dispatcher();
        $dispatcher->setEventsManager($eventsManager);
        $di->setShared('dispatcher', $dispatcher);


        $di->set(
            'volt',
            function($view, $di) use($config)
            {
                $volt = new PhVolt($view, $di);
                $volt->setOptions(
                    array(
                        'compiledPath'      => __DIR__.'/../../../data/volt/',
                        'compiledExtension' => '.compiled',
                        'compiledSeparator' => 'V',
                        'stat'              => (bool) 1,
                    )
                );
                
                //filtros de view customizados
                //1000 number_format($number, 2, '.', '')
                $volt->getCompiler()->addFilter('number_format', function ($resolvedArgs) {
                    return 'number_format(' . $resolvedArgs . ')';
                });

                //1000 | number_format = 1.000,00 , 1000 | number_format(0) = 1.000
                $volt->getCompiler()->addFilter('number_format_br', function ($resolvedArgs) {
                    $args = explode(",", $resolvedArgs);
                    $decimal = isset($args[1])? $args[1] : 2;

                    return 'number_format(' . $args[0] . ', ' . $decimal . ' , \',\' , \'.\'  )';
                });
                
                
                return $volt;
            }
        );

        $view = new View();
        $view->registerEngines([
          ".phtml" => "volt"
        ]);

        $di->setShared('view', $view);





        $di->set(
            'dispatcher',
            function() use ($di) {
                $eventsManager = $di->getShared('eventsManager');
                $eventsManager->attach(
                    'dispatch:beforeException',
                    function($event, $dispatcher, $exception) {
                        switch ($exception->getCode()) {
                            case Dispatcher::EXCEPTION_HANDLER_NOT_FOUND:
                            case Dispatcher::EXCEPTION_ACTION_NOT_FOUND:
                                $dispatcher->forward(
                                    [
                                        'module' => 'Application',
                                        'namespace' => 'Application\Controller',
                                        'controller' => 'index',
                                        'action' => 'notFound',
                                    ]
                                );
                                return false;
                                break; // for checkstyle
                            default:
                                $dispatcher->forward(
                                    [
                                        'module' => 'Application',
                                        'namespace' => 'Application\Controller',
                                        'controller' => 'index',
                                        'action' => 'uncaughtException',
                                    ]
                                );
                                return false;
                                break; // for checkstyle
                        }
                    }
                );
                $dispatcher = new Dispatcher();
                $dispatcher->setEventsManager($eventsManager);
                return $dispatcher;
            },
            true
        );






        return $application->bootstrap();
    }

    /**
     * @return Core\Mvc\Application
     */
    public function bootstrap()
    {
        $eventsManager = $this->getEventsManager();

        foreach ($this->defaultBootstrapListeners as $listener) {
            $listener = new $listener();
            $eventsManager->attach('bootstrap', $listener);
        }

        return $this;
    }

    /**
     * @param string $url optional
     * @return Phalcon\Http\ResponseInterface
     */
    public function handle($url = '')
    {
        try {
            $eventsManager = $this->getEventsManager();
            $eventsManager->fire('bootstrap:init', $this);
            $eventsManager->fire('bootstrap:beforeMergeConfig', $this);
            $eventsManager->fire('bootstrap:mergeConfig', $this);
            $eventsManager->fire('bootstrap:afterMergeConfig', $this);
            $eventsManager->fire('bootstrap:bootstrapModules', $this);
            $eventsManager->fire('bootstrap:beforeHandle', $this);
            return parent::handle($url);
        } catch (Exception $e) {

            if (Application::isDebugMode()) {
                (new Debug())->onUncaughtException($e);
            }

            return new Response();
        }
    }
}
