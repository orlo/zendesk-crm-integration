<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Monolog\Logger;
use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$app = new App();

$container = $app->getContainer();

$logger = new Logger('zendesk-crm-integration');
if (is_writeable(dirname(__FILE__) . '/logs/app.log')) {
    $logger->pushHandler(new \Monolog\Handler\RotatingFileHandler(dirname(__FILE__) . '/logs/app.log', 10));
} else {
    $logger->pushHandler(new \Monolog\Handler\NullHandler());
}

$container['logger'] = $logger;

$container['zendesk'] = function () {
    $client = new \Zendesk\API\HttpClient(getenv('ZENDESK_SUBDOMAIN'));
    $client->setAuth('basic', ['username' => getenv('ZENDESK_USERNAME'), 'token' => getenv('ZENDESK_TOKEN')]);

    return $client;
};

$container['twig'] = function () {
    $loader = new Twig_Loader_Filesystem(__DIR__ . '/../template');
    return new Twig_Environment($loader, []);
};

$app->add(function (Request $request, Response $response, $next) use ($app) {

    $sig = $request->getQueryParam('sig', null);
    $expires = $request->getQueryParam('expires', null);

    /* @var Logger $logger */

    $logger = $app->get('logger');

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
        throw new \InvalidArgumentException('Missing required param: id');
    }

    /* @var $zendesk \Zendesk\API\HttpClient */
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
        throw new \InvalidArgumentException('Missing required param: q');
    }

    /* @var $zendesk \Zendesk\API\HttpClient */
    $zendesk = $app->getContainer()->get('zendesk');
    $users = $zendesk->users()->search([
        'query' => $query
    ]);

    $data = array_map(function ($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }, $users->users);

    return $response->withJson(['results' => $data]);
});

$app->run();
