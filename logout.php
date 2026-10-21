<?php
session_start();

// Apaga todos os dados da sessão (quem estava logado deixa de existir)
$_SESSION = [];
session_destroy();

// Volta para a tela de login
header("Location: login.php");
exit;
