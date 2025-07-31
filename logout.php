<?php
// logout.php
include 'db_connect.php'; // Já inclui logger.php e inicia a sessão

header('Content-Type: application/json');

custom_log('Usuário deslogado. ID da sessão: ' . ($_SESSION['user_id'] ?? 'N/A'), 'INFO');

session_unset(); // Remove todas as variáveis de sessão
session_destroy(); // Destrói a sessão

echo json_encode(['success' => true, 'message' => 'Logout realizado com sucesso.']);
exit();
?>
