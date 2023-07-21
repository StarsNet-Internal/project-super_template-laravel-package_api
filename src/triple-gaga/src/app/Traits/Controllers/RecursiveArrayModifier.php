<?php

namespace StarsNet\Project\TripleGaga\Traits\Controllers;

use App\Constants\Model\Status;
use App\Models\Category;
use Illuminate\Support\Collection;
use StarsNet\Project\TripleGaga\App\Models\RefillInventoryRequest;

trait RecursiveArrayModifier
{
    private function recursiveAppendIsKeep(&$array, $matchingCategoryIds)
    {
        if (array_key_exists("category_id", $array)) {
            $array['is_keep'] = false;
            if (in_array($array["category_id"], $matchingCategoryIds)) {
                $array['is_keep'] = true;
            }
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveAppendIsKeep(
                    $value,
                    $matchingCategoryIds
                );
            }
        }
    }

    private function recursiveRemoveItem($array)
    {
        $result = [];
        foreach ($array as $el) {
            if ($el['is_keep'] === true) {
                if (!empty($el['children'])) {
                    $el['children'] = $this->recursiveRemoveItem($el['children']);
                }
                $result[] = $el;
            }
        }
        return $result;
    }

    private function recursiveGetCategoryInfo(&$array)
    {
        if (array_key_exists("category_id", $array)) {
            $category = Category::objectID($array['category_id'])
                ->statusActive()
                ->first(['parent_id', 'title']);

            // Append keys
            $array['_id'] = $category->_id;
            $array['parent_id'] = $category->parent_id;
            $array['title'] = $category->title;

            // Remove keys
            unset($array['category_id']);
            unset($array['is_keep']);
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveGetCategoryInfo($value);
            }
        }
    }
}
