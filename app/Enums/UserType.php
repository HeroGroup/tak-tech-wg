<?php
 
namespace App\Enums;

use App\Traits\ForSelect;
 
enum UserType: string
{
  use ForSelect;

  case SUPERADMIN = 'superadmin';
  case ADMIN = 'admin';
  case RESELLER = 'reseller';

}
