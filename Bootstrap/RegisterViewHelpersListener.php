<?php

namespace Core\Bootstrap;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as PhVolt;

class RegisterViewHelpersListener
{
    protected function afterMergeConfig($event, $application)
    {
        $di = $application->getDI();
        $config = $di->get('config');

        if(isset($config['volt']) && $config['volt']){
            $di->set(
                'volt',
                function($view, $di) use($config)
                {   

                    $volt = new PhVolt($view, $di);
                    $volt->setOptions([
                        'compiledPath'      =>  $config['volt']['compiledPath'],
                        'compiledExtension' =>  $config['volt']['compiledExtension'],
                        'compiledSeparator' =>  $config['volt']['compiledSeparator'],
                        'stat'              =>  $config['volt']['stat'],
                    ]);
                    
                    //filtros de view customizados
                    if(isset($config['view_helpers_filters']) and is_array($config['view_helpers_filters'])){
                        foreach($config['view_helpers_filters'] as $key => $function){
                            $volt->getCompiler()->addFilter($key, $function);
                        }
                    }

                    //filtros de view customizados
                    if(isset($config['view_helpers']) and is_array($config['view_helpers'])){
                        foreach($config['view_helpers'] as $key => $function){
                            $di->set($key, $function);
                        }
                    }
                    
                    return $volt;
                }
            );
        }

        


    }
}
