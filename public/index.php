<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Handler\NullHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Slim\App;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Zendesk\API\HttpClient;

$container = new Container();
$app = new App($container);

$container['logger'] = function(): Logger {
    $logger = new Logger('zendesk-crm-integration');
    if (is_writeable(dirname(__FILE__) . '/logs/app.log')) {
        $logger->pushHandler(new RotatingFileHandler(dirname(__FILE__) . '/logs/app.log', 10));
    } else {
        $logger->pushHandler(new NullHandler());
    }
    return $logger;
};

$container['zendesk'] = function (): HttpClient {
    $client = new HttpClient(getenv('ZENDESK_SUBDOMAIN'));
    $client->setAuth('basic', ['username' => getenv('ZENDESK_USERNAME'), 'token' => getenv('ZENDESK_TOKEN')]);

    return $client;
};

$container['twig'] = function (): Twig_Environment {
    $loader = new Twig_Loader_Filesystem(__DIR__ . '/../template');
    return new Twig_Environment($loader, []);
};

$app->add(function (Request $request, Response $response, Callable $next) use ($app) {

    $sig = $request->getQueryParam('sig', null);
    $expires = $request->getQueryParam('expires', null);

    /* @var Logger $logger */

    $logger = $app->getContainer()->get('logger');

    if ($sig === null || $expires === null || $expires < time()) {
        $logger->error("Request missing sig/expires, or expires < time", [$sig, $expires, time()]);
        return $response->withStatus(401);
    }

    $params = $request->getQueryParams();
    unset($params['sig']);

    $expected_sig = hash_hmac('sha256', http_build_query($params), getenv('SECRET'));

    if ($expected_sig !== $sig) {
        $logger->error("Request has invalid sig.", ['expected' => $expected_sig, 'actual' => $sig]);
        return $response->withStatus(401);
    }

    return $next($request, $response);
});

$app->get('/iframe', function (Request $request, Response $response) use ($app) {

    $id = $request->getQueryParam('id', null);
    if (!isset($id) || empty($id)) {
        throw new InvalidArgumentException('Missing required param: id');
    }

    /* @var $zendesk HttpClient */
    $zendesk = $app->getContainer()->get('zendesk');
    $user = $zendesk->users()->find($id);

    $user = $user->user;

    $userArray = [
        'name' => $user->name,
        'email' => $user->email,
    ];

    $content = $app->getContainer()->get('twig')->render('iframe.twig', ['user' => $userArray]);
    $response->getBody()->write($content);
    return $response;
});

$app->get('/search', function (Request $request, Response $response) use ($app) {

    $query = $request->getQueryParam('q', null);
    if (!isset($query) || empty($query)) {
        throw new InvalidArgumentException('Missing required param: q');
    }

    /* @var $zendesk HttpClient */
    $zendesk = $app->getContainer()->get('zendesk');
    $users = $zendesk->users()->search([
        'query' => $query
    ]);

    $data = array_map(function (stdClass $user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }, $users->users);

    return $response->withJson(['results' => $data]);
});

$app->run();
