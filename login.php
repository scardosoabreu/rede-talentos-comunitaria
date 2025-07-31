<?php
// login.php
include 'db_connect.php'; // Já inclui logger.php e inicia a sessão

header('Content-Type: application/json');

// Check if trying to check auth status
if (isset($_GET['check_auth'])) {
    echo json_encode(['loggedIn' => isset($_SESSION['user_id']), 'username' => $_SESSION['username'] ?? '', 'userId' => $_SESSION['user_id'] ?? null]);
    exit();
}

// Handle login POST request
$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    custom_log('Tentativa de login falhou: Usuário ou senha vazios.', 'WARN');
    echo json_encode(['success' => false, 'message' => 'Usuário e senha são obrigatórios.']);
    exit();
}

// Buscar usuário no banco de dados
$stmt = $conn->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
if ($stmt === false) {
    $error_message = 'Erro de preparação da query de login: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $db_username, $password_hash);
$stmt->fetch();

if ($stmt->num_rows > 0 && password_verify($password, $password_hash)) {
    // Login bem-sucedido
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $db_username;
    custom_log("Login bem-sucedido para usuário: $db_username", 'INFO');
    echo json_encode(['success' => true, 'message' => 'Login bem-sucedido!']);
} else {
    custom_log("Tentativa de login falhou (usuário/senha inválidos) para: $username", 'WARN');
    echo json_encode(['success' => false, 'message' => 'Usuário ou senha inválidos.']);
}

$stmt->close();
$conn->close();
?>
