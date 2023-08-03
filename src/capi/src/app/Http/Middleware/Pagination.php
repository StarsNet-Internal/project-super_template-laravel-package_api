<?php

namespace StarsNet\Project\Capi\App\Http\Middleware;

use App\Traits\Controller\Paginatable as BasePaginatable;
use StarsNet\Project\Capi\App\Traits\Controller\Paginatable;
use Closure;

class Pagination
{
    use BasePaginatable, Paginatable;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof \Illuminate\Http\JsonResponse) {

            $currentData = $response->getData();
            $currentData = collect($currentData);

            // Sort collection
            $collection = $this->caseInsensitiveSortCollectionWithRequest($currentData, $request);

            // Retrieve paginated result
            $paginator = $this->paginateCollectionWithRequest($collection, $request);

            // if ($request->input('exclude_ids')) {
            //     $paginator->appends(['exclude_ids' => $request->input('exclude_ids')]);
            // }

            $response->setData($paginator);
        }

        return $response;
    }
}
