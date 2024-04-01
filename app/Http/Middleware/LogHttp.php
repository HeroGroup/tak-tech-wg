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
      
        $file = fopen(base_path("/logs/http/$today.log"), 'a');
        $user = auth()->user()->email ?? 'Guest';
        $uri = $request->fullUrl();
        $method = $request->method();
        $input = $request->all();
        $body = $input ? implode(' - ', $input) : '';;
        
        $content = "[$time] $user $method $uri $body \r\n";

        fwrite($file, $content);
        fclose($file);

        return $next($request);
    }
}
