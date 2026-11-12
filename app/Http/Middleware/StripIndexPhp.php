<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StripIndexPhp
{
    public function handle(Request $request, Closure $next): Response
    {
        $uri = (string) $request->server->get('REQUEST_URI');

        if (preg_match('#^/index\.php(/|$)#', $uri) && $request->getBaseUrl() === '') {
            $server = $request->server->all();
            $server['REQUEST_URI'] = substr($uri, strlen('/index.php')) ?: '/';
            $request->initialize($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookies->all(), $request->files->all(), $server, $request->getContent());
        }

        return $next($request);
    }
}
