<?php

	namespace Hcode\Model;

	use \Hcode\DB\Sql;
	use \Hcode\Model;
	use \Hcode\Mailer;
	
	define("SECRET_IV", pack("a16", "BomMercadoAchei"));
	define("SECRET"   , pack("a16", "BomMercadoAchei"));	
	
	class User extends Model {
		
		const SESSION = "User";
		
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
				
				$_SESSION[User::SESSION] = $user->getValues();
				
				return $user;
				
			}else{
				
				throw new \Exception("Usuário inexistente ou senha inválida. (senha inválida)");
				
			}
			

		}
		
		public static function verifyLogin($inadmin = true)
		{

			if(
				!isset($_SESSION[User::SESSION])
				||
				!$_SESSION[User::SESSION]
				||
				!(int)$_SESSION[User::SESSION]["iduser"] > 0
				||
				(bool)$_SESSION[User::SESSION]["inadmin"] !== $inadmin
			){
				header("Location: /admin/login");
				exit;
			}
		}
		
		public static function logout()
		{
			
			$_SESSION[User::SESSION] = NULL;
			
		}
		
		public static function listAll(){
			
			$sql = new Sql();
			
			return $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson");
			
		}
		
		public function save()
		{
			
			$sql = new Sql();
			
			$results = $sql->select("CALL sp_users_save(:pdesperson, :pdeslogin, :pdespassword, :pdesemail, :pnrphone, :pinadmin)", 
				array(
				":pdesperson"=>$this->getdesperson(),
				":pdeslogin"=>$this->getdeslogin(),
				":pdespassword"=>$this->getdespassword(),
				":pdesemail"=>$this->getdesemail(),
				":pnrphone"=>$this->getnrphone(),
				":pinadmin"=>$this->getinadmin()
			)); 

			$this->setData($results[0]);
		}
		
		public function get($iduser)
		{
			
			$sql = new Sql();
			
			$results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser = :iduser",
				array(
				":iduser"=>$iduser
			));
			
			$this->setData($results[0]);
			
		}
		
		public function update()
		{
			
			$sql = new Sql();
			
			$results = $sql->select("CALL sp_usersupdate_save(:piduser, :pdesperson, :pdeslogin, :pdespassword, :pdesemail, :pnrphone, :pinadmin)", 
				array(
				":piduser"=>$this->getiduser(),
				":pdesperson"=>$this->getdesperson(),
				":pdeslogin"=>$this->getdeslogin(),
				":pdespassword"=>$this->getdespassword(),
				":pdesemail"=>$this->getdesemail(),
				":pnrphone"=>$this->getnrphone(),
				":pinadmin"=>$this->getinadmin()
			)); 

			$this->setData($results[0]);			
			
		}
		
		public function delete()
		{
			
			$sql = new Sql();
			
			$sql->query("CALL sp_users_delete(:piduser)", array(
				":piduser"=>$this->getiduser()
			));
			
		}
		
		public static function getForgot($email){
			
			$sql = new Sql();
			
			$results = $sql->select("
			SELECT * 
			FROM db_ecommerce.tb_persons a
			INNER JOIN tb_users b USING(idperson)
			WHERE a.desemail = :email;
			", array(
				":email"=>$email
			));
			
			if(count($results[0]) === 0){
				
				throw new \Exception("Não foi possível recuperar a sua senha.");
				
			}else{
				
				$data = $results[0];
				
				$results2 = $sql->select("CALL sp_userspasswordsrecoveries_create(:piduser, :pdesip)", array(
					":piduser"=>$data["iduser"],
					":pdesip"=>$_SERVER["REMOTE_ADDR"]
				));
				
				if(count($results2[0]) === 0)
				{
				
					throw new \Exception("Não foi possível recuperar a sua senha.");
				
				}else{
					
					$dataRecovery = $results2[0];
					
					/*
					Descontinuado a partir da versão do PHP 7.2.x
					$code = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, User::SECRET, $dataRecovery["idrecovery"], 
						MCRYPT_MODE_ECB));
					*/

					$code = openssl_encrypt(
						$dataRecovery["idrecovery"],
						"AES-128-CBC",
						SECRET,
						0,
						SECRET_IV
					);
					
					/* Para descriptografar em openssl 
						$code = openssl_decrypt($code, "AES-128-CBC", SECRET, 0, SECRET_IV);
					*/

					$link = "http://www.hcodecommerce.com.br/admin/forgot/reset?code=$code";
					
					$mailer = new Mailer($data["desemail"], $data["desperson"], "Redefinir senha", "forgot",
						array(
							"name"=>$data["desperson"],
							"link"=>$link
					));
					
					$mailer->send();
					
					return $data;
					
				}
			}
		}
		
		public static function validForgotDecrypt($code)
		{
			
			$idrecovery = openssl_decrypt($code, "AES-128-CBC", SECRET, 0, SECRET_IV);
		
			$sql = new Sql();
			
			$results = $sql->select("
				SELECT *
				FROM db_ecommerce.tb_userspasswordsrecoveries a
				INNER JOIN db_ecommerce.tb_users b USING(iduser)
				INNER JOIN db_ecommerce.tb_persons c USING(idperson)
				WHERE	
					a.idrecovery = :idrecovery
					AND
					a.dtrecovery IS NULL
					AND 
					DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW();
			", array(
				":idrecovery"=>$idrecovery
			));
			
			if(count($results) === 0)
			{
				
				throw new \Exception("Não foi possível recuperar a senha.");
				
			}else{
				
				return $results[0];
				
			}
		}

		public static function setForgotUsed($idrecovery)
		{

			$sql = new Sql();
			
			$sql->query("UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery",
				array(
				":idrecovery"=>$idrecovery
			));
			
		}
		
		public function setPassword($password)
		{
			
			$sql = new Sql();
			
			$sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser",
			array(
				":password"=>$password,
				":iduser"=>$this->getiduser()
			));
			
			
			
		}
	}
?>