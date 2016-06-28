<?php

if ( !( php_sapi_name() === 'cli' ) ) {
  exit( 'Not a CLI mode.' . PHP_EOL );
}

// Thix should be filled with corresponding date
// Format year-month-day
$fromTimeString = '2015-10-01';

echo 'Calculating weeks from ' . $fromTimeString . ' ... crunching' . PHP_EOL;

$weekSeconds = 604800;


$fromTime = strtotime( $fromTimeString );
$toTime = time();


$timeMilestone = $fromTime;

$weeks = array();

while ( $timeMilestone < $toTime ) {
  $weeks[] = array(
    'since' => $timeMilestone,
    'until' => $timeMilestone + $weekSeconds - 1
  );
  $timeMilestone += $weekSeconds;
}

echo 'Determined ' . count( $weeks ) . ' weeks.' . PHP_EOL;

require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../classes/EmmaDashboardXapiHelpers.php';
require_once __DIR__ . '/../classes/EmmaDashboardMongoDb.php';
require_once __DIR__ . '/classes/EmmaDashboardCliHelpers.php';

$learningLockerDb =  new EmmaDashboardMongoDb(
EDB_MDB_HOST,
EDB_MDB_PORT,
EDB_MDB_DATABASE,
EDB_MDB_USERNAME,
EDB_MDB_PASSWORD,
EDB_LRS_ID
);
$xapiHelpers = new EmmaDashboardXapiHelpers();

$fileName = 'blog_and_comment_general.csv';

$fileHandle = fopen( __DIR__ . '/' . $fileName, 'w+' );

fputcsv( $fileHandle, array( 'Week number', 'Week start', 'Week end', 'Blog Posts Created', 'Comments Created (Blog)' , 'Comments Created (Comment)', 'Comment Created (Other)' ) );


foreach ( $weeks as $index => $week ) {
  echo 'Working on week ' . ( $index + 1 ) . ' ... processing' . PHP_EOL;

  $blogPostQuery = array(
    'statement.verb.id' => $xapiHelpers::getCommentedUri(),
    'statement.object.definition.type' => $xapiHelpers::getBlogActivityUri(),
    'statement.timestamp' => array(
      '$gte' => date(DATE_ATOM, $week['since']),
      '$lte' => date(DATE_ATOM, $week['until']),
    ),
  );

  $blogPostCount = $learningLockerDb->fetchCount( $blogPostQuery );

  $respondedBlogQuery = array(
    'statement.verb.id' => $xapiHelpers::getRespondedUri(),
    'statement.object.definition.type' => $xapiHelpers::getBlogActivityUri(),
    'statement.timestamp' => array(
      '$gte' => date(DATE_ATOM, $week['since']),
      '$lte' => date(DATE_ATOM, $week['until']),
    ),
  );

  $respondedBlogCount = $learningLockerDb->fetchCount( $respondedBlogQuery );

  $respondedCommentQuery = array(
    'statement.verb.id' => $xapiHelpers::getRespondedUri(),
    'statement.object.definition.type' => $xapiHelpers::getCommentActivityUri(),
    'statement.timestamp' => array(
      '$gte' => date(DATE_ATOM, $week['since']),
      '$lte' => date(DATE_ATOM, $week['until']),
    ),
  );

  $respondedCommentCount = $learningLockerDb->fetchCount( $respondedCommentQuery );

  $respondedOtherQuery = array(
    'statement.verb.id' => $xapiHelpers::getRespondedUri(),
    'statement.object.definition.type' => array(
      '$nin' => array(
        $xapiHelpers::getBlogActivityUri(),
        $xapiHelpers::getCommentActivityUri(),
      ),
    ),
    'statement.timestamp' => array(
      '$gte' => date(DATE_ATOM, $week['since']),
      '$lte' => date(DATE_ATOM, $week['until']),
    ),
  );

  $respondedOtherCount = $learningLockerDb->fetchCount( $respondedOtherQuery );

  $data = array(
    'Week ' . ( $index + 1),
    date( 'c', $week['since'] ),
    date( 'c', $week['until'] ),
    $blogPostCount,
    $respondedBlogCount,
    $respondedCommentCount,
    $respondedOtherCount,
  );

  fputcsv( $fileHandle, $data );
}

fclose( $fileHandle );
echo 'All done. Please check results in file ' . $fileName . PHP_EOL;
