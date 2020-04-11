<?php 

session_start();

require_once("vendor/autoload.php");

use \Slim\Slim;
use \Hcode\Page;
use \Hcode\PageAdmin;
use \Hcode\Model\User;

$app = new Slim();

$app->config('debug', true);

$app->get('/', function() {

	$page = new Page();
	
	$page->setTpl("index");
	
});

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

$app->get("/admin/users", function(){
	
	User::verifyLogin();
	
	$users = User::listAll();
	
	$page = new PageAdmin();
	
	$page->setTpl("users", array(
		"users"=>$users
	));
});

// Rota do create
$app->get("/admin/users/create", function(){
	
	User::verifyLogin();
	
	$page = new PageAdmin();
	
	$page->setTpl("users-create");
});

// Rota para excluir um usuário
// Obs 1.: Para o framework Slim entender que o usuário quer excluir um registro,
// precisa colocar a rota /admin/users/delete antes da rota /admin/users/:iduser
// para evitar que este execute primeiro e deixe de excluir o registro.
// Parece que o Slim lê até /users/ porque quando ele vê que existe um parametro
// logo depois, ele acha que já terminou a rota e executa a rota /admin/users/:iduser.
// Obs 2.: Alguns servidores vem com o delete desabilitado, por isso é melhor substituí-lo
// pelo get e enviando a informação de exclusão para um método.
$app->get("/admin/users/:iduser/delete", function($iduser){
	
	User::verifyLogin(); // Verifica sempre se a sessão não expirou
	
	$user = new User();
	
	$user->get((int)$iduser);
	
	$user->delete();
	
	header("Location: /admin/users");
	
	exit;
	
});

// Rota para quando chamar um usuário existente para editar
$app->get("/admin/users/:iduser", function($iduser){
	
	User::verifyLogin();
	
	$user = new User();
	
	$user->get((int)$iduser);
	
	$page = new PageAdmin();
	
	$page->setTpl("users-update", array(
		"user"=>$user->getValues()
	));
	
});

// Rota para quando houver post na página para gravar o usuário no banco (novo cadastro)
$app->post("/admin/users/create", function(){
	
	User::verifyLogin();
	
	$user = new User();
	
	$_POST["inadmin"] = (isset($_POST["inadmin"])) ? 1 : 0;
	
	$user->setData($_POST);
	
	$user->save();
	
	header("Location: /admin/users");
	exit;
	
});

// Rota para gravar um usuário que foi editado
$app->post("/admin/users/:iduser", function($iduser){
	
	User::verifyLogin();
	
	$user = new User();
	
	$_POST["inadmin"] = (isset($_POST["inadmin"])) ? 1 : 0;
	
	$user->get((int)$iduser); // Constroi a sql com o iduser
	
	$user->setData($_POST);   // Cria os gets and setters a partir dos nomes das colunas do tabela
	
	$user->update();
	
	header("Location: /admin/users");
	
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

$app->run();

 ?>