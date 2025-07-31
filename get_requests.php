<?php
// get_requests.php
include 'db_connect.php'; // Já inclui logger.php e inicia a sessão

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    custom_log('Acesso não autorizado a get_requests.php (usuário não logado).', 'WARN');
    echo json_encode(['success' => false, 'message' => 'Não autorizado. Faça login.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$requests = [];

custom_log("Tentando buscar solicitações para user_id: $user_id", 'INFO');

// Fetch requests where user is the requester OR the provider
// Adicionando LEFT JOINs para buscar email e telefone de contato do requerente e do provedor
$stmt = $conn->prepare("
    SELECT 
        er.id, er.requester_id, er.provider_id, er.message, er.status, er.created_at, er.accepted_at,
        s.name AS skill_name,
        req.username AS requester_username, req.contact_email AS requester_contact_email, req.contact_phone AS requester_contact_phone,
        prov.username AS provider_username, prov.contact_email AS provider_contact_email, prov.contact_phone AS provider_contact_phone
    FROM exchange_requests er
    LEFT JOIN skills s ON er.requested_skill_id = s.id
    LEFT JOIN users req ON er.requester_id = req.id
    LEFT JOIN users prov ON er.provider_id = prov.id
    WHERE er.requester_id = ? OR er.provider_id = ?
    ORDER BY er.created_at DESC
");
if ($stmt === false) {
    $error_message = 'Erro de preparação da query get_requests: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

$stmt->close();
$conn->close();
custom_log('Solicitações buscadas com sucesso. Quantidade: ' . count($requests), 'INFO');
echo json_encode(['success' => true, 'requests' => $requests]);
?>
