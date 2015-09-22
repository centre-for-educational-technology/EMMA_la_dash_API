<?php

class EmmaDashboardServiceCaller {
  public function __construct ($baseUrl, $username, $password) {
    $this->base = $baseUrl;
    $this->username = $username;
    $this->password = $password;
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

    curl_close($curl);

    return $result;
  }
}
