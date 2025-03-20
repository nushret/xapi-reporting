<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class XapiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        
        if (!$authHeader) {
            return response()->json(['error' => 'Yetkilendirme başlığı eksik'], 401);
        }
        
        // Basic Auth formatı: "Basic base64(username:password)"
        $credentials = explode(' ', $authHeader);
        
        if (count($credentials) != 2 || $credentials[0] != 'Basic') {
            return response()->json(['error' => 'Geçersiz yetkilendirme formatı'], 401);
        }
        
        $decodedCredentials = base64_decode($credentials[1]);
        list($username, $password) = explode(':', $decodedCredentials);
        
        // Burada kendi kimlik doğrulama mantığınızı uygulayın
        if ($username !== 'xapi_user' || $password !== 'xapi_password') {
            return response()->json(['error' => 'Geçersiz kimlik bilgileri'], 401);
        }
        
        return $next($request);
    }
}

