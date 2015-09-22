<?php

class EmmaDashboardMongoDb {
  public function __construct ($host, $port, $database, $username, $password, $lrsId) {
    $this->host = $host;
    $this->port = $port;
    $this->database = $database;
    $this->username = $username;
    $this->password = $password;
    $this->lrsId = $lrsId;

    $this->connection = new MongoClient('mongodb://' . $this->host . ':' . $this->port, array(
      'username' => $this->username,
      'password' => $this->password,
      'db' => $this->database
    ));
  }

  public function fetchData ($query) {
    $query['lrs._id'] = $this->lrsId;

    $db = $this->connection->selectDB($this->database);

    $collection = $db->statements;

    return $collection->find($query);
  }

  public function fetchCount($query, $options = array()) {
    $query['lrs._id'] = $this->lrsId;

    $db = $this->connection->selectDB($this->database);

    $collection = $db->statements;

    return $collection->count($query, $options);
  }

  public function fetchAggregate($pipeline, $options = array()) {
    $pipeline[0]['$match']['lrs._id'] = $this->lrsId;

    $db = $this->connection->selectDB($this->database);

    $collection = $db->statements;

    return $collection->aggregate($pipeline, $options);
  }
}
