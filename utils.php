<?php


use Firebase\JWT\JWT;
use Slim\Psr7\Response;
use Slim\Psr7\Request;

$config = include( BASEAPP .'/config/settings.php');
$jwtSettings = $config['jwtSettings'];

// Özel bir yöntem: JSON yanıt oluşturma
function respondWithJson(Response $response, $data, $statusCode = 200) {

    $out = [
        "status" => $statusCode >= 200 && $statusCode <= 210,
        "data" => $data
    ];

    $response->getBody()->write(json_encode($out,JSON_NUMERIC_CHECK));

    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($statusCode);
}

function newRespondWithJson($data, $statusCode = 200){
    $response = new Response();
    $out = [
        "status" => $statusCode >= 200 && $statusCode <= 210,
        "data" => $data
    ];

    $response->getBody()->write(json_encode($out, JSON_NUMERIC_CHECK));
    return $response
    ->withHeader('Content-Type', 'application/json')
    ->withHeader('Access-Control-Allow-Origin', '*')
    ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Accept, Origin, Content-Type, Authorization')
    ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
    ->withStatus($statusCode);
}


function jwt_encode($payload){
    global $jwtSettings;
    return JWT::encode($payload, $jwtSettings['secret'], $jwtSettings['algorithm']);
}


function hash_pass($pasword){
    return password_hash($pasword, PASSWORD_DEFAULT);
}

function check_pass($password, $hash){
   return password_verify($password, $hash);
}

function parse_body(Request $request){
    $contentType = $request->getHeaderLine('Content-Type');
    if ($contentType === 'application/json') {
        $data = file_get_contents('php://input');
        $data = json_decode($data, true);
    }else{
        $data = $request->getParsedBody();
    }

    return $data;
}