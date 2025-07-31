<?php
// send_exchange_request.php
include 'db_connect.php'; // Já inclui logger.php e inicia a sessão

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    custom_log('Acesso não autorizado a send_exchange_request.php (usuário não logado).', 'WARN');
    echo json_encode(['success' => false, 'message' => 'Você precisa estar logado para enviar solicitações.']);
    exit();
}

$requester_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$provider_id = $input['provider_id'] ?? null;
$requested_skill_id = $input['requested_skill_id'] ?? null;
$message = $input['message'] ?? '';

if (empty($provider_id) || empty($message)) {
    custom_log('Tentativa de enviar solicitação falhou: ID do provedor ou mensagem vazia. Requester ID: ' . $requester_id, 'WARN');
    echo json_encode(['success' => false, 'message' => 'O ID do talento e a mensagem são obrigatórios.']);
    exit();
}

if ($requester_id == $provider_id) {
    custom_log('Tentativa de enviar solicitação para si mesmo. Requester ID: ' . $requester_id, 'WARN');
    echo json_encode(['success' => false, 'message' => 'Você não pode enviar uma solicitação para si mesmo.']);
    exit();
}

// Check for existing pending request
$stmt_check = $conn->prepare("SELECT id FROM exchange_requests WHERE requester_id = ? AND provider_id = ? AND status = 'pending'");
if ($stmt_check === false) {
    $error_message = 'Erro de preparação da query check_existing_request: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}
$stmt_check->bind_param("ii", $requester_id, $provider_id);
$stmt_check->execute();
$stmt_check->store_result();
if ($stmt_check->num_rows > 0) {
    custom_log('Solicitação pendente duplicada detectada para Requester ID: ' . $requester_id . ', Provider ID: ' . $provider_id, 'WARN');
    echo json_encode(['success' => false, 'message' => 'Você já tem uma solicitação PENDENTE para este talento.']);
    $stmt_check->close();
    $conn->close();
    exit();
}
$stmt_check->close();


$stmt = $conn->prepare("INSERT INTO exchange_requests (requester_id, provider_id, requested_skill_id, message, status) VALUES (?, ?, ?, ?, 'pending')");
if ($stmt === false) {
    $error_message = 'Erro de preparação da query send_exchange_request: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

// requested_skill_id might be null, so adjust bind_param
if ($requested_skill_id === null) {
    $stmt->bind_param("iis", $requester_id, $provider_id, $message); // No skill ID provided
} else {
    $stmt->bind_param("iiis", $requester_id, $provider_id, $requested_skill_id, $message);
}


if ($stmt->execute()) {
    custom_log("Solicitação de troca enviada de user_id: $requester_id para user_id: $provider_id", 'INFO');
    echo json_encode(['success' => true, 'message' => 'Solicitação de troca enviada com sucesso!']);
} else {
    $error_message = 'Erro ao enviar solicitação (execução da query): ' . $stmt->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
}

$stmt->close();
$conn->close();
?>
