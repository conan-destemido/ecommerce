<?php

	namespace Hcode\Model;

	use \Hcode\DB\Sql;
	use \Hcode\Model;
	
	const SESSION = "User";
	
	class User extends Model {
		
		public static function login($login, $password)
		{
			$sql = new Sql();
			
			$results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :LOGIN", array(
				":LOGIN"=>$login
			));
			
			if(count($results) === 0)
			{
				throw new \Exception("Usuário inexistente ou senha inválida. (login inválido)");
			}
			
			$data = $results[0];
			
			if(password_verify($password, $data["despassword"]) === true)
			{
				
				$user = new User();
				
				$user->setData($data);
				
				$_SESSION[SESSION] = $user->getValues();
				
				return $user;
				
			}else{
				
				throw new \Exception("Usuário inexistente ou senha inválida. (senha inválida)");
				
			}
			

		}
		
		public static function verifyLogin($inadmin = true)
		{

			if(
				!isset($_SESSION[SESSION])
				||
				!$_SESSION[SESSION]
				||
				!(int)$_SESSION[SESSION]["iduser"] > 0
				||
				(bool)$_SESSION[SESSION]["inadmin"] !== $inadmin
			){
				header("Location: /admin/login");
				exit;
			}
		}
		
		public static function logout()
		{
			$_SESSION[SESSION] = NULL;
		}
	}
?>