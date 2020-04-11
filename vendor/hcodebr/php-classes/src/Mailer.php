<?php

	namespace Hcode;
	
	use Rain\Tpl;
	
	class Mailer {
		
		const USERNAME  = "fernando.t.araujo2020@gmail.com";
		const PASSWORD  = "agoraVaiFuncionarPorra!!!";
		const NAME_FROM = "ACHEI CHEI HEI EI I!";
		
		private $email;
		
		public function __construct($toAddress, $toNome, $subject, $tplName, $data = array())
		{
			
			$config = array(
				"tpl_dir"       => $_SERVER["DOCUMENT_ROOT"]."/views/email/",
				"cache_dir"     => $_SERVER["DOCUMENT_ROOT"]."/views-cache/",
				"debug"         => false // set to false to improve the speed
		   );

			Tpl::configure( $config );
			
			$tpl = new Tpl;			
			
			foreach($data as $key => $value){
				$tpl->assign($key, $value);
			}
			
			$html = $tpl->draw($tplName, true);
			
			//Create a new PHPMailer instance
			$this->email = new \PHPMailer;


			//Tell PHPMailer to use SMTP
			$this->email->isSMTP();
			//$this->email->isMail();
			
			//Enable SMTP debugging
			// SMTP::DEBUG_OFF = off (for production use) ou 0 (zero)
			// SMTP::DEBUG_CLIENT = client messages ou 1
			// SMTP::DEBUG_SERVER = client and server messages ou 2
			$this->email->SMTPDebug = 0; //SMTP::DEBUG_SERVER; //SMTP::DEBUG_OFF;
			
			//Ask for HTML-friendly debug output
			$this->email->Debugoutput = 'html';

			//Set the hostname of the mail server
			$this->email->Host = 'smtp.gmail.com';
			// use
			// $this->email->Host = gethostbyname('smtp.gmail.com');
			// if your network does not support SMTP over IPv6

			//Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
			$this->email->Port = 587;

			//Set the encryption mechanism to use - STARTTLS or SMTPS
			$this->email->SMTPSecure = 'tls'; //  PHPMailer::ENCRYPTION_STARTTLS;

			//Whether to use SMTP authentication
			$this->email->SMTPAuth = true;

			//Username to use for SMTP authentication - use full email address for gmail
			$this->email->Username = Mailer::USERNAME;

			//Password to use for SMTP authentication
			$this->email->Password = Mailer::PASSWORD;

			//Set who the message is to be sent from
			$this->email->setFrom(Mailer::USERNAME, Mailer::NAME_FROM);

			//Set an alternative reply-to address
			//$this->email->addReplyTo('replyto@example.com', 'First Last');

			//Set who the message is to be sent to
			$this->email->addAddress($toAddress, $toNome);

			//Set the subject line
			$this->email->Subject = $subject;

			//Read an HTML message body from an external file, convert referenced images to embedded,
			//convert HTML into a basic plain-text alternative body
			$this->email->msgHTML($html);

			//Replace the plain text body with one created manually
			$this->email->AltBody = 'This is a plain-text message body';

			//Attach an image file
			//$this->email->addAttachment('images/phpmailer_mini.png');

		}
		
		/*
		
		Esse trecho foi excluído e no lugar se criou uma função
		para enviar o email.
		
		//send the message, check for errors
		if (!$mail->send()) {
			echo 'Mailer Error: '. $mail->ErrorInfo;
		} else {
			echo 'Message sent!';
			//Section 2: IMAP
			//Uncomment these to save your message in the 'Sent Mail' folder.
			#if (save_mail($mail)) {
			#    echo "Message saved!";
			#}
		}
		*/

		//Section 2: IMAP
		//IMAP commands requires the PHP IMAP Extension, found at: https://php.net/manual/en/imap.setup.php
		//Function to call which uses the PHP imap_*() functions to save messages: https://php.net/manual/en/book.imap.php
		//You can use imap_getmailboxes($imapStream, '/imap/ssl', '*' ) to get a list of available folders or labels, this can
		//be useful if you are trying to get this working on a non-Gmail IMAP server.
		public function save_mail($mail)
		{
			//You can change 'Sent Mail' to any other folder or tag
			$path = '{imap.gmail.com:993/imap/ssl}[Gmail]/Sent Mail';

			//Tell your server to open an IMAP connection using the same username and password as you used for SMTP
			$imapStream = imap_open($path, $mail->Username, $mail->Password);

			$result = imap_append($imapStream, $path, $mail->getSentMIMEMessage());
			imap_close($imapStream);

			return $result;
		}
		
		public function send()
		{
			
			return $this->email->send();
			
		}		
		
	}

?>