<?php

	include_once $_SERVER['DOCUMENT_ROOT'].'/manager/includes/config.inc.php'; //указать ваш абсолютный путь до файла config.core.php
	include_once MODX_MANAGER_PATH.'includes/document.parser.class.inc.php';
	require_once MODX_BASE_PATH."assets/snippets/payme/paymeApi.php";

	$modx = new DocumentParser;

	$api = new PaymeApi();
	$api->setMyModx($modx);
	$api->setInputArray(file_get_contents("php://input"));
	$resp=json_encode($api->parseRequest(),JSON_UNESCAPED_UNICODE);

	header('Content-type: application/json charset=utf-8');
	echo $resp;
?>
