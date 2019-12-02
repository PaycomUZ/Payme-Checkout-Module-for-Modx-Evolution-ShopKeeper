<?php
 
require_once  $_SERVER['DOCUMENT_ROOT'].'/config.core.php'; //указать ваш абсолютный путь до файла config.core.php 
require_once MODX_CORE_PATH . 'model/modx/modx.class.php';
require_once MODX_CORE_PATH . '../assets/components/payme/paymeApi.php';

$modx = new modX();
$modx->initialize('web');
$modx->addPackage('shopkeeper3', $modx->getOption('core_path') . 'components/shopkeeper3/model/');

$api = new PaymeApi(); 
$api->setMyModx($modx); 
$api->setInputArray(file_get_contents("php://input")); 
$resp=json_encode($api->parseRequest(),JSON_UNESCAPED_UNICODE );

header('Content-type: application/json charset=utf-8');
echo $resp;