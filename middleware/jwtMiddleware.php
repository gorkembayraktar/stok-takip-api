<?php

use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;

// JWT ayarlarını yapılandırın (örnek)


$config = include( BASEAPP .'/config/settings.php');
$jwtSettings = $config['jwtSettings'];


$jwtMiddleware = function (Request $request, RequestHandler $handler) use ($jwtSettings) {

    $tokenHeader = $request->getHeader('Authorization');

    if (empty($tokenHeader) || !preg_match('/Bearer (.+)/', $tokenHeader[0], $matches)) {
        return newRespondWithJson(['error' => 'Token not provided'], 401);
    }
 
    $token = $matches[1];

    if (!$token) {
        return newRespondWithJson(['error' => 'Token not provided'], 401);
    }

    try {
        $decoded = JWT::decode($token, $jwtSettings['secret'], [$jwtSettings['algorithm']]);
        // JWT token doğrulaması başarılı, devam et
        $request = $request->withAttribute('user', $decoded);
        $response = $handler->handle($request);

        return $response;
    } catch (\Exception $e) {
        // JWT token doğrulaması başarısız
        return newRespondWithJson(['error' => 'Authentication failed', 'message' => $e->getMessage()], 401);
    }
};

