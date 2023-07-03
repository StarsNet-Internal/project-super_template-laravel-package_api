<?php

namespace StarsNet\Project\App\Constants\Model;

class OrderCartItemStatus
{
    const CANCELLED = 'CANCELLED';
    const PENDING = 'PENDING';
    const SUCCESSFUL = 'SUCCESSFUL';
    const FAILED = 'FAILED';

    public static $types = [
        self::CANCELLED,
        self::PENDING,
        self::SUCCESSFUL,
        self::FAILED,
    ];

    public static $defaultTypes = [
        self::CANCELLED,
        self::PENDING,
        self::SUCCESSFUL,
        self::FAILED,
    ];
}
