<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogHttp
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $now = time();
        $today = date('Y-m-d', $now);
        $time = date('H:i:s', $now);

        $user = auth()->user()->email ?? 'Guest';
        $uri = $request->path();
        $method = $request->method();
        $body = json_encode($request->all());
        
        $content = "[$time] $user $method $uri $body \r\n";

        file_put_contents(base_path("/logs/http/$today.log"), $content, FILE_APPEND | LOCK_EX);

        return $next($request);
    }
}
