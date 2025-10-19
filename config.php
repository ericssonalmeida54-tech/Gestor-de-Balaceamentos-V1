<?php
// Arquivo de configuração para a conexão com o banco de dados.
// Altere estes valores se o seu setup do MySQL for diferente.

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Usuário padrão do XAMPP
define('DB_PASSWORD', '');     // Senha padrão do XAMPP é vazia
define('DB_NAME', 'gestor_processos');

// Tenta estabelecer a conexão
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verifica a conexão
if($conn === false){
    die("ERRO: Não foi possível conectar ao banco de dados. " . $conn->connect_error);
}

// Define o charset para UTF-8 para suportar acentos e caracteres especiais
$conn->set_charset("utf8");
?>
