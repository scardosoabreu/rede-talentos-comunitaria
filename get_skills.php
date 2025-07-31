<?php
// get_skills.php
// Retorna a lista de habilidades para popular o dropdown
include 'db_connect.php'; // Já inclui logger.php e inicia a sessão

header('Content-Type: application/json');

$skills = [];
custom_log('Tentando buscar lista de habilidades.', 'INFO');
$result = $conn->query("SELECT id, name FROM skills ORDER BY name ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $skills[] = $row;
    }
    custom_log('Lista de habilidades buscada com sucesso. Quantidade: ' . count($skills), 'INFO');
    echo json_encode(['success' => true, 'skills' => $skills]);
} else {
    $error_message = 'Erro ao buscar habilidades: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
}

$conn->close();
?>
