<?php
// register.php
include 'db_connect.php'; // Já inclui logger.php e inicia a sessão

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$email = $input['email'] ?? '';
$password = $input['password'] ?? '';
$city = $input['city'] ?? null;
$state = $input['state'] ?? null;

if (empty($username) || empty($email) || empty($password)) {
    custom_log('Tentativa de registro falhou: Campos obrigatórios vazios.', 'WARN');
    echo json_encode(['success' => false, 'message' => 'Usuário, Email e Senha são obrigatórios.']);
    exit();
}

// Hash da senha para segurança
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// Inserir usuário no banco de dados com novos campos de endereço
$stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, city, state) VALUES (?, ?, ?, ?, ?)");
if ($stmt === false) {
    $error_message = 'Erro de preparação da query de registro: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}
$stmt->bind_param("sssss", $username, $email, $password_hash, $city, $state);

if ($stmt->execute()) {
    custom_log("Novo usuário registrado: $username", 'INFO');
    echo json_encode(['success' => true, 'message' => 'Registro bem-sucedido! Faça login.']);
} else {
    if ($conn->errno == 1062) { // Duplicate entry error code
        custom_log("Tentativa de registro com usuário/email duplicado: $username", 'WARN');
        echo json_encode(['success' => false, 'message' => 'Usuário ou email já cadastrado.']);
    } else {
        $error_message = 'Erro na execução da query de registro: ' . $stmt->error;
        custom_log($error_message, 'ERROR');
        echo json_encode(['success' => false, 'message' => $error_message]);
    }
}

$stmt->close();
$conn->close();
?>
