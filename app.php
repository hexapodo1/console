#!/usr/bin/env php
<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Command\WrongNotificationsListCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class app extends Application{
    
    var $container;
    var $loader;
    
    public function loadConfig(){
        $this->container = new ContainerBuilder();
        $this->loader = new YamlFileLoader($this->container, new FileLocator(__DIR__));
        $this->loader->load('config.yml');
    }  
    
    public function getContainer() {
        return $this->container;
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

$application->run();
