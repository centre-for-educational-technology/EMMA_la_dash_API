<?php

class EmmaDashboardHelpers {
  public static function shortenName($name) {
    $name = trim( $name );
    $name_split = explode( ' ', $name, 2 );
    if ( count($name_split) > 1 ) {
      return $name_split[0] . ' ' . mb_substr( trim( $name_split[1] ), 0, 1 ) . '.';
    }

    return $name;
  }
}
