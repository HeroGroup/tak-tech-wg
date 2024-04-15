<?php
 
namespace App\Enums;

use App\Traits\ForSelect;
 
enum Privileges: string
{
  use ForSelect;

  case CREATE = 'create';
  case EDIT = 'edit';
  case ENABLE = 'enable';
  case DISABLE = 'disable';
  case REGENERATE = 'regenerate';
  case REMOVE = 'remove';
  case VIOLATIONS = 'violations';

}
