<?php

namespace Fcl\Theme\Middleware;

use Closure;
use Theme;

class ThemeLoader
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param String $theme
     * @param String $layout
     * @return mixed
     */
    public function handle($request, Closure $next, $theme = null, $layout = null)
    {
        if (isset($theme)) {
            Theme::uses($theme);
        }

        if (isset($layout)) {
            Theme::layout($layout);
        }

        return $next($request);
    }
}
