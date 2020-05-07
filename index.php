<?php 

/*

TRECHO DE CÓDIGO DO INDEX.PHP QUANDO FOR SEPARAR/ORGANIZAR AS ROTAS

session_start();
require_once("vendor/autoload.php");

use \Slim\Slim;

$app = new Slim();

$app->config('debug', true);

require_once('functions.php');
require_once('site.php');
require_once('admin.php');
require_once('admin-users.php');
require_once('admin-categories.php');
require_once('admin-products.php');
require_once('admin-orders.php');

$app->run();

*/

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
use \Hcode\Model\Order;
use \Hcode\Model\OrderStatus;

function formatPrice($vlPrice)    
{
	
	return number_format($vlPrice, 2, ",", ".");
	
}

function formatDate($date)
{

	return date('d/m/Y', strtotime($date));

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

function getCartNrQtd()
{

	$cart = Cart::getFromSession();

	$totals = $cart->getProductsTotals();

	return $totals['nrqtd'];

}

function getCartVlSubTotal()
{

	$cart = Cart::getFromSession();

	$totals = $cart->getProductsTotals();

	return formatPrice($totals['vlprice']);
	
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
	
	$address = new Address();

	$cart = Cart::getFromSession();

	if(isset($_GET['zipcode'])){
		$_GET['zipcode'] = $cart->getdeszipcode();
	}

	if(isset($_GET['zipcode']))
	{

		$address->loadFromCEP($_GET['zipcode']);

		$cart->setdeszipcode($_GET['zipcode']);

		$cart->save();

		$cart->getCalculateTotal();

	}

	if(!$address->getdesaddress()) $address->setdesaddress('');
	if(!$address->getdesnumber()) $address->setdesnumber('');
	if(!$address->getdescomplement()) $address->setdescomplement('');
	if(!$address->getdesdistrict()) $address->setdesdistrict('');
	if(!$address->getdescity()) $address->setdescity('');
	if(!$address->getdesstate()) $address->setdesstate('');
	if(!$address->getdescountry()) $address->setdescountry('');
	if(!$address->getdeszipcode()) $address->setdeszipcode('');

	$page = new Page();
	
	$page->setTpl("checkout", [
		'cart'=>$cart->getValues(),
		'address'=>$address->getValues(),
		'products'=>$cart->getProducts(),
		'error'=>Address::getMsgError()
	]);
});

$app->post("/checkout", function(){

	User::verifyLogin(false);

	if(!isset($_POST['zipcode']) || $_POST['zipcode'] === ''){
		Address::setMsgError("Informe o CEP.");
		header('Location: /checkout');
		exit;
	}
	if(!isset($_POST['desaddress']) || $_POST['desaddress'] === ''){
		Address::setMsgError("Informe o endereço.");
		header('Location: /checkout');
		exit;
	}
	if(!isset($_POST['desdistrict']) || $_POST['desdistrict'] === ''){
		Address::setMsgError("Informe o bairro.");
		header('Location: /checkout');
		exit;
	}
	if(!isset($_POST['descity']) || $_POST['descity'] === ''){
		Address::setMsgError("Informe a cidade.");
		header('Location: /checkout');
		exit;
	}
	if(!isset($_POST['desstate']) || $_POST['desstate'] === ''){
		Address::setMsgError("Informe a UF.");
		header('Location: /checkout');
		exit;
	}
	if(!isset($_POST['descountry']) || $_POST['descountry'] === ''){
		Address::setMsgError("Informe o país.");
		header('Location: /checkout');
		exit;
	}

	$user = User::getFromSession();

	$address = new Address(); 

	$_POST['deszipcode'] = $_POST['zipcode'];
	$_POST['idperson'] = $user->getidperson();
	
	$address->setData($_POST);

	$address->save();

	$cart = Cart::getFromSession();

	$cart->getCalculateTotal();

	$order = new Order();

	$order->setData([
		'idcart'=>$cart->getidcart(),
		'idaddress'=>$address->getidaddress(),
		'iduser'=>$user->getiduser(),
		'idstatus'=>OrderStatus::EM_ABERTO,
		'vltotal'=>$cart->getvltotal()
	]);

	$order->save();

	switch ((int)$_POST['payment-method']) {
		case 1:
			header("Location: /order/".$order->getidorder()."/pagseguro");			
			break;
		case 2:
			header("Location: /order/".$order->getidorder()."/paypal");
			break;
	}

	exit;

});

$app->get('/order/:idorder/pagseguro', function($idorder){

	User::verifyLogin(false);

	$order = new Order();

	$order->get((int)$idorder);
	
	$cart = $order->getCart();

	$page = new Page([
		'header'=>false,
		'footer'=>false
	]);

	$page->setTpl('payment-pagseguro', [
		'order'=>$order->getValues(),
		'cart'=>$cart->getValues(),
		'products'=>$cart->getProducts(),
		'phone'=>[
			'areacode'=>substr($order->getnrphone(), 0, 2),
			'number'=>substr($order->getnrphone(), 2, strlen($order->getnrphone()))
		]
	]);
});

$app->get('/order/:idorder/paypal', function($idorder){

	User::verifyLogin(false);

	$order = new Order();

	$order->get((int)$idorder);
	
	$cart = $order->getCart();

	$page = new Page([
		'header'=>false,
		'footer'=>false
	]);

	$page->setTpl('payment-paypal', [
		'order'=>$order->getValues(),
		'cart'=>$cart->getValues(),
		'products'=>$cart->getProducts(),
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

$app->get("/order/:idorder", function($idorder){

	User::verifyLogin(false);

	$order = new Order();

	$order->get((int)$idorder);

	$page = new Page();

	$page->setTpl("payment", [
		'order'=>$order->getValues()
	]);

});

$app->get("/boleto/:idorder", function($idorder){

	User::verifyLogin(false);

	$order = new Order();

	$order->get((int)$idorder);

	// DADOS DO BOLETO PARA O SEU CLIENTE
	$dias_de_prazo_para_pagamento = 10;
	$taxa_boleto = 5.00;
	$data_venc = date("d/m/Y", time() + ($dias_de_prazo_para_pagamento * 86400));  // Prazo de X dias OU informe data: "13/04/2006"; 
	$valor_cobrado = formatPrice($order->getvltotal()); // Valor - REGRA: Sem pontos na milhar e tanto faz com "." ou "," ou com 1 ou 2 ou sem casa decimal
	$valor_cobrado = str_replace(".", "",$valor_cobrado);
	$valor_cobrado = str_replace(",", ".",$valor_cobrado);
	$valor_boleto=number_format($valor_cobrado+$taxa_boleto, 2, ',', '');

	$dadosboleto["nosso_numero"] = $order->getidorder();  // Nosso numero - REGRA: Máximo de 8 caracteres!
	$dadosboleto["numero_documento"] = $order->getidorder();	// Num do pedido ou nosso numero
	$dadosboleto["data_vencimento"] = $data_venc; // Data de Vencimento do Boleto - REGRA: Formato DD/MM/AAAA
	$dadosboleto["data_documento"] = date("d/m/Y"); // Data de emissão do Boleto
	$dadosboleto["data_processamento"] = date("d/m/Y"); // Data de processamento do boleto (opcional)
	$dadosboleto["valor_boleto"] = $valor_boleto; 	// Valor do Boleto - REGRA: Com vírgula e sempre com duas casas depois da virgula

	// DADOS DO SEU CLIENTE
	$dadosboleto["sacado"] = $order->getdesperson();
	$dadosboleto["endereco1"] = utf8_encode($order->getdesaddress() . " " . $order->getdesdistrict());
	$dadosboleto["endereco2"] = $order->city() . " - " . $order->getdesstate() . " -  CEP: " . $order->getdeszipcode();

	// INFORMACOES PARA O CLIENTE
	$dadosboleto["demonstrativo1"] = "Pagamento de Compra na Loja Hcode E-commerce";
	$dadosboleto["demonstrativo2"] = "Taxa bancária - R$ 0,00";
	$dadosboleto["demonstrativo3"] = "";
	$dadosboleto["instrucoes1"] = "- Sr. Caixa, cobrar multa de 2% após o vencimento";
	$dadosboleto["instrucoes2"] = "- Receber até 10 dias após o vencimento";
	$dadosboleto["instrucoes3"] = "- Em caso de dúvidas entre em contato conosco: suporte@hcode.com.br";
	$dadosboleto["instrucoes4"] = "&nbsp; Emitido pelo sistema Projeto Loja Hcode E-commerce - www.hcode.com.br";

	// DADOS OPCIONAIS DE ACORDO COM O BANCO OU CLIENTE
	$dadosboleto["quantidade"] = "";
	$dadosboleto["valor_unitario"] = "";
	$dadosboleto["aceite"] = "";		
	$dadosboleto["especie"] = "R$";
	$dadosboleto["especie_doc"] = "";


	// ---------------------- DADOS FIXOS DE CONFIGURAÇÃO DO SEU BOLETO --------------- //


	// DADOS DA SUA CONTA - ITAÚ
	$dadosboleto["agencia"] = "1690"; // Num da agencia, sem digito
	$dadosboleto["conta"] = "48781";	// Num da conta, sem digito
	$dadosboleto["conta_dv"] = "2"; 	// Digito do Num da conta

	// DADOS PERSONALIZADOS - ITAÚ
	$dadosboleto["carteira"] = "175";  // Código da Carteira: pode ser 175, 174, 104, 109, 178, ou 157

	// SEUS DADOS
	$dadosboleto["identificacao"] = "Hcode Treinamentos";
	$dadosboleto["cpf_cnpj"] = "24.700.731/0001-08";
	$dadosboleto["endereco"] = "Rua Ademar Saraiva Leão, 234 - Alvarenga, 09853-120";
	$dadosboleto["cidade_uf"] = "São Bernardo do Campo - SP";
	$dadosboleto["cedente"] = "HCODE TREINAMENTOS LTDA - ME";

	// NÃO ALTERAR!
	$path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . "res" . DIRECTORY_SEPARATOR . "boletophp" . DIRECTORY_SEPARATOR . "include" . DIRECTORY_SEPARATOR;

	require_once($path . "funcoes_itau.php");
	require_once($path . "layout_itau.php");

	$cart = new Cart();
	$cart->removeSession();

});

$app->get("/profile/orders", function(){

	User::verifyLogin(false);

	$user = User::getFromSession();

	$page = new Page();

	$page->setTpl("profile-orders", [
		'orders'=>$user->getOrders()
	]);

});

$app->get("/profile/orders/:idorder", function($idorder){

	User::verifyLogin(false);

	$order = new Order();

	$order->get((int)$idorder);

	$cart = new Cart();

	$cart->get((int)$order->getidcart());

	$cart->getCalculateTotal();

	$page = new Page();

	$page->setTpl("profile-orders-detail", [
		'order'=>$order->getValues(),
		'cart'=>$cart->getValues(),
		'products'=>$cart->getProducts()
	]);

});

$app->get("/profile/change-password", function(){

	User::verifyLogin(false);

	$page = new Page();

	$page->setTpl("profile-change-password", [
		'changePassError'=>User::getError(),
		'changePassSuccess'=>User::getSuccess()
	]);
});

$app->post("/profile/change-password", function(){

	User::verifyLogin(false);

	if(!isset($_POST['current_pass']) || $_POST['current_pass'] === '')
	{
		User::setError("Digite a senha atual.");
		header("Location: /profile/change-password");
		exit;
	}

	if(!isset($_POST['new_pass']) || $_POST['new_pass'] === '')
	{
		User::setError("Digite a nova senha.");
		header("Location: /profile/change-password");
		exit;
	}

	if(!isset($_POST['new_pass_confirm']) || $_POST['new_pass_confirm'] === '')
	{

		User::setError("Confirme a nova senha."); 
		header("Location: /profile/change-password");
		exit;
	}	

	if($_POST['current_pass'] === $_POST['new_pass'])
	{
		User::setError("A sua nova senha deve ser diferente da atual.");
		header("Location: /profile/change-password");
		exit;
	}	

	if ($_POST['new_pass'] !== $_POST['new_pass_confirm']) 
	{
		User::setError("A senha nova diferente da confirmação");
		header("Location: /profile/change-password");
		exit;
	}		

	$user = User::getFromSession();

	if(!password_verify($_POST['current_pass'], $user->getdespassword())){

		User::setError("A senha está inválida.");
		header("Location: /profile/change-password");
		exit;

	}

	$user->setdespassword($_POST['new_pass']);

	$user->update();

	User::setSuccess("Senha alterada com sucesso.");

	header("Location: /profile/change-password");
	
	

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

	if(User::login($_POST["login"], $_POST["password"]) !== '')
	{
		header('Location: /admin/login');
		exit;
	}
	
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

// Rota para a entrada da troca de senha do usuário
$app->get('/admin/users/:iduser/password', function($iduser){

	User::verifyLogin();

	$user = new User();

	$user->get((int)$iduser);

	$page = new PageAdmin();

	$page->setTpl('users-password', [
		'user'=>$user->getValues(),
		'msgError'=>User::getError(),
		'msgSuccess'=>User::getSuccess()
	]);

	User::setError('');
	User::setSuccess('');

});

// Rota para a troca de senha do usuário
$app->post('/admin/users/:iduser/password', function($iduser){

	User::verifyLogin();

	if(!isset($_POST['despassword']) || $_POST['despassword'] === ''){

		User::setError('Preencha a nova senha.');
		header("Location: /admin/users/$iduser/password");
		exit;
	}

	if(!isset($_POST['despassword-confirm']) || $_POST['despassword-confirm'] === ''){

		User::setError('Preencha a confirmação da nova senha.');
		header("Location: /admin/users/$iduser/password");
		exit;
	}

	if($_POST['despassword'] !== $_POST['despassword-confirm']){

		User::setError('Confirme corretamente as senhas.');
		header("Location: /admin/users/$iduser/password");
		exit;
	}	

	$user = new User();

	$user->get((int)$iduser);

	$user->setPassword(User::getPasswordHash($_POST['despassword']));

	User::setSuccess('Senha alterada com sucesso.');

	header('Location: /admin/users/:iduser/password');

	exit;

});

//
$app->get("/admin/users", function(){
	
	User::verifyLogin();

	$search = (isset($_GET['search'])) ? $_GET['search'] : ''; 

	$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;

	if($search != ''){

		$pagination = User::getPageSearch($search, $page);

	} else {

		$pagination = User::getPage($page);
	}

	$pages = [];

	for($x = 0; $x < $pagination['pages']; $x++)
	{

		array_push($pages, [
			'href'=>'/admin/users?'.http_build_query([
				'page'=>$x+1,
				'search'=>$search
			]),
			'text'=>$x+1
		]);
	}

	$users = User::listAll();
	
	$page = new PageAdmin();
	
	$page->setTpl("users", array(
		"users"=>$pagination['data'],
		'search'=>$search,
		'pages'=>$pages
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

	$search = (isset($_GET['search'])) ? $_GET['search'] : ''; 

	$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;

	if($search != ''){

		$pagination = Category::getPageSearch($search, $page);

	} else {

		$pagination = Category::getPage($page);
	}

	$pages = [];

	for($x = 0; $x < $pagination['pages']; $x++)
	{

		array_push($pages, [
			'href'=>'/admin/categories?'.http_build_query([
				'page'=>$x+1,
				'search'=>$search
			]),
			'text'=>$x+1
		]);
	}	
		
	$page = new PageAdmin();

	$page->setTpl("categories", [
		'categories'=>$pagination['data'],
		'search'=>$search,
		'pages'=>$pages
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

	$search = (isset($_GET['search'])) ? $_GET['search'] : ''; 

	$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;

	if($search != ''){

		$pagination = Product::getPageSearch($search, $page);

	} else {

		$pagination = Product::getPage($page);
	}

	$pages = [];

	for($x = 0; $x < $pagination['pages']; $x++)
	{

		array_push($pages, [
			'href'=>'/admin/products?'.http_build_query([
				'page'=>$x+1,
				'search'=>$search
			]),
			'text'=>$x+1
		]);
	}		

	$page = new PageAdmin();
	
	$page->setTpl("products", [
		'products'=>$pagination['data'],
		'search'=>$search,
		'pages'=>$pages
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

//////////////////////////////////////////////////
// ROTAS PARA OS PEDIDOS DO ADMIN
//////////////////////////////////////////////////

$app->get("/admin/orders/:idorder/status", function($idorder){

	User::verifyLogin();

	$order = new Order();

	$order->get((int)$idorder);

	$page = new PageAdmin();


	$page->setTpl("order-status", [
		'order'=>$order->getValues(),
		'status'=>OrderStatus::listAll(),
		'msgSuccess'=>Order::getSuccess(),
		'msgError'=>Order::getError()
	]);

});

$app->post("/admin/orders/:idorder/status", function($idorder){

	User::verifyLogin();

	if(!isset($_POST['idstatus']) || !(int)$_POST['idstatus'] > 0){
		Order::setError('Informe o status atual.');
		header('Location: /admin/orders/'.$idorder.'/status');
		exit;
	}

	$order = new Order();

	$order->get((int)$idorder);

	$order->setidstatus((int)$_POST['idstatus']);

	$order->save();

	Order::setSuccess('Status atualizado.');

	header('Location: /admin/orders/'.$idorder.'/status');

	exit;

});

$app->get("/admin/orders/:idorder/delete", function($idorder){

	User::verifyLogin();

	$order = new Order();

	$order->get((int)$idorder);

	$order->delete();

	header("Location: /admin/orders");

	exit;

});

$app->get('/admin/orders/:idorder', function($idorder){

	User::verifyLogin();

	$order = new Order();

	$order->get((int)$idorder);

	$cart = $order->getCart();

	$page = new PageAdmin();

	$page->setTpl("order", [
		'order'=>$order->getValues(),
		'cart'=>$cart->getValues(),
		'products'=>$cart->getProducts()
	]);

});

$app->get("/admin/orders", function(){

	User::verifyLogin();

	$search = (isset($_GET['search'])) ? $_GET['search'] : ''; 

	$page = (isset($_GET['page'])) ? (int)$_GET['page'] : 1;

	if($search != ''){

		$pagination = Order::getPageSearch($search, $page);

	} else {

		$pagination = Order::getPage($page);
	}

	$pages = [];

	for($x = 0; $x < $pagination['pages']; $x++)
	{

		array_push($pages, [
			'href'=>'/admin/orders?'.http_build_query([
				'page'=>$x+1,
				'search'=>$search
			]),
			'text'=>$x+1
		]);
	}		

	$page = new PageAdmin();

	$page->setTpl("orders", [
		'orders'=>$pagination['data'],
		'search'=>$search,
		'pages'=>$pages
	]);
});

$app->run();

 ?>