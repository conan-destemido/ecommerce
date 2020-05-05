<?php

//////////////////////////////////////////////////////////////////////////////
// ROTA PARA OS ARQUIVOS DAS CATEGORIAS
// Rota para acessar as categorias
$app->get("/admin/categories", function(){
	
	User::verifyLogin();
	
	$categories = Category::listAll();
	
	$page = new PageAdmin();

	$page->setTpl("categories", [
		"categories"=>$categories
	]);	
	
});
// Rora da página de inclusão das categorias
$app->get("/admin/categories/create", function(){
	
	User::verifyLogin();

	$page = new PageAdmin();

	$page->setTpl("categories-create");
	
});
// Rota para incluir uma categoria após o submit da página categories-create
$app->post("/admin/categories/create", function(){
	
	User::verifyLogin();

	$category = new Category();
	
	$category->setData($_POST);
	
	$category->save();
	
	header("Location: /admin/categories");
	
	exit;

});
// Rota pra excluir um registro de uma categoria
$app->get("/admin/categories/:idcategory/delete", function($idcategory){
	
	User::verifyLogin();
	
	$category = new Category();
	
	$category->get((int)$idcategory);
	
	$category->delete();

	header("Location: /admin/categories");
	
	exit;
	
});
// Rota para a página de edição para atualizar um registro de categoria
$app->get("/admin/categories/:idcategory", function($idcategory){
	
	User::verifyLogin();

	$category = new Category();
	
	$category->get((int)$idcategory);
	
	$page = new PageAdmin();

	$page->setTpl("categories-update", [
		"category"=>$category->getValues()
	]);
	
});
// Rota pra atualizar um registro de categoria
$app->post("/admin/categories/:idcategory", function($idcategory){
	
	User::verifyLogin();

	$category = new Category();
	
	$category->get((int)$idcategory);

	$category->setData($_POST);
	
	$category->save();
	
	header("Location: /admin/categories");
	
	exit;	
	
});

// Rota da página de relacionamento de produtos com categoria
$app->get("/admin/categories/:idcategory/products", function($idcategory){
	
	User::verifyLogin();
	
	$category = new Category();
	
	$category->get((int)$idcategory);
	
	$page = new PageAdmin();
	
	$page->setTpl("categories-products", [
		"category"=>$category->getValues(),
		"productsRelated"=>$category->getProducts(true),
		"productsNotRelated"=>$category->getProducts(false)
	]);
});

// Rota do menu categorias para categorizar (adicionar em uma categoria) um produto
$app->get("/admin/categories/:idcategory/products/:idproduct/add", function($idcategory, $idproduct){
	
	User::verifyLogin();
	
	$category = new Category();
	
	$category->get((int)$idcategory);

	$product = new Product();
	
	$product->get((int)$idproduct);
	
	$category->addProduct($product);
	
	header("Location: /admin/categories/".$idcategory."/products");
	
	exit;
	
});

// Rota do menu categorias para descategorizar um produto (remover um produto de uma categoria) 
$app->get("/admin/categories/:idcategory/products/:idproduct/remove", function($idcategory, $idproduct){
	
	User::verifyLogin();
	
	$category = new Category();
	
	$category->get((int)$idcategory);

	$product = new Product();
	
	$product->get((int)$idproduct);
	
	$category->removeProduct($product);
	
	header("Location: /admin/categories/".$idcategory."/products");

	exit;	
});

?>