<?php
// get_talents.php
include 'db_connect.php'; // Já inclui logger.php e inicia a sessão

header('Content-Type: application/json');

$talents = [];

custom_log('Tentando buscar talentos para o mapa.', 'INFO');

// Reconfirmando a query e as colunas, assumindo que profile_update.php já as lida corretamente
$stmt = $conn->prepare("SELECT id, username, city, state, location_text, latitude, longitude, availability_hours, availability_period, seeking_text FROM users WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
if ($stmt === false) {
    $error_message = 'Erro de preparação da query get_talents: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $talent = $row;
    $talent['skills'] = [];

    // Fetch skills for each talent
    $skills_stmt = $conn->prepare("SELECT s.id, s.name FROM skills s JOIN user_skills us ON s.id = us.skill_id WHERE us.user_id = ?");
    if ($skills_stmt === false) {
        $error_message = "Erro ao preparar query de skills para o talento " . $row['username'] . ": " . $conn->error;
        custom_log($error_message, 'ERROR');
        // Continua, mas este talento não terá as skills populadas
    } else {
        $skills_stmt->bind_param("i", $row['id']);
        $skills_stmt->execute();
        $skills_result = $skills_stmt->get_result();
        while ($skill_row = $skills_result->fetch_assoc()) {
            $talent['skills'][] = $skill_row;
        }
        $skills_stmt->close();
    }
    $talents[] = $talent;
}

$stmt->close();
$conn->close();
custom_log('Talentos buscados com sucesso. Quantidade: ' . count($talents), 'INFO');
echo json_encode(['success' => true, 'talents' => $talents]);
?>
