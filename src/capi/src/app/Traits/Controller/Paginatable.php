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

        $sortedCollection = $collection->toArray();

        usort($sortedCollection, function ($a, $b) use ($sortBy, $sortOrder) {
            $valueA = data_get($a, $sortBy);
            $valueB = data_get($b, $sortBy);

            // Custom sorting logic to sort uppercase letters before lowercase letters
            if (is_string($valueA) && is_string($valueB)) {
                return strcmp(strtoupper($valueA), strtoupper($valueB));
            }

            // For other data types, use the default comparison
            return $valueA <=> $valueB;
        });

        return $sortOrder === 'asc' ? collect($sortedCollection) : collect(array_reverse($sortedCollection));
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
