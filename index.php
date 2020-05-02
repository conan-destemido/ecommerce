<?php 

session_start();

require_once("vendor/autoload.php"); 

use \Hcode\Page;
use \Slim\Slim;
use \Hcode\PageAdmin;
use \Hcode\Model\User;
use \Hcode\Model\Category;
use \Hcode\Model\Product;
use \Hcode\Model\Cart;
use \Hcode\Model\Address;

function formatPrice($vlPrice)    
{
	
	return number_format($vlPrice, 2, ",", ".");
	
}

function checkLogin($inadmin = true)
{
	
	return User::checkLogin($inadmin);
	
}

function getUserName()
{
	
	$user = User::getFromSession();
	
	return $user->getdesperson();
	
}

$app = new Slim();

$app->config('debug', true);
//////////////////////////////////////////////////////////////////////////
// ROTA PARA O ARQUIVO PRINCIPAL DO SITE 
//////////////////////////////////////////////////////////////////////////
$app->get('/', function() {
	
	$products = Product::listAll();

	$page = new Page();
	
	$page->setTpl("index", [
		"products"=>Product::checkList($products)
	]);
	
});
// Rota para exibir no site a categoria de um produto
$app->get("/categories/:idcategory", function($idcategory){
	
	$page = (isset($_GET["page"])) ? (int)$_GET["page"] : 1;
	
	$category = new Category();
	
	$category->get((int)$idcategory);
	
	$pagination = $category->getProductsPage($page);
	
	$pages = [];
	
	for($i = 1; $i <= $pagination["pages"]; $i++)
	{
		
		array_push($pages, [
			"link"=>"/categories/".$category->getidcategory()."?page=".$i,
			"page"=>$i
		]);	
		
	}
	
	$page = new Page();

	$page->setTpl("category", [
		"category"=>$category->getValues(),
		"products"=>$pagination["data"],
		"pages"=>$pages
	]);
	
});

// Rota para exibir os detalhes do produto
$app->get("/products/:desurl", function($desurl){
	
	$product = new Product();
	
	$product->getFromURL($desurl);
	
	$page = new Page();
	
	$page->setTpl("product-detail", [
		"product"=>$product->getValues(),
		"categories"=>$product->getCategories()
	]);
	
});

// Rota para o carrinho de compras
$app->get("/cart", function(){
	
	$cart = Cart::getFromSession();
	
	$page = new Page();
	
	$page->setTpl("cart", [
		'cart'=>$cart->getValues(),
		'products'=>$cart->getProducts(),
		'error'=>Cart::getMsgError()
	]);
	
});

// Rota para inserir um produto no carrinho
$app->get("/cart/:idproduct/add", function($idproduct){

	$product = new Product();
	
	$product->get((int)$idproduct);
	
	$cart = Cart::getFromSession();
	
	$qtd = (isset($_GET['qtd'])) ? (int)$_GET['qtd'] : 1;
	
	for($i = 0; $i < $qtd; $i++)
	{
		
		$cart->addProduct($product);
		
	}
	
	header("Location: /cart");
	
	exit;
});

// Rota para remover um produto do carrinho
$app->get("/cart/:idproduct/minus", function($idproduct){

	$product = new Product();
	
	$product->get((int)$idproduct);
	
	$cart = Cart::getFromSession();
	
	$cart->removeProduct($product);
	
	header("Location: /cart");
	
	exit;
});

// Rota para remover todos os produtos de um tipo no carrinho
$app->get("/cart/:idproduct/remove", function($idproduct){

	$product = new Product();
	
	$product->get((int)$idproduct);
	
	$cart = Cart::getFromSession();
	
	$cart->removeProduct($product, true);
	
	header("Location: /cart");
	
	exit;
});

// Rota do envio do formulário para calcular o CEP
$app->post("/cart/freight", function(){
	
	$cart = Cart::getFromSession();
	
	$cart->setFreight($_POST['zipcode']);
	
	header("Location: /cart");
	
	exit;
	
});

// Rota para a página checkout se o cliente não estiver logado
$app->get("/checkout", function(){
	
	User::verifyLogin(false);
	
	$cart = Cart::getFromSession();
	
	$address = new Address();
	
	$page = new Page();
	
	$page->setTpl("checkout", [
		'cart'=>$cart->getValues(),
		'address'=>$address->getValues()
	]);
});

// Rota para o cliente/usuário se logar para finalizar a compra na loja
$app->get("/login", function(){
	
	$page = new Page();
	
	$page->setTpl("login", [
		'error'=>User::getError(),
		'errorRegister'=>User::getErrorRegister(),
		'registerValues'=>(isset($_SESSION['registerValues'])) ? $_SESSION['registerValues'] : ['name'=>'', 'email'=>'', 'phone'=>'']
	]);

	
});

// Rota para verificar o login do usuário
$app->post("/login", function(){
		
	try{
	
		checkLogin(false);
		
		User::login($_POST['login'], $_POST['password']);
	
	}catch(Exception $e){
	
		User::setError($e->getMessage());
	
	}
	
	header("Location: /checkout");
	
	exit;
	
});

// Rota para sair da sessão
$app->get("/logout", function(){
	
	User::logout();
	
	header("Location: /login");
	
	exit;
	
});

$app->post("/register", function(){
	
	$_SESSION['registerValues'] = $_POST;
	
	if(!isset($_POST['name']) || $_POST['name'] == '')
	{
		
		User::setErrorRegister("Preencha o seu nome.");
		header("Location: /login");
		exit;
		
	}
	
	if(!isset($_POST['email']) || $_POST['email'] == '')
	{
		
		User::setErrorRegister("Preencha o seu e-mail.");
		header("Location: /login");
		exit;
		
	}	
	
	if(!isset($_POST['password']) || $_POST['password'] == '')
	{
		
		User::setErrorRegister("Preencha a sua senha.");
		header("Location: /login");
		exit;
		
	}	
	
	if(User::checkLoginExist($_POST['email']) === true)
	{
		
		User::setErrorRegister("E-mail já existe.");
		header("Location: /login");
		exit;		
		
	}
	
	$user = new User();
	
	$user->setData([
		'inadmin'=>0,
		'deslogin'=>$_POST['email'],
		'desperson'=>$_POST['name'],
		'desemail'=>$_POST['email'],
		'despassword'=>$_POST['password'],
		'nrphone'=>$_POST['phone']]);

	$user->save();
	
	User::login($_POST['email'], $_POST['password']);
	
	header("Location: /checkout");
	
	exit;
});

// Rotas para "Esqueci a senha" para usuários comuns
// Rota para a página "esqueci a senha" para usuário comuns
// enviando para a página de login.
// Nesta página tem a entrada para preencher com email.
// Após submeter o formulário é enviado um email para recuperar a senha.
$app->get("/forgot", function(){

	$page = new Page();
	
	$page->setTpl("forgot");
	
});
// Após o envio de email para recuperar a senha,
// Deve redirecionar para a página "forgot-sent.html".
// Nesta página diz que o envio de email foi com sucesso ou não.
$app->post("/forgot", function(){
	
	$user = User::getForgot($_POST["email"], false);
	
	header("Location: /forgot/sent");
	exit;
	
});

$app->get("/forgot/sent", function(){
	
	$page = new Page();
	
	$page->setTpl("forgot-sent");
	
});

// Rota para digitar a nova senha.
$app->get("/forgot/reset", function(){

	$user = User::validForgotDecrypt($_GET["code"]);
	
	$page = new Page();

	$page->setTpl("forgot-reset", array(
		"name"=>$user["desperson"],
		"code"=>$_GET["code"]
	));
	
});

// Rota para a página que diz que a troca da senha foi bem sucedida.
$app->post("/forgot/reset", function(){
	
	$forgot = User::validForgotDecrypt($_POST["code"]);
	
	User::setForgotUsed($forgot["idrecovery"]);
	
	$user = new User();
	
	$user->get((int)$forgot["iduser"]);
	
	$password = password_hash($_POST["password"], PASSWORD_DEFAULT, [
		"cost"=>12
	]);
	
	$user->setPassword($password);
	
	$page = new Page();

	$page->setTpl("forgot-reset-success");	
	
});


$app->get("/profile", function(){
	
	User::verifyLogin(false);
	
	$user = User::getFromSession();

	$page = new Page();
	
	$page->setTpl("profile", [
		'user'=>$user->getValues(),
		'profileMsg'=>User::getSuccess(),
		'profileError'=>User::getError()
	]);
	
});


$app->post("/profile", function(){
	
	User::verifyLogin(false);

	if(!isset($_POST['desperson']) || $_POST['desperson'] === ''){

		User::setError("Preencha o seu nome.");
		header('Location: /profile');
		exit;

	}	

	if(!isset($_POST['desemail']) || $_POST['desemail'] === ''){
		User::setError("Preencha o seu email.");
		header('Location: /profile');
		exit;
	}	
	
	$user = User::getFromSession();
	
	if($_POST['desemail'] !== $user->getdesemail()){

		if(User::checkLoginExist($_POST['desemail']) === true )
		{
			User::setError("Este endereço de e-mail já está cadastrado.");
			header('Location: /profile');
			exit;
		}
	}

	User::setError("");

	$_SESSION['inadmin'] = $user->getinadmin();
	$_SESSION['despassword'] = $user->getdespassword();
	$_SESSION['deslogin'] = $_POST['desemail'];

	$user->setData($_POST);
	
	$user->save();

	User::setSuccess('Dados alterados com sucesso!');
	
	header("Location: /profile");
	
	exit;
	
});

// F I M  PARA ROTAS DO SITE

///////////////////////////////////////////////////////////////////
// ROTA PARA OS ARQUIVOS DO ADMIN
///////////////////////////////////////////////////////////////////

$app->get('/admin', function() {
	
	User::verifyLogin();

	$page = new PageAdmin();
	
	$page->setTpl("index");
	
});

$app->get('/admin/login', function(){

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


$app->run();

 ?>