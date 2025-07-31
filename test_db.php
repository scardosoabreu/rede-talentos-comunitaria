<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inclua o seu arquivo de conexão
include 'db_connect.php';

if ($conn) {
    echo "Conexão com o banco de dados estabelecida com sucesso!";
    $conn->close();
} else {
    echo "Erro: Conexão com o banco de dados falhou.";
}
?>