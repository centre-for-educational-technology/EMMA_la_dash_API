<?php

class EmmaDashboardCliHelpers {
  public static function extractCourseIdFromUrl( $url ) {
    $split = explode( '?cor=', $url, 2 );
    return count($split) > 1 ? $split[1] : $url;
  }

  public static function extractFirstValue( $titles ) {
    $values = array_values( $titles );
    return $values[0];
  }

  public static function mboxIntoEmail( $mbox ) {
    return preg_replace( '/^' . preg_quote('mailto:', '/') . '/', '', $mbox );
  }

  public static function roundMinutes( $time ) {
    if ( $time > 1) {
      return round($time, 0, PHP_ROUND_HALF_UP);
    } else {
      return round($time, 2, PHP_ROUND_HALF_UP);
    }
  }

  public static function milliSecondsToMinutes( $time ) {
    return $time / ( 60 * 1000 );
  }
}
