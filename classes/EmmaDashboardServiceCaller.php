<?php

class EmmaDashboardServiceException extends Exception {
}

class EmmaDashboardServiceCaller {
  public function __construct ($baseUrl, $username, $password) {
    $this->base = $baseUrl;
    $this->username = $username;
    $this->password = $password;
  }

  private function examineResponse($json) {
    $decoded = json_decode($json);

    if ( isset($decoded->error) ) {
      error_log('Web Service Responded With Error: ' . $decoded->error->message . ' : ' . $decoded->error->code);
      throw new EmmaDashboardServiceException('Service Error, please contact Administrator.');
    }
  }

  public function getCourseStructure ($id) {
    return $this->makeCall($this->base . 'api/public/courses/' . $id . '/structure');
  }

  public function getCourseStudents ($id) {
    return $this->makeCall($this->base . 'api/public/courses/' . $id . '/students');
  }

  private function makeCall ($url) {
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

    return $result;
  }

  public static function extractLessonFromCourse($lesson_id, $course) {
    $current_lesson = array_filter($course->lessons, function ($lesson) use ($lesson_id) {
      return $lesson->id == $lesson_id;
    });

    return array_pop($current_lesson);
  }

  public static function extractUnitFromLesson($unit_id, $lesson) {
    $current_unit = array_filter($lesson->units, function ($unit) use ($unit_id) {
      return $unit->id == $unit_id;
    });

    return array_pop($current_unit);
  }
}
