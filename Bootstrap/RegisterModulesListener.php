<?php

namespace Core\Bootstrap;

use Core\Exception\DomainException;

class RegisterModulesListener
{
    public function init($event, $application)
    {
        $di = $application->getDI();
        $config = $di->get('config');

        $modulesConfig = [];
        if (isset($config['modules'])) {
            foreach ($config['modules'] as $moduleNamespace) {
                $modulesConfig[$moduleNamespace] = [
                    'className' => $moduleNamespace . '\Module',
                ];
            }
        }
        if (empty($modulesConfig)) {
            throw new DomainException(
                'Missing configuration of modules.'
            );
        }
        $application->registerModules($modulesConfig, true);
    }
}
