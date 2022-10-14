<?php

namespace AuroraWebSoftware\AAuth\Enums;

enum ABACCondition
{
    case equal;
    case identical;
    case not_equal;
    case greater_then;
    case less_then;
    case greater_than_or_equal_to;
    case less_than_or_equal_to;
}
