<?php

namespace StarsNet\Project\ClsPackaging\App\Constants\Model;

class ProductType
{
    const PRODUCT = 'PRODUCT';
    const QUOTATION_ITEM = 'QUOTATION_ITEM';

    public static $types = [
        self::PRODUCT,
        self::QUOTATION_ITEM,
    ];

    public static $defaultTypes = [
        self::PRODUCT,
        self::QUOTATION_ITEM,
    ];
}
