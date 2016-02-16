<?php

class EmmaDashboardServiceException extends Exception {
}

class EmmaDashboardServicePermissionException extends Exception {
}

class EmmaDashboardServiceCaller {
  const CACHE_TIMEOUT = 300;

  /**
   * Constructs the service caller object.
   * Storage helper is ised as a pointer to an original instance.
   * @param string $baseUrl       Service base URL
   * @param string $username      Service username
   * @param string $password      Service password
   * @param object $storageHelper Storage helper to use for cache functionality
   */
  public function __construct ($baseUrl, $username, $password, &$storageHelper) {
    $this->base = $baseUrl;
    $this->username = $username;
    $this->password = $password;
    $this->storageHelper = $storageHelper;
  }

  /**
   * Examine response and raise error if one given. Response should be JSON
   * encoded string with errors provided.
   * @param string $json Response JSON
   */
  private function examineResponse($json) {
    $decoded = json_decode($json);

    if ( isset($decoded->error) ) {
      error_log('Web Service Responded With Error: ' . $decoded->error->message . ' : ' . $decoded->error->code);
      throw new EmmaDashboardServiceException('Service Error, please contact Administrator.');
    }
  }

  /**
   * Get course structure from the service. Will throw an Exception if fails.
   * @param  integer $id Course identifier
   * @return string      JSON string with course structure
   */
  public function getCourseStructure ($id) {
    return $this->makeCall($id, $this->base . 'api/public/courses/' . $id . '/structure', true);
  }

  /**
   * Get course students from the service. Will thow an Exception if fails.
   * @param  integer $id Course identifier
   * @return string      JSON string with an array of students
   */
  public function getCourseStudents ($id) {
    return $this->makeCall($id, $this->base . 'api/public/courses/' . $id . '/students', true);
  }

  /**
   * Checks if currently loged in user is a teacher of the course.
   * @param  integer $id Course identifier
   * @return string      JSON string with status of true or false
   */
  public function getCheckTeacher($id) {
    return $this->makeCall($id, $this->base . 'api/public/check_teacher/' . $id, false);
  }

  /**
   * Checks if currently logged in user is a student of the course.
   * @param  integer $id Course identifier
   * @return string     JSON string with status of true of falde
   */
  public function getCheckStudent($id) {
    return $this->makeCall($id, $this->base . 'api/public/check_student/' . $id, false);
  }

  /**
   * Returns email of current logged in user or fails
   * @return string     JSON string with email of the user or error code and message
   */
  public function getCurrentUserEmail() {
    return $this->makeCall($id, $this->base . 'api/public/current_user_email/', false);
  }

  /**
   * Make a call to service and return the data.
   * There is a possibility to use cache. This will fetch data fom cache if
   * availablt and not yet outdated.
   * @param  integer $id        Course identifier
   * @param  string  $url       URL to be called
   * @param  bool    $use_cache Should cache be used or not
   * @return string             Response data
   */
  private function makeCall ($id, $url, $use_cache = false) {
    $cache_file_name = null;

    // See if cache is allowed
    if ( $use_cache ) {
      $split = explode('/', $url);
      $cache_file_name = 'course_' . $split[sizeof($split) - 1] . '.json';
      // Try to load from storage
      $cached_response = $this->storageHelper->readFileIfNotOutdated($id, $cache_file_name, self::CACHE_TIMEOUT);
      if ( $cached_response ) {
        return $cached_response;
      }
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL => $url,
      CURLOPT_USERPWD => $this->username . ':' . $this->password
    ));

    $result = curl_exec($curl);

    if ( curl_errno($curl) ) {
      error_log('Web Service Error: ' . curl_error($curl));
      throw new EmmaDashboardServiceException('Service Error, please contact Administrator.');
    }

    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);

    curl_close($curl);

    if ( $http_code !== 200 || strtolower($content_type) !== 'application/json' ) {
      error_log('Web Service Responded With Error: ' . $http_code . ' and content-type ' . $content_type );
      throw new EmmaDashboardServiceException('Service Error, please contact Administrator.');
    }

    $this->examineResponse($result);

    // Cache if allowed
    if ( $use_cache ) {
      $this->storageHelper->createOrUpdateFile($id, $cache_file_name, $result);
    }

    return $result;
  }

  /**
   * Extracts single lesson from a course data object.
   * @param  integer $lesson_id Lesson identifier
   * @param  object  $course    Course object with data
   * @return object             Extracted lesson object
   */
  public static function extractLessonFromCourse($lesson_id, $course) {
    $current_lesson = array_filter($course->lessons, function ($lesson) use ($lesson_id) {
      return $lesson->id == $lesson_id;
    });

    return array_pop($current_lesson);
  }

  /**
   * Extracts single unit from a lesson data object.
   * @param  integer $unit_id Unit identifier
   * @param  object  $lesson  Lesson object with data
   * @return object           Extracted unut object
   */
  public static function extractUnitFromLesson($unit_id, $lesson) {
    $current_unit = array_filter($lesson->units, function ($unit) use ($unit_id) {
      return $unit->id == $unit_id;
    });

    return array_pop($current_unit);
  }

  /**
   * Checks if currently logged in user is a teacher of the course.
   * Throws an Excepton if not teacher.
   * @param  integer $id Course identifier
   * @return void
   */
  public function applyTeacherCheck($id) {
    if ( EDB_ENABLE_PROTECTION ) {
      $teacher_check_response = $this->getCheckTeacher($id);
      $teacher_check = json_decode($teacher_check_response);

      if ( true !== $teacher_check->status ) {
        throw new EmmaDashboardServicePermissionException('You are not a teacher of this course.');
      }
    }
  }

  /**
   * Checks if currently logged in user is a student of the course.
   * Throws an Exception if not student.
   * @param  integer $id Course identifier.
   * @return void
   */
  public function applyStudentCheck($id) {
    if ( EDB_ENABLE_PROTECTION ) {
      $student_check_response = $this->getCheckStudent($id);
      $student_check = json_decode($student_check_response);

      if ( true !== $student_check->status ) {
        throw new EmmaDashboardServicePermissionException('You are not a student of this course.');
      }
    }
  }

  /**
   * Checks if currently logged in user is a teacher or a student of the course.
   * @param  integer $id Course identifier.
   * @return void
   */
  public function applyTeacherOrStudentCheck($id) {
    if ( EDB_ENABLE_PROTECTION ) {
      $teacher_check_response = $this->getCheckTeacher($id);
      $teacher_check = json_decode($teacher_check_response);

      $student_check_response = $this->getCheckStudent($id);
      $student_check = json_decode($student_check_response);

      if ( true !== $teacher_check->status || true !== $student_check->status ) {
        throw new EmmaDashboardServicePermissionException('You are not a teacher or student of this course.');
      }
    }
  }
}
