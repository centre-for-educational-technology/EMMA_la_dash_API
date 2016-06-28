<?php

if ( !( php_sapi_name() === 'cli' ) ) {
  exit( 'Not a CLI mode.' . PHP_EOL );
}

// Fill with suitable course identifiers
// OR
// Pass comma separated list as an argument to the script
$courseIds = array();

if ( count( $argv ) > 1 ) {
  $courseIds = explode( ',', $argv[1] );
} else {
  if ( count( $courseIds ) === 0 ) {
    echo '!!!WARNING!!! Course identifiers should be provided as a comma separated list within an argument!' . PHP_EOL;
  }
}

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



$fileName = 'course_blogs_comments_replies.csv';

$fileHandle = fopen( __DIR__ . '/' . $fileName, 'w+' );

fputcsv( $fileHandle, array( 'Course id', 'Week number', 'Week start', 'Week end', 'Blog Posts Created', 'Comments Created (Comment)', 'Comment Created (Other)' ) );

$weekSeconds = 604800;


foreach ( $courseIds as $courseId ) {
  echo 'Working on course ' . $courseId . ', crunching ...' . PHP_EOL;

  try {
    $structureResponse = $serviceCaller->getCourseStructure($courseId);
    $structure = json_decode($structureResponse);
  } catch ( Exception $e ) {
    error_log( 'Service error for course  ' . $courseId );
    error_log( print_r( $e->getMessage(), true ) );
  }

  echo 'Fetched structure, crunching ...' . PHP_EOL;

  if ( !( $structure->startDate && $structure->endDate ) ) {
    echo '!!!WARNING!!! Course ' . $courseId . ' is missing startDate and/or endDate, skipping.' . PHP_EOL;
    continue;
  }

  $fromTime = strtotime( $structure->startDate );
  $toTime = strtotime( $structure->endDate );

  if ( $toTime > time() ) {
    $toTime = time();
  }

  $timeMilestone = $fromTime;

  $weeks = array();

  while ( $timeMilestone < $toTime ) {
    $weeks[] = array(
      'since' => $timeMilestone,
      'until' => $timeMilestone + $weekSeconds - 1
    );
    $timeMilestone += $weekSeconds;
  }

  echo 'Determined ' . count( $weeks ) . ' weeks ... crunching' . PHP_EOL;

  foreach ( $weeks as $index => $week ) {
    $blogPostQuery = array(
      'statement.verb.id' => $xapiHelpers::getCommentedUri(),
      'statement.object.definition.type' => $xapiHelpers::getBlogActivityUri(),
      'statement.timestamp' => array(
        '$gte' => date(DATE_ATOM, $week['since']),
        '$lte' => date(DATE_ATOM, $week['until']),
      ),
      'statement.context.contextActivities.grouping.id' => $uriBuilder->buildCourseUri($courseId),
    );

    $blogPostCount = $learningLockerDb->fetchCount( $blogPostQuery );

    $respondedCommentQuery = array(
      'statement.verb.id' => $xapiHelpers::getRespondedUri(),
      'statement.object.definition.type' => array(
        '$in' => array(
          $xapiHelpers::getCommentActivityUri(),
          $xapiHelpers::getCommentSchemaUri(),
        ),
      ),
      'statement.timestamp' => array(
        '$gte' => date(DATE_ATOM, $week['since']),
        '$lte' => date(DATE_ATOM, $week['until']),
      ),
      'statement.context.contextActivities.grouping.id' => $uriBuilder->buildCourseUri($courseId),
    );

    $respondedCommentCount = $learningLockerDb->fetchCount( $respondedCommentQuery );

    $respondedOtherQuery = array(
      'statement.verb.id' => $xapiHelpers::getRespondedUri(),
      'statement.object.definition.type' => array(
        '$nin' => array(
          $xapiHelpers::getBlogActivityUri(),
          $xapiHelpers::getCommentActivityUri(),
          $xapiHelpers::getCommentSchemaUri(),
        ),
      ),
      'statement.timestamp' => array(
        '$gte' => date(DATE_ATOM, $week['since']),
        '$lte' => date(DATE_ATOM, $week['until']),
      ),
      'statement.context.contextActivities.grouping.id' => $uriBuilder->buildCourseUri($courseId),
    );

    $respondedOtherCount = $learningLockerDb->fetchCount( $respondedOtherQuery );

    $data = array(
      $courseId,
      'Week ' . ( $index + 1),
      date( 'c', $week['since'] ),
      date( 'c', $week['until'] ),
      $blogPostCount,
      $respondedCommentCount,
      $respondedOtherCount,
    );

    fputcsv( $fileHandle, $data );
  }
}

fclose( $fileHandle );

echo 'All done. Please check results in file ' . $fileName . PHP_EOL;
