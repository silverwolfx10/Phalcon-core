<?php

namespace Core\Bootstrap;

class RegisterDatabaseListener
{
    protected function afterMergeConfig($event, $application)
    {
        $di = $application->getDI();
        $config = $di->get('config');
        if(isset($config['databases']) && is_array($config['databases'])){
            foreach ($config['databases'] as $k => $database) {
    
                $db_name = 'db_'.$k;
    
                $di->set($db_name, function() use ($database){
    
                    $config = [
                        'host' => $database->host,
                        'dbname' => $database->dbname,
                        'username' => $database->username,
                        'password' => $database->password,
                    ];
    
                    if (isset($database->port)) {
    
                        $config['port'] = (int)$database->port;
    
                    }
    
                    $connection = new \Phalcon\Db\Adapter\Pdo\Mysql($config);
    
                    return $connection;
    
                });
    
            }
        }

    }
}
