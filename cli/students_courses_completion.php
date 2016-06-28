<?php

if ( !( php_sapi_name() === 'cli' ) ) {
  exit( "Not a CLI mode.\n" );
}

require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../classes/EmmaDashboardXapiHelpers.php';
require_once __DIR__ . '/../classes/EmmaDashboardMongoDb.php';
require_once __DIR__ . '/../classes/EmmaDashboardServiceCaller.php';
require_once __DIR__ . '/../classes/EmmaDashboardStorage.php';
require_once __DIR__ . '/classes/EmmaDashboardCliHelpers.php';
require_once __DIR__ . '/../classes/EmmaDashboardXapiHelpers.php';
require_once __DIR__ . '/../classes/EmmaDashboardUriBuilder.php';

$learningLockerDb =  new EmmaDashboardMongoDb(
    EDB_MDB_HOST,
    EDB_MDB_PORT,
    EDB_MDB_DATABASE,
    EDB_MDB_USERNAME,
    EDB_MDB_PASSWORD,
    EDB_LRS_ID
);

$fileName = 'student_courses_completion.csv';

$fileHandle = fopen( __DIR__ . '/' . $fileName, 'w+' );

fputcsv( $fileHandle, array( 'id', 'mbox', 'units_visited', 'units_total', 'assignments_visited', 'assignments_total', 'all_units_visited', 'success_in_assignments' ) );

$start_time = time();


$courses = array(115,143,142,118,144,128,146,140,138,156,148,124,149,153,114,165,139,90,161,80);



$storageHelper = new EmmaDashboardStorage();

$uriBuilder = new EmmaDashboardUriBuilder(EDB_BUILDER_URL);

$serviceCaller = new EmmaDashboardServiceCaller( EDB_PLATFORM_URL, EDB_SERVICE_USERNAME, EDB_SERVICE_PASSWORD, $storageHelper );


foreach ($courses as $course_id){

  try {
    echo 'Working with course ' .$course_id. PHP_EOL;
    
    //Get students for that course
    $students_response = $serviceCaller->getCourseStudents($course_id);
    $students = json_decode($students_response);

    $students = array_unique($students);


    //Get course structure
    $structure_response = $serviceCaller->getCourseStructure($course_id);
    $structure = json_decode($structure_response);


    
    $lessons = array();

    $units = array();

    $assignments = array();

    if ( isset($structure->lessons) ) {
      foreach ($structure->lessons as $lesson) {
        if ($lesson->status == 1){
          array_push($lessons, $uriBuilder->buildLessonUri($course_id, $lesson->id));
          if (isset($lesson->units)) {
            foreach ($lesson->units as $unit) {
              if ($unit->status == 1){
                array_push($units, $uriBuilder->buildUnitUri($unit->id));
                if (isset ($unit->assignments)) {
                  foreach ($unit->assignments as $assignment) {
                    if ($assignment->status == 1){
                      array_push($assignments, $uriBuilder->buildAssignmentUri($assignment->id));
                    }
                  }

                }
              }
            }
          }
        }
      }
    }

    $units_total = count($units);

    $assignments_total = count($assignments);

    $xapiHelpers = new EmmaDashboardXapiHelpers();


    foreach ($students as $mbox){
      // Units visited by student
      $query_units_visited = array(
          'statement.verb.id' => $xapiHelpers->getVisitedUri(),
          'statement.object.id' => array(
              '$in' => $units,
          ),
          'statement.actor.mbox' => 'mailto:' . $mbox,
      );

      $pipeline = array(
          array(
              '$match' => $query_units_visited,
          ),
          array(
              '$group' => array(
                  '_id' => '$statement.object.id',
              ),
          ),
          array(
              '$group' => array(
                  '_id' => 1,
                  'count' => array(
                      '$sum' => 1,
                  ),
              ),
          ),
      );

      $aggregate = $learningLockerDb->fetchAggregate($pipeline);

      if($aggregate['ok'] == 1 && isset($aggregate['result'][0])){
        $units_visited = $aggregate['result'][0]['count'];
      }else{
        $units_visited = 0;
      }



      // Assignements visited by student
      $query_assignments_visited = array(
          'statement.verb.id' => $xapiHelpers->getVisitedUri(),
          'statement.object.id' => array(
              '$in' => $assignments,
          ),
          'statement.actor.mbox' => 'mailto:' . $mbox,
      );

      $pipeline = array(
          array(
              '$match' => $query_assignments_visited,
          ),
          array(
              '$group' => array(
                  '_id' => '$statement.object.id',
              ),
          ),
          array(
              '$group' => array(
                  '_id' => 1,
                  'count' => array(
                      '$sum' => 1,
                  ),
              ),
          ),
      );

      $aggregate = $learningLockerDb->fetchAggregate($pipeline);

      if($aggregate['ok'] == 1 && isset($aggregate['result'][0])){
        $assignments_visited = $aggregate['result'][0]['count'];
      }else{
        $assignments_visited = 0;
      }

      $all_units_visited = 'False';
      $success_in_assignments = 'False';


      if($units_visited == $units_total){
        $all_units_visited = 'True';
      }

      if($assignments_total>0){
        if(($assignments_visited * 100 / $assignments_total) > 50){
          $success_in_assignments = 'True';
        }
      }



      fputcsv( $fileHandle, array($course_id, $mbox, $units_visited, $units_total, $assignments_visited, $assignments_total, $all_units_visited, $success_in_assignments) );
    }


  } catch( Exception $e ) {
    error_log( 'There has been a problem with the course ' . $course_id );
    error_log( print_r( $e->getMessage(), true ) );
  }



}


fclose( $fileHandle );

$end_time = time();

echo 'All done. Please check results in file ' . $fileName . ' Time taken: '. round(($end_time-$start_time)/60, 2) .' mins'. PHP_EOL;


