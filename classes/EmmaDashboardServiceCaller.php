<?php

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
      throw new Exception('Service Error, please contact Administrator.');
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
      throw new Exception('Service Error, please contact Administrator.');
    }

    curl_close($curl);

    $this->examineResponse($result);

    return $result;
  }
}
