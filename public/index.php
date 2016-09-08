<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Slim\App;
use Slim\Http\Request;
use Slim\Http\Response;

$app = new App();

$container = $app->getContainer();

$container['zendesk'] = function() {
    $client = new \Zendesk\API\HttpClient(getenv('ZENDESK_SUBDOMAIN'));
    $client->setAuth('basic', ['username' => getenv('ZENDESK_USERNAME'), 'token' => getenv('ZENDESK_TOKEN')]);

    return $client;
};

$container['twig'] = function() {
    $loader = new Twig_Loader_Filesystem(__DIR__ . '/../template');
    return new Twig_Environment($loader, []);
};

$app->get('/iframe', function(Request $request, Response $response) use ($app) {

    $id = $request->getQueryParam('id', null);
    if (! isset($id) || empty($id)) {
        throw new \InvalidArgumentException('Missing required param: id');
    }

    /* @var $zendesk \Zendesk\API\HttpClient */
    $zendesk = $app->getContainer()->get('zendesk');
    $user = $zendesk->users()->find($id);

    $content = $app->getContainer()->get('twig')->render('iframe.twig', ['user' => $user]);
    $response->getBody()->write($content);
    return $response;
});

$app->get('/search', function(Request $request, Response $response) use ($app) {

    $query = $request->getQueryParam('q', null);
    if (! isset($query) || empty($query)) {
        throw new \InvalidArgumentException('Missing required param: q');
    }

    /* @var $zendesk \Zendesk\API\HttpClient */
    $zendesk = $app->getContainer()->get('zendesk');
    $users = $zendesk->users()->search([
        'query' => $query
    ]);

    $data = array_map(function($user) {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }, $users);

    return $response->withJson($data);
});

$app->run();