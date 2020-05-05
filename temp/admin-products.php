<?php

///////////////////////////////////////////////////////////////////////////////////////
// ROTAS PARA OS ARQUIVOS DE PRODUTOS
// Rota para a tela da listagem dos produtos
$app->get("/admin/products", function(){

	User::verifyLogin();

	$products = Product::listAll();
	
	$page = new PageAdmin();
	
	$page->setTpl("products", [
		"products"=>$products
	]);
});

// Rota para a tela de inclusão de produtos
$app->get("/admin/products/create", function(){

	User::verifyLogin();
	
	$page = new PageAdmin();
	
	$page->setTpl("products-create");
	
});

// Rota pra gravar as informações de inclusões 
// do produtos ou salvar se existir um registro
$app->post("/admin/products/create", function(){

	User::verifyLogin();
	
	$product = new Product();
	
	$product->setData($_POST);
	
	$product->save();
	
	header("Location: /admin/products");
	
	exit;
	
});

// Rota para a tela de edição de produtos
$app->get("/admin/products/:idproduct", function($idproduct){

	User::verifyLogin();
	
	$product = new Product();
	
	$product->get((int)$idproduct);
	
	$page = new PageAdmin();
	
	$page->setTpl("products-update", [
		"product"=>$product->getValues()
	]);
	
});

// Rota para a tela de update de produtos
$app->post("/admin/products/:idproduct", function($idproduct){

	User::verifyLogin();
	
	$product = new Product();
	
	$product->get((int)$idproduct);
	
	$product->setData($_POST);
	
	$product->save();
	
	$product->setPhoto($_FILES["file"]);
	
	header("Location: /admin/products");
	
	exit;
	
});

// Rota para a exclusão de produtos
$app->get("/admin/products/:idproduct/delete", function($idproduct){

	User::verifyLogin();
	
	$product = new Product();
	
	$product->get((int)$idproduct);
	
	$product->delete();
	
	header("Location: /admin/products");
	
	exit;
	
});

?>