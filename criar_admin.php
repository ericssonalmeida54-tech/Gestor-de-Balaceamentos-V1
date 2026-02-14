<?php
// Inclui a configuração do banco de dados
require_once "config.php";

echo "<h1>Script de Criação/Atualização de Administrador</h1>";

// --- DADOS DO NOVO USUÁRIO ---
$username = 'admin'; // O nome de usuário para login
$password_plana = 'admin123'; // A nova senha
$name = 'Administrador Principal';
$role = 'admin';
$id = 'admin_user_01'; // ID único para o admin

// Criptografa a senha usando o método padrão do PHP
$password_hash = password_hash($password_plana, PASSWORD_DEFAULT);

// Verifica se o usuário já existe
$checkSql = "SELECT id FROM users WHERE id = ?";
$checkStmt = $conn->prepare($checkSql);
if ($checkStmt) {
    $checkStmt->bind_param("s", $id);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        die("<p style='color: red; font-weight: bold;'>ERRO DE SEGURANÇA: O usuário com ID 'admin_user_01' já existe.</p><p>Execução abortada.</p>");
    }
    $checkStmt->close();
}

// Prepara o comando SQL para inserir o usuário
$sql = "INSERT INTO users (id, username, password, name, role) VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("<strong>ERRO: Falha ao preparar o comando SQL.</strong> " . htmlspecialchars($conn->error));
}

// Vincula os parâmetros ao comando preparado
$stmt->bind_param("sssss", $id, $username, $password_hash, $name, $role);

// Executa o comando
if ($stmt->execute()) {
    echo "<p style='color: green; font-weight: bold;'>SUCESSO!</p>";
    echo "<p>O usuário administrador foi criado/atualizado no banco de dados.</p>";
    echo "<p>Agora você pode fazer o login com as seguintes credenciais:</p>";
    echo "<ul>";
    echo "<li><strong>Usuário:</strong> " . htmlspecialchars($username) . "</li>";
    echo "<li><strong>Senha:</strong> " . htmlspecialchars($password_plana) . "</li>";
    echo "</ul>";
    echo "<p style='color: red;'><strong>IMPORTANTE:</strong> Por segurança, apague este arquivo (criar_admin_usuario.php) do seu servidor após usá-lo.</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>ERRO: Não foi possível criar o usuário.</p>";
    echo "<p>" . htmlspecialchars($stmt->error) . "</p>";
}

$stmt->close();
$conn->close();
?>
