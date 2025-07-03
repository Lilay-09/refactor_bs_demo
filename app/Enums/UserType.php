<?php

namespace App\Enums;

enum UserType: string
{
    //
    case ADMIN = 'admin';
    case MERCHANT = 'merchant';
    case DRIVER = 'driver';

}
