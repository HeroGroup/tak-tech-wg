<?php

namespace App\Traits;

trait ForSelect {
  public static function forSelect(): array
  {
    return array_column(self::cases(), 'name', 'value');
  }
}