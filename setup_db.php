<?php
// setup_db.php
// Este script DEVE SER EXECUTADO APENAS UMA VEZ para configurar o banco de dados e as habilidades iniciais.
// Após a execução bem-sucedida, você PODE REMOVÊ-LO ou RENOMEÁ-LO por segurança.

// Inclua o arquivo de conexão com o banco de dados
include 'db_connect.php';

$message = '';
$error = '';

// Desabilitar chaves estrangeiras temporariamente para facilitar a exclusão em cascata
$conn->query("SET FOREIGN_KEY_CHECKS = 0;");

// SQL para DROPAR (excluir) tabelas existentes - ÚTIL PARA REFAZER A ESTRUTURA!
// Se você já tem dados e não quer perdê-los, remova ou comente estas linhas.
// Como este é um app em desenvolvimento, é útil para testar a estrutura.
$conn->query("DROP TABLE IF EXISTS exchange_requests;");
$conn->query("DROP TABLE IF EXISTS user_skills;");
$conn->query("DROP TABLE IF EXISTS skills;");
$conn->query("DROP TABLE IF EXISTS users;");

// SQL para criar as tabelas (ATUALIZADAS com novas colunas e sem colunas de endereço completo)
$sql_users = "CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    -- Campos de endereço simplificados: apenas cidade e estado
    city VARCHAR(100),
    state VARCHAR(2),
    location_text VARCHAR(255), -- Para referência de mapa, pode ser bairro ou texto livre
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    availability_hours INT,
    availability_period ENUM('semana', 'mes'),
    seeking_text TEXT,
    contact_email VARCHAR(100),
    contact_phone VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql_skills = "CREATE TABLE skills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL
)";

$sql_user_skills = "CREATE TABLE user_skills (
    user_id INT,
    skill_id INT,
    PRIMARY KEY (user_id, skill_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
)";

$sql_exchange_requests = "CREATE TABLE exchange_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    requester_id INT NOT NULL,
    provider_id INT NOT NULL,
    requested_skill_id INT, -- A habilidade específica que o requerente busca do provedor
    message TEXT,
    status ENUM('pending', 'accepted', 'rejected', 'cancelled') DEFAULT 'pending' NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at DATETIME NULL,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_skill_id) REFERENCES skills(id) ON DELETE SET NULL
)";


// Executar criação das tabelas
if ($conn->query($sql_users) === TRUE) {
    $message .= "Tabela 'users' criada com sucesso.<br>";
} else {
    $error .= "Erro ao criar tabela 'users': " . $conn->error . "<br>";
}

if ($conn->query($sql_skills) === TRUE) {
    $message .= "Tabela 'skills' criada com sucesso.<br>";
} else {
    $error .= "Erro ao criar tabela 'skills': " . $conn->error . "<br>";
}

if ($conn->query($sql_user_skills) === TRUE) {
    $message .= "Tabela 'user_skills' criada com sucesso.<br>";
} else {
    $error .= "Erro ao criar tabela 'user_skills': " . $conn->error . "<br>";
}

if ($conn->query($sql_exchange_requests) === TRUE) {
    $message .= "Tabela 'exchange_requests' criada com sucesso.<br>";
} else {
    $error .= "Erro ao criar tabela 'exchange_requests': " . $conn->error . "<br>";
}

// Inserir habilidades iniciais (lista expandida)
$initial_skills = [
    "Pedreiro", "Eletricista", "Encanador", "Pintor", "Marceneiro", "Serralheiro",
    "Gesseiro", "Vidraceiro", "Jardineiro", "Zelador", "Diarista", "Passadeira",
    "Cuidador de idosos", "Babá", "Dog walker", "Lavador de carros", "Auxiliar de limpeza",
    "Catador de recicláveis", "Enfermeiro domiciliar", "Cuidadores de animais domésticos",
    "Cozinheiro", "Padeiro", "Confeiteiro", "Marmiteiro", "Churrasqueiro", "Garçom",
    "Doceira", "Salgadeira", "Barista", "Personal chef", "Cabeleireiro", "Manicure",
    "Maquiador", "Massoterapeuta", "Designer de sobrancelhas", "Depilador",
    "Esteticista", "Terapeuta holístico", "Tatuador", "Instrutor de yoga",
    "Designer gráfico", "Social media", "Fotógrafo", "Editor de vídeo",
    "Programador", "Redator", "Web designer", "Assistente virtual",
    "Professor particular", "Artesão", "Aulas de Violão", "Culinária", "Reparos Elétricos",
    "Costura", "Aulas de Idiomas", "Aconselhamento Financeiro", "Jardinagem", "Fotografia", "Edição de Vídeo", "Marketing Digital", "Instalação de Software", "Passear com Cachorro", "Cuidar de Crianças"
];

foreach ($initial_skills as $skill_name) {
    // Usar INSERT IGNORE para evitar erros se a habilidade já existir de execuções anteriores
    $stmt_insert = $conn->prepare("INSERT IGNORE INTO skills (name) VALUES (?)");
    $stmt_insert->bind_param("s", $skill_name);
    if ($stmt_insert->execute()) {
        if ($stmt_insert->affected_rows > 0) { // Verifica se uma nova linha foi realmente inserida
            $message .= "Habilidade '$skill_name' adicionada.<br>";
        }
    } else {
        $error .= "Erro ao adicionar habilidade '$skill_name': " . $conn->error . "<br>";
    }
    $stmt_insert->close();
}

// Reabilitar chaves estrangeiras
$conn->query("SET FOREIGN_KEY_CHECKS = 1;");

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuração do Banco de Dados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-xl shadow-2xl w-full max-w-md border border-gray-200 text-center">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Status da Configuração do Banco de Dados</h1>
        <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4 rounded-md text-left">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4 rounded-md text-left">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <p class="text-sm text-gray-600">
            Se tudo estiver correto, você pode agora acessar <a href="index.html" class="text-blue-600 hover:underline">index.html</a>.
            Por segurança, é altamente recomendado **REMOVER ou RENOMEAR** este arquivo (`setup_db.php`) do seu servidor após a execução.
        </p>
    </div>
</body>
</html>
