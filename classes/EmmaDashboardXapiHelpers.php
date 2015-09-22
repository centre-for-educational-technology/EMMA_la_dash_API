<?php

class EmmaDashboardXapiHelpers {
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
}
