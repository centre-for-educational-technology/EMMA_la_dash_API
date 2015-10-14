<?php

/**
 * There are some issues with this implementation.
 * it creates a catalog for each course.
 * Could run into issues in future.
 */

class EmmaDashboardStorage {
  public function __construct() {
    $this->storage = __DIR__ . '/../storage';
  }

  public function buildFileLocation($course_id, $file_name) {
    if ( !file_exists($this->storage . '/' . $course_id) ) {
      mkdir($this->storage . '/' . $course_id);
    }

    return $this->storage . '/' . $course_id . '/' . $file_name;
  }

  public function readFileIfNotOutdated($course_id, $file_name) {
    $file_location = $this->buildFileLocation($course_id, $file_name);

    if ( file_exists($file_location) ) {
      $modified_time = filemtime($file_location);

      if ( $modified_time && ($modified_time - time()) <= 60 * 60 ) {
        return file_get_contents($file_location);
      }
    }

    return false;
  }

  public function createOrUpdateFile($course_id, $file_name, $data) {
    $file_location = $this->buildFileLocation($course_id, $file_name);

    $handle = fopen($file_location, 'w');
    fwrite($handle, $data);
    fclose($handle);
  }

}
