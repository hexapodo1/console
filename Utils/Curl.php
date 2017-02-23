<?php

namespace Utils;

class Curl {
    var $ch;
    var $container;
  
    public function __construct($container){
        $this->container = $container;
    }
    
    public function init($dataCenter, $endpoint = 'policies', $extra = '') {
      $this->ch = curl_init();
      $parameters = $this->container->getParameter($dataCenter);
      $server   = $parameters['server'];
      $port     = $parameters['port'];
      $headers  = $parameters['headers'];
      $apiEndpoint = array();
      $apiEndpoint['policies']  = "http://" . $server . ":" . $port 
          . $parameters['endpoint'] ;
      $apiEndpoint['targets']  = "http://" . $server . ":" . $port 
          . $parameters['endpointTargets'];
      $apiEndpoint['contacts']  = "http://" . $server . ":" . $port 
          . $parameters['endpointContacts'];
      // var_dump($apiEndpoint);
      curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers); // Set The Response Format to Json
      curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); // Will return the response, if false it print the response
      curl_setopt($this->ch, CURLOPT_URL, $apiEndpoint[$endpoint] . $extra); // Set the url
    }
    
    public function execute($array = 0) {
        $result = curl_exec($this->ch);
        curl_close($this->ch);
        return $array === 1 ? json_decode($result, true) : $result;
    }
    
    public function delete($array = 0) {
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        $result = curl_exec($this->ch);
        curl_close($this->ch);
        return $array === 1 ? json_decode($result, true) : $result;
    }
}
