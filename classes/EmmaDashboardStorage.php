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

  /**
   * Creates a file location on the disk
   * @param  integer $course_id Course identifier
   * @param  string  $file_name File name to be used
   * @return string             Absolute path to the file
   */
  public function buildFileLocation($course_id, $file_name) {
    if ( !file_exists($this->storage . '/' . $course_id) ) {
      // XXX This could fail silently
      mkdir($this->storage . '/' . $course_id);
    }

    return $this->storage . '/' . $course_id . '/' . $file_name;
  }

  /**
   * Check file modification time and determine if that is still fresh.
   * Read the file and return the contents if it is fresh enough.
   * Returns false otherwise.
   * @param  integer $course_id      Course identifier
   * @param  string  $file_name      File name
   * @param  integer $timeout_period Timeout period (optional, has default)
   * @return mixed                   Either string with file data or false
   */
  public function readFileIfNotOutdated($course_id, $file_name, $timeout_period = 3600) {
    $file_location = $this->buildFileLocation($course_id, $file_name);

    if ( file_exists($file_location) ) {
      $modified_time = filemtime($file_location);

      if ( $modified_time && ( (time() - $modified_time) <= $timeout_period ) ) {
        return file_get_contents($file_location);
      }
    }

    return false;
  }

  /**
   * Create new file or overwrite an existing one.
   * @param  integer $course_id Course identifier
   * @param  string  $file_name File name
   * @param  string  $data      Data to be written (JSON encoded mostly)
   */
  public function createOrUpdateFile($course_id, $file_name, $data) {
    $file_location = $this->buildFileLocation($course_id, $file_name);

    // XXX This could fail silently
    $handle = fopen($file_location, 'w');
    fwrite($handle, $data);
    fclose($handle);
  }

}
