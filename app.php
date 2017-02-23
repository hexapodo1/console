#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Command\WrongNotificationsListCommand;
use Command\OrphanNotificationsCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class app extends Application{
    
    var $container;
    var $loader;
    var $log;
    
    public function loadConfig(){
        $this->container = new ContainerBuilder();
        $this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
        $this->loader->load('config.yml');
        
        // load log component
        date_default_timezone_set('America/Bogota');
        $this->log = new Logger('LOG');
        $this->log->pushHandler(new StreamHandler(__DIR__ . '/logs/history2.log', Logger::DEBUG));
    }  
    
    public function getContainer() {
        return $this->container;
    }
    
    public function getLog() {
        return $this->log;
    }
    
    public function loadYaml($file) {
        $this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
        $this->loader->load($file);
    }
    
}

$application = new app();
$application->loadConfig();

// load each of all commands
$application->add(new WrongNotificationsListCommand());
$application->add(new OrphanNotificationsCommand());

$application->run();
