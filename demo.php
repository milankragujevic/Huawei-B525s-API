<?php
require_once 'vendor/autoload.php';

$router = new MilanKragujevic\HuaweiApi\Router;

$router->setAddress('192.168.8.1');

$router->login('admin', 'your-password');

var_dump($router->getStatus());