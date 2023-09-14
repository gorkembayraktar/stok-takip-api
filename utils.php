<?php


use Slim\Psr7\Response;

// Özel bir yöntem: JSON yanıt oluşturma
function respondWithJson(Response $response, $data, $statusCode = 200) {

    $out = [
        "status" => $statusCode >= 200 && $statusCode <= 210,
        "data" => $data
    ];

    $response->getBody()->write(json_encode($out));

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

    $response->getBody()->write(json_encode($out));
    return $response
    ->withHeader('Content-Type', 'application/json')
    ->withStatus($statusCode);
}