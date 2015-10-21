<?php

/**
 * There are some issues with this implementation.
 * it creates a catalog for each course.
 * Could run into issues in future.
 */

// XXX Need to make sure that directory is writable (either throw an error or
// add that to the installation steps)

class EmmaDashboardStorage {
  public function __construct() {
    $this->storage = EDB_STORAGE_PATH;
  }

  public function buildFileLocation($course_id, $file_name) {
    if ( !file_exists($this->storage . '/' . $course_id) ) {
      // XXX This could fail silently
      mkdir($this->storage . '/' . $course_id);
    }

    return $this->storage . '/' . $course_id . '/' . $file_name;
  }

  public function readFileIfNotOutdated($course_id, $file_name) {
    $file_location = $this->buildFileLocation($course_id, $file_name);

    if ( file_exists($file_location) ) {
      $modified_time = filemtime($file_location);

      if ( $modified_time && ( (time() - $modified_time) <= 60 * 60 ) ) {
        return file_get_contents($file_location);
      }
    }

    return false;
  }

  public function createOrUpdateFile($course_id, $file_name, $data) {
    $file_location = $this->buildFileLocation($course_id, $file_name);

    // XXX This could fail silently
    $handle = fopen($file_location, 'w');
    fwrite($handle, $data);
    fclose($handle);
  }

}
