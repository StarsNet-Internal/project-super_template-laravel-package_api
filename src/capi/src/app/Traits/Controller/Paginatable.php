<?php

namespace StarsNet\Project\Capi\App\Traits\Controller;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait Paginatable
{
    private function caseInsensitiveSortCollection(
        Collection $collection,
        string $sortBy = 'created_at',
        string $sortOrder = 'desc'
    ): Collection {
        if (strtoupper($sortBy) === 'DEFAULT') return $collection;

        $sortFunction = function ($a, $b) use ($sortBy) {
            $valueA = data_get($a, $sortBy);
            $valueB = data_get($b, $sortBy);

            // Convert values to float if they are numeric
            if (is_numeric($valueA) && is_numeric($valueB)) {
                $valueA = (float) $valueA;
                $valueB = (float) $valueB;
            }

            // Perform a numeric comparison if both values are numeric
            if (is_numeric($valueA) && is_numeric($valueB)) {
                return $valueA - $valueB;
            }

            // Perform a case-insensitive string comparison otherwise
            return strcasecmp($valueA, $valueB);
        };

        return $sortOrder === 'asc' ? $collection->sort($sortFunction) : $collection->sort($sortFunction)->reverse();
    }

    /**
     * Sort collection based on sorting-related inputs provided from Request.
     * 
     * @param \Illuminate\Support\Collection    $collection
     * @param \Illuminate\Http\Request          $request
     * @param ?string                           [$request->sort_by]         Any key's name in Collection of Records
     * @param ?string                           [$request->sort_order]      Acceptable values: ['asc', 'desc']
     * ---------------------------------------------------------------------------------------------------------
     * @return \Illuminate\Support\Collection   Sorted collection, fallback sort is by "created_at" in "desc" order. 
     */
    private function caseInsensitiveSortCollectionWithRequest(Collection $collection, Request $request): Collection
    {
        // Extract pagination inputs
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $sortOrder = strtolower($sortOrder);

        return $this->caseInsensitiveSortCollection($collection, $sortBy, $sortOrder);
    }
}
