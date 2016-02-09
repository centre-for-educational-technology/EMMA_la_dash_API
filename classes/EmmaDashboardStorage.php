<?php

/**
 * The storage catalog structure is quite simple and create a subcatalog for
 * each new course. This could potentially lead to issues if some limits of the
 * file system are exceeded.
 */

/**
 * Exception class to indicate storage exceptions
 */
 class EmmaDashboardStorageException extends Exception {
 }

/**
 * Storage helper class.
 * Deals with temporaty storages and validation of information still being fresh
 * enough (checks for last modification time and provided timeout value).
 */
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
      if ( !is_writable($this->storage) ) {
        throw new EmmaDashboardStorageException('Storage location is not writable, please contact Administrator');
      }

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
        if ( !is_readable($file_location) ) {
          throw new EmmaDashboardStorageException('Cache file is not readable, please contact Administrator');
        }
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

    if ( !is_writable($this->storage) ) {
      throw new EmmaDashboardStorageException('Storage location is not writable, please contact Administrator');
    }

    $handle = fopen($file_location, 'w');
    fwrite($handle, $data);
    fclose($handle);
  }

}
