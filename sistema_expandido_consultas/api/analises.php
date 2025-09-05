<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../models/AnaliseEEG.php';

$analise = new AnaliseEEG();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if (preg_match('/^\/(\d+)$/', $path, $matches)) {
                // GET /analises/{id}
                $id = $matches[1];
                $resultado = $analise->buscarPorId($id);
                if (!$resultado) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Análise não encontrada']);
                    exit();
                }
                echo json_encode($resultado);
            } elseif (preg_match('/^\/laudo\/(\d+)$/', $path, $matches)) {
                // GET /analises/laudo/{laudo_id}
                $laudo_id = $matches[1];
                echo json_encode($analise->buscarPorLaudo($laudo_id));
            } elseif ($path === '/estatisticas') {
                // GET /analises/estatisticas
                echo json_encode($analise->estatisticas());
            } else {
                // GET /analises
                $filtros = [];
                if (isset($_GET['status'])) $filtros['status'] = $_GET['status'];
                if (isset($_GET['recomendacao'])) $filtros['recomendacao'] = $_GET['recomendacao'];
                if (isset($_GET['laudo_id'])) $filtros['laudo_id'] = $_GET['laudo_id'];
                
                echo json_encode($analise->listar($filtros));
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (preg_match('/^\/processar\/(\d+)$/', $path, $matches)) {
                // POST /analises/processar/{laudo_id}
                $laudo_id = $matches[1];
                
                if (empty($input['arquivo_pdf'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Arquivo PDF é obrigatório']);
                    exit();
                }
                
                $resultado = $analise->processarPDF($laudo_id, $input['arquivo_pdf']);
                
                http_response_code(201);
                echo json_encode([
                    'message' => 'Análise iniciada com sucesso',
                    'analise' => $resultado
                ]);
            } elseif ($path === '/upload') {
                // POST /analises/upload
                if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Erro no upload do arquivo']);
                    exit();
                }
                
                $upload_dir = __DIR__ . '/../uploads/pdfs/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $arquivo_nome = uniqid() . '_' . $_FILES['pdf']['name'];
                $arquivo_path = $upload_dir . $arquivo_nome;
                
                if (!move_uploaded_file($_FILES['pdf']['tmp_name'], $arquivo_path)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Erro ao salvar arquivo']);
                    exit();
                }
                
                // Se laudo_id foi fornecido, processar automaticamente
                if (!empty($_POST['laudo_id'])) {
                    $resultado = $analise->processarPDF($_POST['laudo_id'], $arquivo_path);
                    echo json_encode([
                        'message' => 'Upload e análise realizados com sucesso',
                        'arquivo' => $arquivo_path,
                        'analise' => $resultado
                    ]);
                } else {
                    echo json_encode([
                        'message' => 'Upload realizado com sucesso',
                        'arquivo' => $arquivo_path
                    ]);
                }
            } else {
                // POST /analises
                $campos_obrigatorios = ['laudo_id', 'arquivo_pdf'];
                foreach ($campos_obrigatorios as $campo) {
                    if (empty($input[$campo])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Campo obrigatório: $campo"]);
                        exit();
                    }
                }
                
                $id = $analise->criar($input);
                $resultado = $analise->buscarPorId($id);
                
                http_response_code(201);
                echo json_encode($resultado);
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (preg_match('/^\/(\d+)$/', $path, $matches)) {
                // PUT /analises/{id}
                $id = $matches[1];
                $resultado = $analise->atualizar($id, $input);
                echo json_encode($resultado);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

