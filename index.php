<?php
use Slim\Factory\AppFactory;

use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Psr7\Response;


define('BASEAPP', __DIR__);

require BASEAPP . '/vendor/autoload.php';
require BASEAPP . '/Container/Container.php';
require BASEAPP . '/utils.php';
require BASEAPP . '/middleware/jwtMiddleware.php';



$app = AppFactory::create();

$app->addErrorMiddleware(true, true, true);
$app->setBasePath("/stok-takip/api");


$app->post('/login', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    // Kullanıcı adı ve şifre doğrulamasını yapın
    $username = $data['username'];
    $password = $data['password'];

    $db = $this->get('db');

    $user = $db->table('user')->where('username', $username)->first();

    // Kullanıcı doğrulama başarılıysa, JWT token üretin
    if ($user && check_pass($password, $user->password)) {
        $payload = [
            'sub' =>  $user->id,
            'username' =>  $user->username,
            'exp' => time() + (60 * 60 * 24), // 24 saatlik geçerlilik süresi
        ];
        $token = jwt_encode($payload);
        return respondWithJson($response, ['token' => $token], 401);
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
        $data = $request->getParsedBody();

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
        $data = $request->getParsedBody();
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

        $data = $request->getParsedBody();

        $list_id = $db->table('list')->insertGetId(['title' => $data['title']]);

        $data['items'] = json_decode($data['items'], true);

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


    $group->post('/variant/create', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $data = $request->getParsedBody();

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
        $data = $request->getParsedBody();
        if(!isset($data['title']) || !isset($data['stock']))
            return respondWithJson($response, [], 400);


        $db = $this->get('db');
        $s = $db->table('variants')->where('id', $args['id'])->update(['title' => $data['title'], 'stock' => $data['stock'] ]);
        return respondWithJson($response, [], $s ? 200 : 500);
    });
    $group->delete('/variant/{id:[0-9]+}', function (Request $request, Response $response, $args) {
        $db = $this->get('db');
        $s = $db->table('variants')->where('id', $args['id'])->update(['is_deleted' => true]);
        return respondWithJson($response, [], $s ? 200 : 500);
    });

})->add($jwtMiddleware);






try {
    $app->run();     
    
} catch (Exception $e) {    
  // We display a error message
  die( json_encode(array("status" => "failed", "message" => $e->getMessage()))); 
}