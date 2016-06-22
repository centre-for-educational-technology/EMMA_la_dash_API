<?php

if ( !( php_sapi_name() === 'cli' ) ) {
  exit( 'Not a CLI mode.' . PHP_EOL );
}

// Fill with suitable course identifiers
$courseIds = array();

if ( !( isset( $courseIds ) && is_array( $courseIds ) && count( $courseIds) >= 1 ) ) {
  exit( '!!!ERROR!!! No course identifiers present!' . PHP_EOL );
}

require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../classes/EmmaDashboardXapiHelpers.php';
require_once __DIR__ . '/../classes/EmmaDashboardUriBuilder.php';
require_once __DIR__ . '/../classes/EmmaDashboardMongoDb.php';
require_once __DIR__ . '/../classes/EmmaDashboardServiceCaller.php';
require_once __DIR__ . '/../classes/EmmaDashboardStorage.php';
require_once __DIR__ . '/classes/EmmaDashboardCliHelpers.php';

$learningLockerDb =  new EmmaDashboardMongoDb(
EDB_MDB_HOST,
EDB_MDB_PORT,
EDB_MDB_DATABASE,
EDB_MDB_USERNAME,
EDB_MDB_PASSWORD,
EDB_LRS_ID
);
$storageHelper = new EmmaDashboardStorage();
$serviceCaller = new EmmaDashboardServiceCaller( EDB_PLATFORM_URL, EDB_SERVICE_USERNAME, EDB_SERVICE_PASSWORD, $storageHelper );
$uriBuilder =  new EmmaDashboardUriBuilder(EDB_BUILDER_URL);
$xapiHelpers = new EmmaDashboardXapiHelpers();



$fileName = 'course_lesson_views_count_and_duration.csv';

$fileHandle = fopen( __DIR__ . '/' . $fileName, 'w+' );

fputcsv( $fileHandle, array( 'course id', 'lesson id', 'lesson title', 'number of interactions', 'total time spent (min)', 'total idle time spent (min)', 'average time spent (min)' ) );

foreach ( $courseIds as $courseId ) {
  echo 'Working on course ' . $courseId . ', crunching ...' . PHP_EOL;

  try {
    $structureResponse = $serviceCaller->getCourseStructure($courseId);
    $structure = json_decode($structureResponse);

    $studentsResponse = $serviceCaller->getCourseStudents($courseId);
    $students = json_decode($studentsResponse);
    $studentsCount = count($students);
  } catch ( Exception $e ) {
    error_log( 'Service error for course  ' . $courseId );
    error_log( print_r( $e->getMessage(), true ) );
  }

  if ( !( isset($structure->lessons) && is_array( $structure->lessons ) && count( $structure->lessons ) >= 1 && isset( $students ) && is_array( $students ) && count( $students ) >= 1 ) ) {
    echo '!!!Warning!!! course ' . $courseId . ' has no lessons or students, skipping ...' . PHP_EOL;
    continue;
  }

  echo 'Fetched structure and students, crunching ...' . PHP_EOL;

  $lessons = array();

  foreach ( $structure->lessons as $lesson ) {
    $lessons[$lesson->id]['title'] = $lesson->title;
    $query = array(
      'statement.verb.id' => $xapiHelpers->getVisitedUri(),
      'statement.object.id' => array(
        '$in' => array(
          $uriBuilder->buildLessonUri($courseId, $lesson->id),
        ),
      ),
    );

    if ( $lesson->units ) {
      foreach( $lesson->units as $unit) {
        $query['statement.object.id']['$in'][] = $uriBuilder->buildUnitUri($unit->id);
      }

      if ( $unit->assignments ) {
        foreach( $unit->assignments as $assignment ) {
          $query['statement.object.id']['$in'][] = $uriBuilder->buildAssignmentUri($assignment->id);
        }
      }
    }

    $pipeline = array(
      array(
        '$match' => $query,
      ),
      array(
        '$group' => array(
          '_id' => 'null',
          'count' => array(
            '$sum' => 1,
          ),
          'times' => array(
            '$push' => '$statement.object.definition.extensions.http://id&46;tincanapi&46;com/extension/duration',
          ),
          'idleTimes' => array(
            '$push' => '$statement.object.definition.extensions.http://id&46;tincanapi&46;com/extension/idleDuration',
          ),
        ),
      ),
    );

    $aggregate = $learningLockerDb->fetchAggregate($pipeline);

    if ( $aggregate['ok'] == 1 && isset( $aggregate['result'][0] ) ) {
      $timesSum = array_sum( $aggregate['result'][0]['times'] );
      $idleTimesSum = array_sum( $aggregate['result'][0]['idleTimes'] );

      // Logic is: Sum of Duration minus Sum of Idle Durations divided by 1000 to get seconds, then bu 60 to get minutes and then by Students count (active ones)
      $averageTime =  ( $timesSum - $idleTimesSum ) / ( 60 * 1000 * $studentsCount );

      $data = array(
        $courseId,
        $lesson->id,
        $lesson->title,
        $aggregate['result'][0]['count'],
        EmmaDashboardCliHelpers::roundMinutes( EmmaDashboardCliHelpers::milliSecondsToMinutes( $timesSum ) ),
        EmmaDashboardCliHelpers::roundMinutes( EmmaDashboardCliHelpers::milliSecondsToMinutes( $idleTimesSum ) ),
        EmmaDashboardCliHelpers::roundMinutes( $averageTime ),
      );

      fputcsv( $fileHandle, $data );
    }
  }
}

fclose( $fileHandle );

echo 'All done. Please check results in file ' . $fileName . PHP_EOL;
