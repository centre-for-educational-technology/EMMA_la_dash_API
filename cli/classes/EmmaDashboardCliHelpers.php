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
}
