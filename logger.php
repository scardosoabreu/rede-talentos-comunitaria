<?php
// logger.php
// Função para registrar mensagens de log em um arquivo

function custom_log($message, $level = 'INFO') {
    $log_file = __DIR__ . '/app_log.log'; // O arquivo de log será criado na mesma pasta dos seus scripts PHP
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] [$level] $message" . PHP_EOL; // PHP_EOL para nova linha

    // Tenta escrever no arquivo de log
    // FILE_APPEND: adiciona ao final do arquivo
    // LOCK_EX: impede que outros processos escrevam ao mesmo tempo
    if (file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX) === false) {
        // Se falhar ao escrever no log, tenta imprimir no erro padrão do PHP (STDERR)
        // ou no log de erros do Apache/Nginx se estiver configurado
        error_log("ERRO AO ESCREVER NO LOG PERSONALIZADO: $log_message");
    }
}

// Exemplo de uso (você pode remover ou comentar isso depois)
// custom_log('Teste de mensagem INFO');
// custom_log('Alerta: Algo inesperado aconteceu.', 'WARN');
// custom_log('Erro crítico: A conexão com o BD falhou.', 'ERROR');
?>
