<?php

namespace StarsNet\Project\ClsPackaging\App\Constants\Model;

class OrderType
{
    const DELIVERY = 'DELIVERY';
    const INVOICE = 'INVOICE';
    const QUOTATION = 'QUOTATION';

    public static $types = [
        self::DELIVERY,
        self::INVOICE,
        self::QUOTATION,
    ];

    public static $defaultTypes = [
        self::DELIVERY,
        self::INVOICE,
        self::QUOTATION,
    ];
}
