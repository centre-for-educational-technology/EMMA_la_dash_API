<?php

class EmmaDashboardXapiHelpers {
  const UNKNOWN_ACTIVITY_TYPE = 'UNKNOWN ACTIVITY TYPE';
  public static function getJoinUri () {
    return 'http://activitystrea.ms/schema/1.0/join';
  }

  public static function getLeaveUri () {
    return 'http://activitystrea.ms/schema/1.0/leave';
  }

  public static function getVisitedUri () {
    return 'http://activitystrea.ms/schema/1.0/visited';
  }

  public static function getAnsweredUri () {
    return 'http://adlnet.gov/expapi/verbs/answered';
  }

  public static function getCreateUri () {
    return 'http://activitystrea.ms/schema/1.0/create';
  }

  public static function getRespondedUri () {
    return 'http://adlnet.gov/expapi/verbs/responded';
  }


  public static function getCommentedUri() {
    return 'http://activitystrea.ms/schema/1.0/comment';
  }

  public static function getLinkTypeUri () {
    return 'http://adlnet.gov/expapi/activities/link';
  }

  public static function getCommentSchemaUri() {
    return 'http://activitystrea.ms/schema/1.0/comment';
  }

  /**
   * Retruns unknown activity type constant
   * @return string Returned value
   */
  public static function getDefaultUnknownActivityType() {
    return self::UNKNOWN_ACTIVITY_TYPE;
  }

  /**
   * Returns a type for verb ID (URI) or unknown default type.
   * @param  string $verb_id Verb uniqui ID (URI)
   * @return string          Type from verb ID or unknown default
   */
  public static function getActivityTypeFromVerbId($verb_id) {
    $type = self::UNKNOWN_ACTIVITY_TYPE;

    if ( $verb_id === self::getJoinUri() ) {
      $type = 'joined';
    } else if ( $verb_id === self::getLeaveUri() ) {
      $type = 'left';
    } else if ( $verb_id === self::getVisitedUri() ) {
      $type = 'visited';
    } else if ( $verb_id === self::getAnsweredUri() ) {
      $type = 'answered';
    } else if ( $verb_id === self::getRespondedUri() ) {
      $type = 'responded';
    } else if ( $verb_id === self::getCreateUri() ) {
      $type = 'commented';
    }

    return $type;
  }
}
