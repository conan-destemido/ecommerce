<?php

use \Hcode\Model\User;

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

?>