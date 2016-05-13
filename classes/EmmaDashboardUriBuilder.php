<?php

class EmmaDashboardUriBuilder {
  public function __construct($uriBase) {
    $this->base = $uriBase;
  }

  public function buildCourseUri($id) {
    return $this->base . 'course.php?cor=' . $id;
  }

  public function buildLessonUri($courseId, $id) {
    return $this->base . 'lesson.php?cor=' . $courseId . '&lez=' . $id;
  }

  public function buildUnitUri($id) {
    return $this->base . 'unit.php?sli=' . $id;
  }

  public function buildAssignmentUri($id) {
    return $this->base . 'assignment.php?sli=' . $id;
  }
}
