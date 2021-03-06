<?php


// ROTA PARA OS ARQUIVOS DO ADMIN USERS
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

?>