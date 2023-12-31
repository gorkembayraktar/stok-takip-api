<?php
use Slim\Factory\AppFactory;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteContext;


define('BASEAPP', __DIR__);

require BASEAPP . '/vendor/autoload.php';
require BASEAPP . '/Container/Container.php';
require BASEAPP . '/utils.php';
require BASEAPP . '/middleware/jwtMiddleware.php';


$config = include(BASEAPP . '/config/settings.php');

$app = AppFactory::create();


$app->addErrorMiddleware(true, true, true);
$app->setBasePath( $config['basePath'] );


// CORS middleware
$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->addBodyParsingMiddleware();

// This middleware will append the response header Access-Control-Allow-Methods with all allowed methods
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    $routeContext = RouteContext::fromRequest($request);
    $routingResults = $routeContext->getRoutingResults();
    $methods = $routingResults->getAllowedMethods();
    $requestHeaders = $request->getHeaderLine('Access-Control-Request-Headers');

    $response = $handler->handle($request);

    $response = $response->withHeader('Access-Control-Allow-Origin', '*');
    $response = $response->withHeader('Access-Control-Allow-Methods', implode(',', $methods));
    $response = $response->withHeader('Access-Control-Allow-Headers', $requestHeaders);

    // Optional: Allow Ajax CORS requests with Authorization header
    //$response = $response->withHeader('Access-Control-Allow-Credentials', 'true');

    return $response;
});


// The RoutingMiddleware should be added after our CORS middleware so routing is performed first
$app->addRoutingMiddleware();

$app->post('/login', function (Request $request, Response $response) {


    $data = parse_body($request);

    // Kullanıcı adı ve şifre doğrulamasını yapın
    $username = isset($data['username']) ? $data['username'] : '';
    $password = isset($data['username']) ? $data['password'] : '';

    $db = $this->get('db');

    $user = $db->table('user')->where('username', $username)->first();

    // Kullanıcı doğrulama başarılıysa, JWT token üretin
    if ($user && check_pass($password, $user->password)) {
        $payload = [
            'sub' =>  $user->id,
            'username' =>  $user->username,
            'exp' => time() + (60 * 60 * 24), // 24 saatlik geçerlilik süresi
        ];
        $user = [
            "id" => $user->id,
            "username" => $user->username,
        ];
        $token = jwt_encode($payload);
        return respondWithJson($response, ['token' => $token, 'user' => $user], 200);
    } else {
       return respondWithJson($response, ['error' => 'Authentication failed'], 401);
    }
});



$app->group('', function ($group)  {



    $group->get('/products', function (Request $request, Response $response, $args) {
        $user = $request->getAttribute('user');
        
        $db = $this->get('db');
        $products = $db->table('products')->where('is_deleted', false)->get();
        foreach($products as &$product){
            $product->variants = $db->table('variants')->where('product_id', $product->id)->where('is_deleted', false)->get();
        }
        return respondWithJson($response, $products);
    });

    $group->get('/product/{id:[0-9]+}', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $product = $db->table('products')->where('is_deleted', false)->where('id', $args['id'])->first();

        $product->variants = $db->table('variants')->where('product_id', $args['id'])->where('is_deleted', false)->get();
        return respondWithJson($response, $product);
    });

    $group->post('/product/create', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $data = parse_body($request);

        $insert = [
            "title" => $data['title']
        ];

        $id = $db->table('products')->insertGetId($insert);
        $product = $db->table('products')->find($id);

        return respondWithJson($response, $product);
    });
    // product update
    $group->post('/product/{id:[0-9]+}', function (Request $request, Response $response, $args) {
        
        $db = $this->get('db');
        $data = parse_body($request);
        $s = $db->table('products')
        ->where('is_deleted', false)
        ->where('id', $args['id'])->update([
            'title' => $data['title']
        ]);
        return respondWithJson($response, [], $s ? 200 : 500);
    });
    $group->delete('/product/{id:[0-9]+}', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $s = $db->table('products')->where('id', $args['id'])->update(['is_deleted' => true]);
        return respondWithJson($response, [], $s ? 200 : 500);
    });

    $group->get('/list', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $list = $db->table('list')->where('is_deleted', false)->get();
        foreach($list as &$item){
            $item->items = [];

            $ids = $db->table('list_variants')
            ->select('product_id')
            ->distinct()
            ->where('list_id', $item->id)
            ->get()
            ->pluck('product_id');

            foreach($ids as $id){
                $item->items[] = [
                    "product" => $db->table('products')->where('id', $id)->first(),
                    "variants" => $db->table('list_variants')->select('variants.*', 'list_variants.total')
                    ->leftJoin('variants', 'list_variants.variant_id', '=', 'variants.id')
                    ->where('list_variants.product_id', $id)
                    ->where('variants.is_deleted', false)
                    ->where('list_variants.list_id', $item->id)
                    ->get()
                ];
            }
        }
        return respondWithJson($response, $list);
    });

    $group->get('/list/{id:[0-9]+}', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $item = get_list_with_id($db, $args['id']);
        return respondWithJson($response, $item);
    });

    function get_list_with_id( $db, int $args_id  ){
        $item = $db->table('list')->where('is_deleted', false)->where("id", $args_id)->first();
        if(!$item) return (object)[];
        $item->items = [];

        $ids = $db->table('list_variants')
        ->select('product_id')
        ->distinct()
        ->where('list_id', $item->id)
        ->get()
        ->pluck('product_id');

        foreach($ids as $id){
            $item->items[] = [
                "product" => $db->table('products')->where('id', $id)->first(),
                "variants" => $db->table('list_variants')->select('variants.*', 'list_variants.total')
                ->leftJoin('variants', 'list_variants.variant_id', '=', 'variants.id')
                ->where('list_variants.product_id', $id)
                ->where('variants.product_id', '<>' , null)
                ->where('list_variants.list_id', $item->id)
                ->get()
            ];
        }
        return $item;
    }

    $group->post('/list/create', function (Request $request, Response $response, $args) {

        $db = $this->get('db');

        $data = parse_body($request);

        $list_id = $db->table('list')->insertGetId(['title' => $data['title']]);

        foreach($data['items'] as $item){
            foreach($item['variants'] as $variant){
                if(
                    $variant['id'] &&
                    $db->table('products')->where('id', $item['product']['id'])->exists() &&
                    $db->table('variants')->where('id', $variant['id'])->exists()
                ){

                    $db->table('list_variants')->insert([
                        'list_id' => $list_id,
                        'variant_id' => $variant['id'],
                        'product_id' => $item['product']['id'],
                        'total' => $variant['total']
                    ]);
                }
            
            }
        }
        return respondWithJson($response, get_list_with_id($db, $list_id));
    });
   $group->post('/list/{id:[0-9]+}', function (Request $request, Response $response, $args) {

        $db = $this->get('db');

        $data = parse_body($request);

        $db->table('list')->
        where('id', $args['id'])->
        update(['title' => $data['title']]);

        $db->table('list_variants')->where('list_id', $args['id'])->delete();

      
        $list_id = $args['id'];
        
        foreach($data['items'] as $item){
            foreach($item['variants'] as $variant){
                if(
                    $variant['id'] &&
                    $db->table('products')->where('id', $item['product']['id'])->exists() &&
                    $db->table('variants')->where('id', $variant['id'])->exists()
                ){

                    $db->table('list_variants')->insert([
                        'list_id' => $list_id,
                        'variant_id' => $variant['id'],
                        'product_id' => $item['product']['id'],
                        'total' => $variant['total']
                    ]);
                }
            
            }
        }
        return respondWithJson($response, get_list_with_id($db, $list_id));
    });

    $group->delete('/list/{id:[0-9]+}', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $s = $db->table('list')->where('id', $args['id'])->update(['is_deleted' => true]);
        return respondWithJson($response, [], $s ? 200 : 500);
    });
   

    $group->post('/variant/create', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $data = parse_body($request);

        if(!isset($data['product_id']) || is_numeric(!$data['product_id'])){
            return respondWithJson($response, [ 'message' => 'Product id doğru değil.' ], 400);
        }

        $insert = [
            "title" => $data['title'],
            "product_id" => $data['product_id'],
            "stock" => $data['stock'] ?? 0
        ];

        $id = $db->table('variants')->insertGetId($insert);
        $variant = $db->table('variants')->find($id);

        return respondWithJson($response, $variant, 201);
    });

    // update
    $group->post('/variant/{id:[0-9]+}', function (Request $request, Response $response, $args) {
        $data = parse_body($request);
        if(!isset($data['title']) || !isset($data['stock']))
            return respondWithJson($response, [], 400);
        $db = $this->get('db');
        $s = $db->table('variants')
        ->where('id', $args['id'])
        ->update(['title' => $data['title'],
         'stock' => intval($data['stock']) ]);
        return respondWithJson($response, [], 200);
    });

    $group->delete('/variant/{id:[0-9]+}', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $s = $db->table('variants')->where('id', $args['id'])->update(['is_deleted' => true]);
        return respondWithJson($response, [], $s ? 200 : 500);
    });

   // update
   $group->post('/calculate', function (Request $request, Response $response, $args) {
        $data = parse_body($request);
        $db = $this->get('db');

        if(!$data){
            return respondWithJson($response, ["message" => 'Uncorrect data'], 400);
        }

        foreach($data as $list){

            $get_list_data = get_list_with_id($db, $list['id']);

            if($get_list_data && property_exists( $get_list_data , 'items')){

                foreach($get_list_data->items as $item){

                
                    foreach($item['variants'] as $variant){
                    
                        $db->table('variants')->where('id', $variant->id)->decrement('stock', $variant->total * $list['total']);
                        $db->table('variants_stock_decrement')->insert([
                            'variant_id' => $variant->id,
                            'stock' => $db->table('variants')->where('id', $variant->id)->first()->stock,
                            'decrement' => $variant->total * $list['total']
                        ]);
                    }
                }
            }
            $i = $db->table('calculate')->insert([
                'list_id' => $list['id'],
                'list_data' => json_encode($get_list_data),
                'total' => $list['total']
            ]);

        }

        return respondWithJson($response, []);
        
    });



})->add($jwtMiddleware);


$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    throw new HttpNotFoundException($request);
});




try {
    $app->run();     
    
} catch (Exception $e) {    
  // We display a error message
  die( json_encode(array("status" => "failed", "message" => $e->getMessage()))); 
}