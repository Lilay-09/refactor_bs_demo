<?php

namespace App\Enums;

enum ImageDirectory:string
{
    //
    case SHOP = 'shop';
    case ORDER_IMAGE = 'order_image';
    case RETURNED_IMAGE = 'returned_image';
    case SUBMIT_PACKAGE = 'submit_package';
    case USER_PROFILE = 'user_profile';
    case COMMENT = 'comment';
}
