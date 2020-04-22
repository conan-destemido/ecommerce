<?php

use \Hcode\PageAdmin;
use \Hcode\Model\User;

$app->get('/admin', function() {
	
	User::verifyLogin();

	$page = new PageAdmin();
	
	$page->setTpl("index");
	
});

$app->get('/admin/login', function() {

	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);
	
	$page->setTpl("login");
	
});

$app->post("/admin/login", function(){
	
	User::login($_POST["login"], $_POST["password"]);
	
	header("Location: /admin");
	exit;
});

$app->get("/admin/logout", function(){
	
	User::logout();
	
	header("Location: /admin/login");
	exit;
});

// Rota para a página "esqueci a senha"
// Quando clica no link "Esqueci a senha" na página de login.
// Nesta página tem a entrada para preencher com email.
// Após submeter o formulário é enviado um email para recuperar a senha.
$app->get("/admin/forgot", function(){

	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);
	
	$page->setTpl("forgot");
	
});
// Após o envio de email para recuperar a senha,
// Deve redirecionar para a página "forgot-sent.html".
// Nesta página diz que o envio de email foi com sucesso ou não.
$app->post("/admin/forgot", function(){
	
	$user = User::getForgot($_POST["email"]);
	
	header("Location: /admin/forgot/sent");
	exit;
	
});

$app->get("/admin/forgot/sent", function(){
	
	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);
	
	$page->setTpl("forgot-sent");
	
});

// Rota para digitar a nova senha.
$app->get("/admin/forgot/reset", function(){

	$user = User::validForgotDecrypt($_GET["code"]);
	
	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);

	$page->setTpl("forgot-reset", array(
		"name"=>$user["desperson"],
		"code"=>$_GET["code"]
	));
	
});

// Rota para a página que diz que a troca da senha foi bem sucedida.
$app->post("/admin/forgot/reset", function(){
	
	$forgot = User::validForgotDecrypt($_POST["code"]);
	
	User::setForgotUsed($forgot["idrecovery"]);
	
	$user = new User();
	
	$user->get((int)$forgot["iduser"]);
	
	$password = password_hash($_POST["password"], PASSWORD_DEFAULT, [
		"cost"=>12
	]);
	
	$user->setPassword($password);
	
	$page = new PageAdmin([
		"header"=>false,
		"footer"=>false
	]);

	$page->setTpl("forgot-reset-success");	
	
});

?>