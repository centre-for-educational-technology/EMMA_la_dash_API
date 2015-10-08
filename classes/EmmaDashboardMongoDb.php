<?php

class EmmaDashboardMongoDbException extends Exception {
}

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
      throw new EmmaDashboardMongoDbException('Database Error, please contact Administrator.');
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
   * Extracts keys from array and returns the first one.
   * Mainly used to extract the language setting.
   * @param  array $array An array to extract the key from
   * @return mixed        Extracted first key
   */
  public static function getFirstKeyFromArray($array) {
    $keys = array_keys($array);

    return $keys[0];
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

  /**
   * Return count of unique visitors for query using Aggregate framework.
   * @param  array $query Query to be used
   * @return int        Count of unique visitors or 0
   */
  public function getUniqueVisitorsCount($query) {
    $pipeline = array(
      array(
        '$match' => $query,
      ),
      array(
        '$group' => array(
          '_id' => 'null',
          'visitors' => array(
            '$addToSet' => '$statement.actor.mbox',
          ),
        ),
      ),
    );

    $aggregate = $this->fetchAggregate($pipeline);

    return isset($aggregate['result'][0]['visitors']) ? count($aggregate['result'][0]['visitors']) : 0;
  }

  /**
   * Return and array of popular resources
   * @param  array  $query Query to be used with aggregate
   * @param  integer $limit Limit of resources to enforce
   * @return array         Data set of resources
   */
  public function getPopularResourcedData($query, $limit = 25) {
    $pipeline = array(
      array(
        '$match' => $query,
      ),
      array(
        '$group' => array(
          '_id' => '$statement.object.id',
          'name' => array(
            '$first' => '$statement.object.definition.name', // TODO Check if taking last also is possible
          ),
          'count' => array(
            '$sum' => 1,
          ),
        ),
      ),
      array(
        '$sort' => array(
          'count' => -1,
        ),
      ),
      array(
        '$limit' => $limit,
      ),
    );

    $aggregate = $this->fetchAggregate($pipeline);

    $resources = array();
    if ( $aggregate['ok'] == 1 && isset($aggregate['result']) && count($aggregate['result']) > 0 ) {
      foreach($aggregate['result'] as $resource) {
        $resources[] = array(
          'url' => $resource['_id'],
          'title' => $this->getFirstValueFromArray($resource['name']),
          'views' => $resource['count'],
          'language' => $this->getFirstKeyFromArray($resource['name']),
        );
      }
    }

    return $resources;
  }

  /**
   * Format timetamp string into date representation.
   * @param  string $timestamp Timestamp string representation
   * @return string            Formatted date
   */
  public static function formatTimestampDate($timestamp) {
    return strftime('%Y-%m-%d', strtotime($timestamp));
  }

  /**
   * Format timestamp string into time representation.
   * @param  string $timestamp Timestamp string representation
   * @return string            Formatted time
   */
  public static function formatTimestampTime($timestamp) {
    return strftime('%H:%M', strtotime($timestamp));
  }
}
