<?php
// profile_update.php
include 'db_connect.php'; // Já inclui logger.php e inicia a sessão

// TEMPORARIAMENTE: Ativar exibição de erros na página para depuração direta
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    custom_log('Acesso não autorizado a profile_update.php (usuário não logado).', 'WARN');
    echo json_encode(['success' => false, 'message' => 'Não autorizado. Faça login.']);
    exit();
}

$user_id = $_SESSION['user_id'];
custom_log("Acesso a profile_update.php para user_id: $user_id", 'INFO');

// Handle GET request to retrieve user profile
if (isset($_GET['get_profile'])) {
    $stmt = $conn->prepare("SELECT username, email, city, state, location_text, latitude, longitude, availability_hours, availability_period, seeking_text, contact_email, contact_phone FROM users WHERE id = ?");
    if ($stmt === false) {
        $error_message = 'Erro de preparação da query GET profile: ' . $conn->error;
        custom_log($error_message, 'ERROR');
        echo json_encode(['success' => false, 'message' => $error_message]);
        exit();
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    if ($user_data) {
        custom_log("Dados do perfil obtidos para user_id: $user_id. Dados: " . json_encode($user_data), 'INFO'); // Log dos dados obtidos
        // Fetch user's skills
        $skills_stmt = $conn->prepare("SELECT skill_id FROM user_skills WHERE user_id = ?");
        if ($skills_stmt === false) {
            $error_message = 'Erro de preparação da query GET user_skills: ' . $conn->error;
            custom_log($error_message, 'ERROR');
            // Continua, mas o frontend não terá as skills
        } else {
            $skills_stmt->bind_param("i", $user_id);
            $skills_stmt->execute();
            $skills_result = $skills_stmt->get_result();
            $user_data['skills'] = [];
            while ($row = $skills_result->fetch_assoc()) {
                $user_data['skills'][] = $row;
            }
            $skills_stmt->close();
            custom_log("Habilidades do perfil obtidas para user_id: $user_id.", 'INFO');
        }
        echo json_encode(['success' => true, 'user' => $user_data]);
    } else {
        custom_log("Perfil do usuário $user_id não encontrado no DB.", 'WARN');
        echo json_encode(['success' => false, 'message' => 'Perfil do usuário não encontrado.']);
    }
    $conn->close();
    exit();
}

// Handle POST request to update user profile
$input = json_decode(file_get_contents('php://input'), true);

$city = $input['city'] ?? null;
$state = $input['state'] ?? null;
$location_text = $input['location_text'] ?? null;
$latitude = $input['latitude'] ?? null;
$longitude = $input['longitude'] ?? null;
$availability_hours = $input['availability_hours'] ?? null;
$availability_period = $input['availability_period'] ?? null;
$seeking_text = $input['seeking_text'] ?? null;
$contact_email = $input['contact_email'] ?? null;
$contact_phone = $input['contact_phone'] ?? null;
$skills = $input['skills'] ?? []; // Array of skill IDs

custom_log("Tentativa de atualização de perfil para user_id: $user_id. Dados recebidos do frontend (JSON decode): " . json_encode($input), 'INFO');

// Validar e converter latitude/longitude para float (decimal) antes de enviar para o DB
// Se o valor do JS for uma string vazia ou nulo, o is_numeric retornará false, e será setado como null.
$latitude = is_numeric($latitude) ? (float)$latitude : null;
$longitude = is_numeric($longitude) ? (float)$longitude : null;

custom_log("Valores processados para UPDATE: City: " . ($city ?? 'NULL') . ", State: " . ($state ?? 'NULL') . ", LocText: " . ($location_text ?? 'NULL') . ", Lat: " . ($latitude ?? 'NULL') . ", Lon: " . ($longitude ?? 'NULL') . ", AvailHours: " . ($availability_hours ?? 'NULL') . ", AvailPeriod: " . ($availability_period ?? 'NULL') . ", Seeking: " . ($seeking_text ?? 'NULL') . ", Email: " . ($contact_email ?? 'NULL') . ", Phone: " . ($contact_phone ?? 'NULL') . ", UserID: $user_id", 'DEBUG');

$stmt = $conn->prepare("UPDATE users SET city = ?, state = ?, location_text = ?, latitude = ?, longitude = ?, availability_hours = ?, availability_period = ?, seeking_text = ?, contact_email = ?, contact_phone = ? WHERE id = ?");
if ($stmt === false) {
    $error_message = 'Erro de preparação da query UPDATE profile: ' . $conn->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
    exit();
}

// A ordem dos tipos e parâmetros DEVE CORRESPONDER EXATAMENTE à query UPDATE
// 'sssddissssi' -> s=city, s=state, s=location_text, d=latitude, d=longitude, i=availability_hours,
// s=availability_period, s=seeking_text, s=contact_email, s=contact_phone, i=user_id
$stmt->bind_param("sssddissssi", 
    $city, 
    $state,
    $location_text,
    $latitude, 
    $longitude,
    $availability_hours,
    $availability_period,
    $seeking_text,
    $contact_email,
    $contact_phone,
    $user_id
);

if ($stmt->execute()) {
    custom_log("Perfil de user_id: $user_id atualizado no DB. Affected rows: " . $stmt->affected_rows, 'INFO'); // Log affected rows
    // Update user skills (delete all existing and insert new ones)
    $conn->begin_transaction();
    try {
        $delete_stmt = $conn->prepare("DELETE FROM user_skills WHERE user_id = ?");
        $delete_stmt->bind_param("i", $user_id);
        if ($delete_stmt->execute()) {
            custom_log("Habilidades antigas de user_id: $user_id deletadas. Affected rows: " . $delete_stmt->affected_rows, 'INFO'); // Log affected rows
        } else {
            custom_log("Erro ao deletar habilidades antigas de user_id: $user_id: " . $delete_stmt->error, 'ERROR');
        }
        $delete_stmt->close();

        if (!empty($skills)) {
            $insert_skill_stmt = $conn->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?, ?)");
            if ($insert_skill_stmt === false) {
                 throw new mysqli_sql_exception('Erro de preparação da query insert user_skills: ' . $conn->error);
            }
            foreach ($skills as $skill_id) {
                $insert_skill_stmt->bind_param("ii", $user_id, $skill_id);
                if (!$insert_skill_stmt->execute()) {
                    throw new mysqli_sql_exception('Erro ao inserir habilidade ' . $skill_id . ' para user_id: ' . $user_id . ': ' . $insert_skill_stmt->error);
                }
            }
            $insert_skill_stmt->close();
            custom_log("Novas habilidades inseridas para user_id: $user_id. Skills: " . json_encode($skills), 'INFO'); // Log das skills inseridas
        } else {
            custom_log("Nenhuma nova habilidade para inserir para user_id: $user_id.", 'INFO');
        }
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Perfil atualizado com sucesso!']);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        $error_message = 'Erro na transação de habilidades: ' . $e->getMessage();
        custom_log($error_message, 'ERROR');
        echo json_encode(['success' => false, 'message' => $error_message]);
    }
} else {
    $error_message = 'Erro na execução da query UPDATE profile: ' . $stmt->error;
    custom_log($error_message, 'ERROR');
    echo json_encode(['success' => false, 'message' => $error_message]);
}

$stmt->close();
$conn->close();
?>
