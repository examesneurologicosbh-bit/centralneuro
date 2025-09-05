<?php
// Inicializar banco de dados
require_once __DIR__ . '/config.php';

try {
    DatabaseConfig::createTables();
    $db_type = DatabaseConfig::getDbType();
} catch (Exception $e) {
    die("Erro ao inicializar banco de dados: " . $e->getMessage());
}

// Obter a URI da requisição
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// API de status
if ($path === '/api/status') {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'online',
        'database' => $db_type,
        'timestamp' => date('Y-m-d H:i:s'),
        'version' => '2.0.0',
        'features' => [
            'agendamentos' => true,
            'laudos' => true,
            'analises' => true,
            'consultas' => true,
            'ftp_manager' => true
        ]
    ]);
    exit();
}

// Roteamento de APIs
if (strpos($path, '/api/agendamentos') === 0) {
    $_SERVER['PATH_INFO'] = substr($path, strlen('/api/agendamentos'));
    require __DIR__ . '/api/agendamentos.php';
    exit();
}

if (strpos($path, '/api/laudos') === 0) {
    $_SERVER['PATH_INFO'] = substr($path, strlen('/api/laudos'));
    require __DIR__ . '/api/laudos.php';
    exit();
}

if (strpos($path, '/api/analises') === 0) {
    $_SERVER['PATH_INFO'] = substr($path, strlen('/api/analises'));
    require __DIR__ . '/api/analises.php';
    exit();
}

// Novas APIs
if (strpos($path, '/api/consultas') === 0) {
    $_SERVER['PATH_INFO'] = substr($path, strlen('/api/consultas'));
    require __DIR__ . '/api/consultas.php';
    exit();
}

if (strpos($path, '/api/ftp') === 0) {
    $_SERVER['PATH_INFO'] = substr($path, strlen('/api/ftp'));
    require __DIR__ . '/api/ftp_manager.php';
    exit();
}

// Dashboard neurológico
if ($path === '/dashboard' || $path === '/dashboard.html') {
    if (file_exists(__DIR__ . '/frontend/dashboard.html')) {
        readfile(__DIR__ . '/frontend/dashboard.html');
    } else {
        echo '<h1>Dashboard Neurológico</h1><p>Dashboard não encontrado</p>';
    }
    exit();
}

// Servir frontend
if ($path === '/' || $path === '/index.html') {
    if (file_exists(__DIR__ . '/frontend/index.html')) {
        readfile(__DIR__ . '/frontend/index.html');
    } else {
        echo '<h1>Sistema Integrado EEG</h1><p>Frontend não encontrado</p>';
    }
    exit();
}

// Servir arquivos estáticos do frontend
if (strpos($path, '/frontend/') === 0 || in_array(pathinfo($path, PATHINFO_EXTENSION), ['js', 'css', 'html'])) {
    $static_file = __DIR__ . '/frontend' . substr($path, strpos($path, '/frontend/') === 0 ? 9 : 0);
    if (file_exists($static_file) && is_file($static_file)) {
        $mime_type = mime_content_type($static_file);
        header("Content-Type: $mime_type");
        readfile($static_file);
        exit();
    }
}

// Servir app.js diretamente
if ($path === '/app.js') {
    if (file_exists(__DIR__ . '/frontend/app.js')) {
        header('Content-Type: application/javascript');
        readfile(__DIR__ . '/frontend/app.js');
    } else {
        http_response_code(404);
        echo 'app.js não encontrado';
    }
    exit();
}

// 404 para rotas não encontradas
http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Rota não encontrada: ' . $path]);
?>

