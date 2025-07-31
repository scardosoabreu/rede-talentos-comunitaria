<?php
// update_request_status.php
include 'db_connect.php'; // Já inclui logger.php e inicia a sessão

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    custom_log('Acesso não autorizado a update_request_status.php (usuário não logado).', 'WARN');
    echo json_encode(['success' => false, 'message' => 'Não autorizado. Faça login.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$request_id = $input['request_id'] ?? null;
$new_status = $input['status'] ?? null; // 'accepted' or 'rejected'

if (empty($request_id) || !in_array($new_status, ['accepted', 'rejected'])) {
    custom_log("Tentativa de atualizar status de solicitação falhou: Dados inválidos. Request ID: $request_id, Status: $new_status. User ID: $user_id", 'WARN');
    echo json_encode(['success' => false, 'message' => 'Dados inválidos para atualizar a solicitação.']);
    exit();
}

// Verify that the user is the provider of the request and status is pending
$stmt_check = $conn->prepare("SELECT provider_id, status FROM exchange_requests WHERE id = ?");
if ($stmt_check === false) {
    $error_message = 'Erro de preparação da query check_provider_status: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}
$stmt_check->bind_param("i", $request_id);
$stmt_check->execute();
$stmt_check->store_result();
$stmt_check->bind_result($provider_id, $current_status);
$stmt_check->fetch();

if ($stmt_check->num_rows === 0 || $provider_id != $user_id || $current_status !== 'pending') {
    custom_log('Permissão negada ou status inválido para atualizar solicitação. Request ID: ' . $request_id . ', User ID: ' . $user_id . ', Provider ID no DB: ' . $provider_id . ', Status Atual no DB: ' . $current_status, 'WARN');
    echo json_encode(['success' => false, 'message' => 'Você não tem permissão para alterar esta solicitação ou ela não está pendente.']);
    $stmt_check->close();
    $conn->close();
    exit();
}
$stmt_check->close();

$update_time = ($new_status === 'accepted') ? date('Y-m-d H:i:s') : null;

$stmt = $conn->prepare("UPDATE exchange_requests SET status = ?, accepted_at = ? WHERE id = ?");
if ($stmt === false) {
    $error_message = 'Erro de preparação da query update_request_status: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

$stmt->bind_param("ssi", $new_status, $update_time, $request_id);

if ($stmt->execute()) {
    custom_log("Status da solicitação $request_id atualizado para $new_status por user_id: $user_id", 'INFO');
    echo json_encode(['success' => true, 'message' => 'Status da solicitação atualizado com sucesso para ' . $new_status . '.']);
} else {
    $error_message = 'Erro ao atualizar status da solicitação (execução da query): ' . $stmt->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
}

$stmt->close();
$conn->close();
?>
