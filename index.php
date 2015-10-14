<?php

require_once __DIR__ . '/config.php';

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/classes/EmmaDashboardUriBuilder.php';
require_once __DIR__ . '/classes/EmmaDashboardXapiHelpers.php';
require_once __DIR__ . '/classes/EmmaDashboardMongoDb.php';
require_once __DIR__ . '/classes/EmmaDashboardServiceCaller.php';
require_once __DIR__ . '/classes/EmmaDashboardStorage.php';


$klein = new \Klein\Klein();
$request = \Klein\Request::createFromGlobals();

// Only replace in case of being used from subdirectory
if ( EDB_APP_PATH !== '/' ) {
  $uri = $request->server()->get('REQUEST_URI');
  $request->server()->set('REQUEST_URI', substr($uri, strlen(EDB_APP_PATH)));
}

// Instanciate connections and helpers
$klein->respond(function ($request, $response, $service, $app) use ($klein) {
  // Error handler
  $klein->onError(function($klein, $error_msg) {
    $klein->response()->code(500);
    // XXX Exposing internal error information might be a bad idea
    $klein->response()->json(array(
      'message' => $error_msg,
    ));
  });

  $app->register('learningLockerDb', function () {
    return new EmmaDashboardMongoDb(
      EDB_MDB_HOST,
      EDB_MDB_PORT,
      EDB_MDB_DATABASE,
      EDB_MDB_USERNAME,
      EDB_MDB_PASSWORD,
      EDB_LRS_ID
    );
  });
  $app->register('uriBuilder', function () {
    return new EmmaDashboardUriBuilder(EDB_PLATFORM_URL);
  });
  $app->register('xapiHelpers', function () {
    return new EmmaDashboardXapiHelpers();
  });
  $app->register('serviceCaller', function () {
    return new EmmaDashboardServiceCaller(EDB_PLATFORM_URL, EDB_SERVICE_USERNAME, EDB_SERVICE_PASSWORD);
  });
  $app->register('storageHelper', function () {
    return new EmmaDashboardStorage();
  });
});

// Handle CORS filter
if ( EDB_ENABLE_CORS ) {
  /**
   * Add CORS filter which allows any origin
   */
  $klein->respond(function ($request, $response) {
    // Allow any origin and only GET and OPTIONS requests
    $response->header('Access-Control-Allow-Origin', '*');
    $response->header('Access-Control-Allow-Methods', 'GET', 'OPTIONS');
  });
}

// Course participants endpoint
$klein->respond('/course/[i:id]/participants', function ($request, $response, $service, $app) {
  $course_id = $request->param('id');

  $course_title = '';
  $dates_and_counts = array();
  $students = array();

  // TODO Data for SINCE and UNTIL is missing
  // Need to make sure that only sttements from some time span are used
  $query = array(
    'statement.verb.id' => array(
      '$in' => array(
        $app->xapiHelpers->getJoinUri(),
        $app->xapiHelpers->getLeaveUri()
      )
    ),
    'statement.object.id' => $app->uriBuilder->buildCourseUri($course_id)
  );

  $cursor = $app->learningLockerDb->fetchData($query);

  foreach( $cursor as $document ) {
    $timestamp_date = $app->learningLockerDb->formatTimestampDate($document['statement']['timestamp']);
    $verb_id = $document['statement']['verb']['id'];

    if ( !array_key_exists($timestamp_date, $dates_and_counts) ) {
      $dates_and_counts[$timestamp_date] = array(
        'join' => 0,
        'leave' => 0
      );
    }

    $email = $document['statement']['actor']['mbox'];
    $email = preg_replace('/^' . preg_quote('mailto:', '/') . '/', '', $email);
    $nameSplit = preg_split('/ /', $document['statement']['actor']['name'], 2);

    if ( !array_key_exists($email, $students) ) {
      $students[$email] = array(
        'firstName' => $nameSplit[0],
        'lastName' => count($nameSplit) > 1 ? $nameSplit[1] : '',
        'email' => $email,
        'join' => 0,
        'leave' => 0
      );
    }

    if ( $document['statement']['verb']['id'] === $app->xapiHelpers->getJoinUri() ) {
      $dates_and_counts[$timestamp_date]['join'] += 1;
      $students[$email]['join'] += 1;
    } else if ( $document['statement']['verb']['id'] === $app->xapiHelpers->getLeaveUri() ) {
      $dates_and_counts[$timestamp_date]['leave'] += 1;
      $students[$email]['leave'] += 1;
    }

    // XXX This probably is not the best solution
    // TODO Consider loading the Course Title from the Web Service
    if ( !$course_title ) {
      $course_title = array_values($document['statement']['object']['definition']['name']);
      $course_title = $course_title[0];
    }
  }

  $response->json(array(
    'id' => $course_id,
    'title' => $course_title,
    'dates' => array_keys($dates_and_counts),
    'join' => array_map(function ($counts) { return $counts['join']; }, array_values($dates_and_counts)),
    'leave' => array_map(function($counts) { return $counts['leave']; }, array_values($dates_and_counts)),
    'students' => array_values($students),
  ));
});

// Course activity stream endpoint
$klein->respond('/course/[i:id]/activity_stream', function ($request, $response, $service, $app) {
  $course_id = $request->param('id');

  $dates_activities = array();

  // TODO Data for SINCE and UNTIL is missing
  // Need to make sure that only sttements from some time span are used
  // XXX Need to make sure that query is correct
  $query = array(
    '$or' => array(
      array(
        'statement.verb.id' => $app->xapiHelpers->getCreateUri(),
        'statement.object.definition.type' => $app->xapiHelpers->getCommentSchemaUri(),
        'statement.context.contextActivities.grouping' => array(
          '$elemMatch' => array(
            'id' => $app->uriBuilder->buildCourseUri($course_id),
          ),
        ),
      ),
      array(
        'statement.verb.id' => array(
          '$in' => array(
            $app->xapiHelpers->getJoinUri(),
            $app->xapiHelpers->getLeaveUri(),
          ),
        ),
        'statement.object.id' => $app->uriBuilder->buildCourseUri($course_id),
      ),
      array(
        'statement.verb.id' => array(
          '$in' => array(
            $app->xapiHelpers->getVisitedUri(),
            $app->xapiHelpers->getAnsweredUri(),
            $app->xapiHelpers->getRespondedUri(),
          )
        ),
        'statement.context.contextActivities.grouping' => array(
          '$elemMatch' => array(
            'id' => $app->uriBuilder->buildCourseUri($course_id),
          ),
        ),
      ),
    ),
  );

  $cursor = $app->learningLockerDb->fetchData($query);
  $cursor->sort(array(
    'statement.timestamp' => -1
  ));
  $cursor->limit(25);

  foreach($cursor as $document) {
    $timestamp_date = $app->learningLockerDb->formatTimestampDate($document['statement']['timestamp']);
    $timestamp_time = $app->learningLockerDb->formatTimestampTime($document['statement']['timestamp']);

    if ( !array_key_exists($timestamp_date, $dates_activities) ) {
      $dates_activities[$timestamp_date] = array(
        'date' => $timestamp_date,
        'activities' => array()
      );
    }

    $single_activity = array(
      'name' => $document['statement']['actor']['name'],
      'type' => $app->xapiHelpers->getActivityTypeFromVerbId($document['statement']['verb']['id']),
      'title' => $app->learningLockerDb->getFirstValueFromArray($document['statement']['object']['definition']['name']),
      'url' => $document['statement']['object']['id'],
      'time' => $timestamp_time,
    );

    if ($single_activity['type'] == $app->xapiHelpers->getDefaultUnknownActivityType()) {
      // This should not be possible, but will be kept just in case
      error_log('Unknown activity type, should not be present.');
      error_log(print_r($document, true));
    }

    $dates_activities[$timestamp_date]['activities'][] = $single_activity;
  }

  $response->json(array(
    'id' => $course_id,
    'data' => array_values($dates_activities)
  ));
});

// Course overview endpoint
$klein->respond('/course/[i:id]/overview', function ($request, $response, $service, $app) {
  $course_id = $request->param('id');

  $structure_response = $app->serviceCaller->getCourseStructure($course_id);
  $structure = json_decode($structure_response);

  $students_response = $app->serviceCaller->getCourseStudents($course_id);
  $students = json_decode($students_response);
  $students_count = count($students);

  $lessons = array();

  foreach ( $structure->lessons as $lesson ) {
    $lessons[$lesson->id]['title'] = $lesson->title;
    $query = array(
      'statement.verb.id' => $app->xapiHelpers->getVisitedUri(),
      'statement.object.id' => array(
        '$in' => array(
          $app->uriBuilder->buildLessonUri($course_id, $lesson->id),
        ),
      ),
    );

    if ( $lesson->units ) {
      foreach( $lesson->units as $unit) {
        $query['statement.object.id']['$in'][] = $app->uriBuilder->buildUnitUri($unit->id);
      }

      if ( $unit->assignments ) {
        foreach( $unit->assignments as $assignment ) {
          $query['statement.object.id']['$in'][] = $app->uriBuilder->buildAssignmentUri($assignment->id);
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
          /*'totalDuration' => array(
            '$sum' => '$statement.object.definition.extensions.http://id&46;tincanapi&46;com/extension/duration'
          ),*/
          /*'totalIdleDuration' => array(
            '$sum' => '$statement.object.definition.extensions.http://id&46;tincanapi&46;com/extension/idleDuration'
          ),*/
        ),
      ),
    );

    $aggregate = $app->learningLockerDb->fetchAggregate($pipeline);

    if ( $aggregate['ok'] == 1 && isset($aggregate['result'][0]) ) {
      $lessons[$lesson->id]['interactions'] = $aggregate['result'][0]['count'];
      // Logic is: Sum of Duration minus Sum of Idle Durations divided by 1000 to get seconds, then bu 60 to get minutes and then by Students count (active ones)
      $lessons[$lesson->id]['time_spent'] = intval( ( array_sum($aggregate['result'][0]['times']) - array_sum($aggregate['result'][0]['idleTimes']) ) / ( 60 * 1000 * $students_count ) );
    } else {
      $lessons[$lesson->id]['interactions'] = 0;
      $lessons[$lesson->id]['time_spent'] = 0;
    }
  }

  // Popular resources
  $popular_query = array(
    'statement.verb.id' => $app->xapiHelpers->getVisitedUri(),
    'statement.context.contextActivities.grouping' => array(
      '$elemMatch' => array(
        'id' => $app->uriBuilder->buildCourseUri($course_id),
      ),
    ),
  );

  $resources = $app->learningLockerDb->getPopularResourcedData($popular_query, 25);

  $response->json(array(
    'id' => $course_id,
    'title' => $structure->title,
    'lessons' => array_map(function($lesson) { return $lesson['title']; }, array_values($lessons)),
    'interactions' => array_map(function($lesson) { return $lesson['interactions']; }, array_values($lessons)),
    'time_spent' => array_map(function($lesson) { return $lesson['time_spent']; }, array_values($lessons)),
    'resources' => $resources,
  ));
});

// Course lessons endpoint
$klein->respond('/course/[i:id]/lessons', function ($request, $response, $service, $app) {
  $course_id = $request->param('id');

  $structure_response = $app->serviceCaller->getCourseStructure($course_id);
  $structure = json_decode($structure_response);

  $lessons_with_units = array();

  if ( isset($structure->lessons) ) {
    foreach( $structure->lessons as $lesson ) {
      $lessons_with_units[$lesson->id] = array(
        'id' => $lesson->id,
        'title' => $lesson->title,
        'units' => array(),
      );
      if ( isset($lesson->units) ) {
        foreach ( $lesson->units as $unit ) {
          $lessons_with_units[$lesson->id]['units'][] = array(
            'id' => $unit->id,
            'title' => $unit->title,
          );
        }
      }
    }
  }

  $response->json(array(
    'id' => $course_id,
    'title' => $structure->title,
    'lessons_with_units' => array_values($lessons_with_units),
  ));
});

// Course Lesson Unit endpoint
$klein->respond('/course/[i:course]/lesson/[i:lesson]/unit/[i:unit]', function ($request, $response, $service, $app) {
  $course_id = $request->param('course');
  $lesson_id = $request->param('lesson');
  $unit_id = $request->param('unit');

  $students_response = $app->serviceCaller->getCourseStudents($course_id);
  $students = json_decode($students_response);
  $students_count = count($students);
  $active_students_mailto = array_map(function ($email) { return 'mailto:' . $email; }, $students);

  $structure_response = $app->serviceCaller->getCourseStructure($course_id);
  $structure = json_decode($structure_response);

  $assignmments_uris = array();

  $current_lesson = $app->serviceCaller->extractLessonFromCourse($lesson_id, $structure);
  $current_unit = $app->serviceCaller->extractUnitFromLesson($unit_id, $current_lesson);

  foreach ( $current_unit->assignments as $assignment ) {
    $assignmments_uris[] = $app->uriBuilder->buildAssignmentUri($assignment->id);
  }

  $assignments_and_unit_uris = $assignmments_uris;
  $assignments_and_unit_uris[] = $app->uriBuilder->buildUnitUri($unit_id);

  // Unit visitors count
  $query = array(
    'statement.verb.id' => $app->xapiHelpers->getVisitedUri(),
    'statement.object.id' => array(
      '$in' => array(
        $app->uriBuilder->buildUnitUri($unit_id),
      ),
    ),
    'statement.actor.mbox' => array(
      '$in' => $active_students_mailto,
    ),
  );

  $unit_visitors_count = $app->learningLockerDb->getUniqueVisitorsCount($query);

  // Material visitors count
  $query_materials = array(
    'statement.verb.id' => $app->xapiHelpers->getVisitedUri(),
    'statement.context.contextActivities.parent' => array(
      '$elemMatch' => array(
        'id' => array(
          '$in' => $assignments_and_unit_uris,
        )
      ),
    ),
    'statement.object.definition.type' => array(
      '$ne' => $app->xapiHelpers->getLinkTypeUri(),
    ),
    'statement.actor.mbox' => array(
      '$in' => $active_students_mailto,
    ),
  );

  $material_visitors_count = $app->learningLockerDb->getUniqueVisitorsCount($query_materials);

  // Links visitors count
  $query_links = array(
    'statement.verb.id' => $app->xapiHelpers->getVisitedUri(),
    'statement.context.contextActivities.parent' => array(
      '$elemMatch' => array(
        'id' => array(
          '$in' => $assignments_and_unit_uris,
        )
      ),
    ),
    'statement.object.definition.type' => $app->xapiHelpers->getLinkTypeUri(),
    'statement.actor.mbox' => array(
      '$in' => $active_students_mailto,
    ),
  );

  $link_visitors_count = $app->learningLockerDb->getUniqueVisitorsCount($query_links);

  // Assignments views count
  $query_assignments_v = array(
    'statement.verb.id' => $app->xapiHelpers->getVisitedUri(),
    'statement.object.id' => array(
      '$in' => $assignmments_uris,
    ),
    'statement.actor.mbox' => array(
      '$in' => $active_students_mailto,
    ),
  );

  $assignment_views_count = $app->learningLockerDb->getUniqueVisitorsCount($query_assignments_v);

  // Assignment submission count, unique users and average score
  $query_assignments_s = array(
    'statement.verb.id' => $app->xapiHelpers->getAnsweredUri(),
    'statement.object.id' => array(
      '$in' => $assignmments_uris,
    ),
    'statement.actor.mbox' => array(
      '$in' => $active_students_mailto,
    ),
  );

  $pipeline_assignments_s = array(
    array(
      '$match' => $query_assignments_s,
    ),
    array(
      '$group' => array(
        '_id' => 'null',
        'count' => array(
          '$sum' => 1,
        ),
        'visitors' => array(
          '$addToSet' => '$statement.actor.mbox',
        ),
        /*'scores' => array(
          '$push' => '$statement.result.score.scaled', // XXX REMOVEME
        ),*/
        /*'sesses' => array(
          '$push' => '$statement.result.success', // XXX REMOVEME
        ),*/
        'averageScore' => array(
          '$avg' => '$statement.result.score.scaled',
        ),
      ),
    ),
  );

  $aggregate_assignments_s = $app->learningLockerDb->fetchAggregate($pipeline_assignments_s);

  // Popular resources
  $popular_query = array(
    'statement.verb.id' => $app->xapiHelpers->getVisitedUri(),
    'statement.context.contextActivities.parent' => array(
      '$elemMatch' => array(
        'id' => array(
          '$in' => $assignments_and_unit_uris,
        ),
      ),
    ),
  );

  $resources = $app->learningLockerDb->getPopularResourcedData($popular_query, 10);

  $response->json(array(
    'course' => $course_id,
    'lesson' => $lesson_id,
    'unit' => $unit_id,
    'students_count' => $students_count,
    'assignments_count' => count($assignmments_uris),
    'learning_content' => array(
      'unit' => array(
        'count' => $unit_visitors_count,
      ),
      'materials' => array(
        'count' => $material_visitors_count,
      ),
      'hyperlinks' => array(
        'count' => $link_visitors_count,
      ),
      'viewed_assignments' => array(
        'count' => $assignment_views_count,
      ),
      'submitted_assignments' => array(
        'count' => isset($aggregate_assignments_s['result'][0]['count']) ? $aggregate_assignments_s['result'][0]['count'] : 0,
        'unique_users' => isset($aggregate_assignments_s['result'][0]['visitors']) ? count($aggregate_assignments_s['result'][0]['visitors']) : 0,
        'average_score' => isset($aggregate_assignments_s['result'][0]['averageScore']) ? $aggregate_assignments_s['result'][0]['averageScore'] : 0,
      )
    ),
    'resources' => $resources,
  ));
});

// Course lessons endpoint
$klein->respond('/course/[i:id]/sna', function ($request, $response, $service, $app) {
  $file_name = 'course_sna.json';

  $course_id = $request->param('id');

  // Try to load from storage
  $file_contents = $app->storageHelper->readFileIfNotOutdated($course_id, $file_name);
  if ( $file_contents ) {
    $response->json(json_decode($file_contents));
    return;
  }

  $students_response = $app->serviceCaller->getCourseStudents($course_id);
  $students = json_decode($students_response);

  $nodes = array();
  $edges = array();

  foreach ( $students as $student ) {
    $nodes['mailto:' . $student] = array(
      'id' => 'mailto:' . $student,
      'label' => $student,
      'size' => '1',
    );
  }

  $query = array(
    'statement.context.contextActivities.grouping' => array(
      '$elemMatch' => array(
        'id' => $app->uriBuilder->buildCourseUri($course_id),
      ),
    ),
    'statement.verb.id' => array(
      '$in' => array(
        $app->xapiHelpers->getRespondedUri(),
        $app->xapiHelpers->getCreateUri(),
      ),
    ),
    'statement.object.definition.type' => $app->xapiHelpers->getCommentSchemaUri(),
    'statement.actor.mbox' => array(
      '$in' => array_keys($nodes),
    ),
  );

  $pipeline = array(
    array(
      '$match' => $query,
    ),
    array(
      '$group' => array(
        '_id' => '$statement.context.contextActivities.parent.id',
        'commenters' => array(
          '$push' => '$statement.actor.mbox',
        ),
      ),
    ),
  );

  $aggregate = $app->learningLockerDb->fetchAggregate($pipeline);

  //error_log(print_r($aggregate, true));

  if ( isset($aggregate['ok']) && (int)$aggregate['ok'] === 1 && isset($aggregate['result']) && is_array($aggregate['result']) && count($aggregate['result']) > 0 ) {
    $ids = array();
    foreach ( $aggregate['result'] as $single ) {
      if ( !in_array($single['_id'][0], $ids) ) {
        $ids[] = $single['_id'][0];
      }
    }

    if ( count($ids) > 0 ) {}
      $resources_query = array(
        'statement.context.contextActivities.grouping' => array(
          '$elemMatch' => array(
            'id' => $app->uriBuilder->buildCourseUri($course_id),
          ),
        ),
        'statement.verb.id' => $app->xapiHelpers->getCreateUri(),
        'statement.object.id' => array(
          '$in' => $ids,
        ),
        'statement.actor.mbox' => array(
          '$in' => array_keys($nodes),
        ),
      );

      $cursor = $app->learningLockerDb->fetchData($resources_query);
      $owner_lookup = array();
      foreach ($cursor as $resource ) {
        $owner_lookup[$resource['statement']['object']['id']] = $resource['statement']['actor']['mbox'];
      }

      //error_log(print_r($owner_lookup, true));

      foreach ($aggregate['result'] as $single ) {
        $owner = isset($owner_lookup[$single['_id'][0]]) ? $owner_lookup[$single['_id'][0]] : null;

        if ( !$owner ) {
          error_log('NO OWNER FOR RESOURCE: ' . $single['_id'][0]);
          continue;
        }
        // XXX Should fail if owner could not be determined
        foreach ($single['commenters'] as $commenter) {
          if ( !array_key_exists($commenter, $nodes) ) {
            // XXX This is crazy
            continue;
          }

          if ( !isset($nodes[$owner]) ) {
            error_log('NOT IN USERS: ' . $owner);
            continue;
          }

          $nodes[$owner]['size'] += 1;
          if ( isset($edges[$owner . ':' . $commenter]) ) {
            $edges[$owner . ':' . $commenter]['size'] += 1;
          } else {
            $edges[$owner . ':' . $commenter] = array(
              'id' => $owner . ':' . $commenter,
              'source' => $commenter,
              'target' => $owner,
              'size' => 1,
            );
          }
        }
      }
  }

  $response_data = array(
    'id' => $course_id,
    'nodes' => array_values($nodes),
    'edges' => array_values($edges),
  );

  // Try to save into storage
  $app->storageHelper->createOrUpdateFile($course_id, $file_name, json_encode($response_data));

  $response->json($response_data);
});


$klein->dispatch($request);
