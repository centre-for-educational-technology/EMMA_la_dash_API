<?php

class EmmaDashboardMongoDb {
  public function __construct ($host, $port, $database, $username, $password, $lrsId) {
    $this->host = $host;
    $this->port = $port;
    $this->database = $database;
    $this->username = $username;
    $this->password = $password;
    $this->lrsId = $lrsId;

    try {
      $this->connection = new MongoClient('mongodb://' . $this->host . ':' . $this->port, array(
        'username' => $this->username,
        'password' => $this->password,
        'db' => $this->database
      ));
    } catch (Exception $e) {
      error_log('Database Error: ' . $e->getMessage());
      throw new Exception('Database Error, please contact Administrator.');
    }
  }

  /**
   * Extracts values from an array and returns the first one.
   * Mainly used to extract names/titles, ignoring language settings.
   * @param array $array An array to extract the value from
   * @return mixed Extracted first value
   */
  public static function getFirstValueFromArray($array) {
    $values = array_values($array);

    return $values[0];
  }

  /**
   * Fetch dat from database using find()
   * @param  array $query Query to run
   * @return MongoCollection Collection of ruturned documents
   */
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
