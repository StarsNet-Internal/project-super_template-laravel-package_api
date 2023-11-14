<?php

namespace StarsNet\Project\Easeca\App\Http\Middleware;

use App\Traits\Controller\AuthenticationTrait;
use Closure;

class GetStore
{
    use AuthenticationTrait;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $account = $this->getCurrentAccount();

        if ($account['store_id'] != null) {
            $request->route()->setParameter('store_id', $account['store_id']);
        }

        return $next($request);
    }
}
