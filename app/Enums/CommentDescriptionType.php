<?php

namespace App\Enums;

enum CommentDescriptionType:string
{
    //
    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
}
