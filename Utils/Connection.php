<?php

namespace Utils;

class Connection {
  
  var $conn;
  var $container;

  public function __construct($container){
      $this->container = $container;      
  }
  
  public function init ($datacenter) {
    // $servername, $username, $password, $db, $port
      $parameters = $this->container->getParameter($datacenter);
      $dbConfig = $parameters['db'];
    
      // Create connection
      $this->conn = new \mysqli($dbConfig['servername'], $dbConfig['username'], $dbConfig['password'], $dbConfig['db'], $dbConfig['port']);
      
      // Check connection
      if ($this->conn->connect_error) {
          die("Connection failed: " . $this->conn->connect_error);
      } 
  }
  
  public function query($sql) {
      $result = $this->conn->query($sql);
      $rows = array();
      if ($result) {
          while($row = $result->fetch_assoc()) {
              $rows[] = $row;
          }
      }
      return $rows;
  }
  
  
  
}
