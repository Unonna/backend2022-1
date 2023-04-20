<?php

use DI\Container;
use Slim\Views\Twig;
use Slim\Factory\AppFactory;
use Slim\Views\TwigMiddleware;
use Slim\Middleware\MethodOverrideMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require __DIR__ . '/../vendor/autoload.php';

$container = new Container();
AppFactory::setContainer($container);

$container->set('db', function () {
    $db = new \PDO("sqlite:" . __DIR__ . '/../database/database.sqlite');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(\PDO::ATTR_TIMEOUT, 5000);
    $db->exec("PRAGMA journal_mode = WAL");
    return $db;
});

$app = AppFactory::create();

$app->addBodyParsingMiddleware();

$twig = Twig::create(__DIR__ . '/../twig', ['cache' => false]);

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});


$app->get('/downloads', function (Request $request, Response $response, $args) {

  
    $fp = fopen('./sample.pdf', 'rb');
    
    // Отправляем заголовки
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="user_report_'.date('Y-m-d').'.pdf"');
    
    // Отправляем содержимое файла
    fpassthru($fp);
    
    // Закрываем файл
    fclose($fp);

    // $response->getBody()->write("Hello world!");
    // return $response;
});

$app->get('/users', function (Request $request, Response $response, $args) {
    // GET Query params
    $query_params = $request->getQueryParams();
    $formz = $query_params['format'] ?? null;
    // dump($formz);
    // die;

    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);

    if($formz == 'json'){
        $payload = json_encode($users);
        $response->getBody()->write($payload);
        return $response
          ->withHeader('Content-Type', 'application/json');
    }
    if($formz == 'text'){
        $str = '';
        foreach($users as $user){
            $str .= $user->first_name . PHP_EOL;
            $str .= $user->last_name . PHP_EOL;
            $str .= $user->email . PHP_EOL;
        }
        $response->getBody()->write($str);
        return $response
          ->withHeader('Content-Type', 'text/plain');
    }



    // dump($users);
    // die;

    $view = Twig::fromRequest($request);
    return $view->render($response, 'users.html', [
        'users' => $users
    ]);
});

$app->get('/users-by-header', function (Request $request, Response $response, $args) {
    $headerAccept = $request->getHeader('Accept')[0];
    // dd($headerAccept);
    $db = $this->get('db');
    $sth = $db->prepare("SELECT * FROM users");
    $sth->execute();
    $users = $sth->fetchAll(\PDO::FETCH_OBJ);

    if($headerAccept == 'application/json'){
        $payload = json_encode($users);
        $response->getBody()->write($payload);
        return $response
          ->withHeader('Content-Type', 'application/json');
    }
    if($headerAccept == 'text/plain'){
        $str = '';
        foreach($users as $user){
            $str .= $user->first_name . PHP_EOL;
            $str .= $user->last_name . PHP_EOL;
            $str .= $user->email . PHP_EOL;
        }
        $response->getBody()->write($str);
        return $response
          ->withHeader('Content-Type', 'text/plain');
    }
    return $response->withStatus(404);
});

$app->get('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];

    $db = $this->get('db');
    $sth = $db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
    $sth->bindValue(':id', $id);
    $sth->execute();

    $user = $sth->fetch(\PDO::FETCH_OBJ);
    // dd($user);

    if ($user == false){
        return $response->withStatus(404);

    }
    
    $view = Twig::fromRequest($request);
    return $view->render($response, 'user.html', [
        'user' => $user
    ]);

});

$app->post('/users', function (Request $request, Response $response, $args) {
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();
    // получаем тело запроса
    //dump($parsedBody);
    //die;
    $sth = $db->prepare("INSERT INTO users (first_name, last_name, email) VALUES (?,?,?)");
    $sth->execute([$parsedBody['first_name'], $parsedBody['last_name'], $parsedBody['email']]);
    
    return $response->withStatus(201);


});

$app->patch('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');

    $db = $this->get('db');
    $sth = $db->prepare('SELECT * FROM users WHERE id=:id LIMIT 1');
    $sth->bindValue(':id', $id);
    $sth->execute();

    $user = $sth->fetch(\PDO::FETCH_OBJ);

    $parsedBody = $request->getParsedBody();
    $first_name = $parsedBody['first_name'] ?? $user->first_name;
    $last_name = $parsedBody['last_name'] ?? $user->last_name;
    $email = $parsedBody['email'] ?? $user->email;


    $sth = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
    $sth->execute([$first_name, $last_name, $email, $id]);

    return $response
  ->withHeader('Location', '/users')
  ->withStatus(302);

});

$app->put('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');
    $parsedBody = $request->getParsedBody();
    $first_name = $parsedBody['first_name'];
    $last_name = $parsedBody['last_name'];
    $email = $parsedBody['email'];
    
    // dd($parsedBody);

        $sth = $db->prepare("UPDATE users SET first_name=?, last_name=?, email=? WHERE id=?");
    $sth->execute([$first_name, $last_name, $email, $id]);


    return $response
  ->withHeader('Location', '/users')
  ->withStatus(302);
  
});

$app->delete('/users/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $db = $this->get('db');



    $sth = $db->prepare("DELETE FROM users WHERE id = ?");
    $sth->execute([$id]);

    return $response->withStatus(204);
});

$methodOverrideMiddleware = new MethodOverrideMiddleware();
$app->add($methodOverrideMiddleware);

$app->add(TwigMiddleware::create($app, $twig));
$app->run();


