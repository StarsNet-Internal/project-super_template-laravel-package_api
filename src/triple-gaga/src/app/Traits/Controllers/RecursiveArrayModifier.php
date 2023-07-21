<?php

namespace StarsNet\Project\TripleGaga\Traits\Controllers;

use App\Constants\Model\Status;
use Illuminate\Support\Collection;
use StarsNet\Project\TripleGaga\App\Models\RefillInventoryRequest;

trait RecursiveArrayModifier
{
    private function recusriveAppendIsKeep(&$array, $matchingCategoryIds)
    {
        if (array_key_exists("category_id", $array)) {
            $array['is_keep'] = false;
            if (in_array($array["category_id"], $matchingCategoryIds)) {
                $array['is_keep'] = true;
            }
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recusriveAppendIsKeep(
                    $value,
                    $matchingCategoryIds
                );
            }
        }
    }

    private function recursiveRemoveItem($arr)
    {
        $result = [];
        foreach ($arr as $el) {
            if ($el['is_keep'] === true) {
                if (!empty($el['children'])) {
                    $el['children'] = $this->recursiveRemoveItem($el['children']);
                }
                $result[] = $el;
            }
        }
        return $result;
    }

    private function filterArray(&$array, $matchingCategoryIds)
    {
        if (array_key_exists("category_id", $array)) {
            $array['is_keep'] = false;
            if (in_array($array["category_id"], $matchingCategoryIds)) {
                $array['is_keep'] = true;
            }
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveAppendKey(
                    $value,
                    $matchingCategoryIds
                );
            }
        }
    }
}
