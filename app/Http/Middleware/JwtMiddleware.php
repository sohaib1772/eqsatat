<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\User;
use Exception;

class JwtMiddleware
{
    public function handle($request, Closure $next)
    {
        try {
            JWTAuth::setRequest($request);
            // التحقق من صحة التوكن
            JWTAuth::parseToken()->authenticate();
        } catch (Exception $e) {
            if ($e instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException) {
                // في حال انتهاء صلاحية التوكن، نقوم بالتحقق من سلامة التوقيع وتمرير الطلب تلقائياً
                try {
                    $token = JWTAuth::getToken();
                    if ($token) {
                        $payload = JWTAuth::getJWTProvider()->decode($token->get());
                        if (isset($payload['sub'])) {
                            $user = User::find($payload['sub']);
                            if ($user && $user->status !== 'محذوف') {
                                auth()->login($user);
                                $request->setUserResolver(fn () => $user);
                                return $next($request);
                            }
                        }
                    }
                } catch (Exception $inner) {
                    // في حال فشل فك التوكن
                }

                return response()->json([
                    'status' => 'error',
                    'message' => 'انتهت صلاحية التوكن'
                ], 401);
            } elseif ($e instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'التوكن غير صالح'
                ], 401);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'غير مسجل دخول'
                ], 401);
            }
        }
        return $next($request);
    }
}
