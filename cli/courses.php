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

$learningLockerDb =  new EmmaDashboardMongoDb(
  EDB_MDB_HOST,
  EDB_MDB_PORT,
  EDB_MDB_DATABASE,
  EDB_MDB_USERNAME,
  EDB_MDB_PASSWORD,
  EDB_LRS_ID
);

$query = array(
  'statement.verb.id' => EmmaDashboardXapiHelpers::getCreateUri(),
  'statement.object.definition.type' => EmmaDashboardXapiHelpers::getCourseSchemaUri()
);

$courses = array();

$cursor = $learningLockerDb->fetchData($query);

echo 'Courses fetched from database, crunching ...' . PHP_EOL;

foreach( $cursor as $document ) {
  $courseId = EmmaDashboardCliHelpers::extractCourseIdFromUrl( $document['statement']['object']['id'] );

  // Ignore any courses with no identifiers
  if ( $courseId !== $document['statement']['object']['id'] ) {
    $courseTitle = EmmaDashboardCliHelpers::extractFirstValue( $document['statement']['object']['definition']['name'] );

    if ( !array_key_exists( $courseId, $courses ) ) {
      $courses[$courseId] = array(
        'id' => $courseId,
        'url' => $document['statement']['object']['id'],
        'title' => array( $courseTitle ),
        'start' => '',
        'end' => '',
        'teacher_name' => $document['statement']['actor']['name'],
        'teacher_email' => EmmaDashboardCliHelpers::mboxIntoEmail( $document['statement']['actor']['mbox'] )
      );
    } else {
      if ( !in_array( $courseTitle, $courses[ $courseId ]['title'] ) ) {
        $courses[$courseId]['title'][] = $courseTitle;
      }
    }
  }
}

$storageHelper = new EmmaDashboardStorage();

$serviceCaller = new EmmaDashboardServiceCaller( EDB_PLATFORM_URL, EDB_SERVICE_USERNAME, EDB_SERVICE_PASSWORD, $storageHelper );

echo 'Fetching data from Platform API, crunching ... Please be patient.' . PHP_EOL;

foreach ( $courses as $course ) {
  try {
    $structure_response = $serviceCaller->getCourseStructure( $course['id'] );
    $structure = json_decode( $structure_response );

    $courses[ $course['id'] ]['start'] = $structure->startDate;
    $courses[ $course['id'] ]['end'] = $structure->endDate;

  } catch( Exception $e ) {
    error_log( 'Could not load structure for course ' . $course['id'] );
    error_log( print_r( $e->getMessage(), true ) );
  }
}

$fileName = 'courses.csv';

$fileHandle = fopen( __DIR__ . '/' . $fileName, 'w+' );

fputcsv( $fileHandle, array( 'id', 'url', 'title', 'start', 'end', 'teacher_name', 'teacher_email' ) );

foreach ( $courses as $course ) {
  $course['title'] = implode( ' || ', $course['title'] );
  fputcsv( $fileHandle, array_values( $course ) );
}

fclose( $fileHandle );

echo 'All done. Please check results in file ' . $fileName . PHP_EOL;
