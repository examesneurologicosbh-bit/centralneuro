<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../models/Laudo.php';

$laudo = new Laudo();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if (preg_match('/^\/(\d+)$/', $path, $matches)) {
                // GET /laudos/{id}
                $id = $matches[1];
                $resultado = $laudo->buscarPorId($id);
                if (!$resultado) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Laudo não encontrado']);
                    exit();
                }
                echo json_encode($laudo->toArray($resultado));
            } elseif (preg_match('/^\/codigo\/(.+)$/', $path, $matches)) {
                // GET /laudos/codigo/{codigo_validador}
                $codigo = $matches[1];
                $resultado = $laudo->buscarPorCodigo($codigo);
                if (!$resultado) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Laudo não encontrado']);
                    exit();
                }
                echo json_encode($laudo->toArray($resultado));
            } elseif ($path === '/estatisticas') {
                // GET /laudos/estatisticas
                echo json_encode($laudo->estatisticas());
            } else {
                // GET /laudos
                $filtros = [];
                if (isset($_GET['status'])) $filtros['status'] = $_GET['status'];
                if (isset($_GET['tipo_exame'])) $filtros['tipo_exame'] = $_GET['tipo_exame'];
                if (isset($_GET['data_inicio'])) $filtros['data_inicio'] = $_GET['data_inicio'];
                if (isset($_GET['data_fim'])) $filtros['data_fim'] = $_GET['data_fim'];
                if (isset($_GET['search'])) $filtros['search'] = $_GET['search'];
                
                $resultados = $laudo->listar($filtros);
                $laudos_formatados = array_map([$laudo, 'toArray'], $resultados);
                echo json_encode($laudos_formatados);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if ($path === '') {
                // POST /laudos
                $campos_obrigatorios = [
                    'nome_paciente', 'data_nascimento', 'indicacao', 'sexo',
                    'data_exame', 'tipo_exame', 'medico_nome', 'medico_crm'
                ];
                
                foreach ($campos_obrigatorios as $campo) {
                    if (empty($input[$campo])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Campo obrigatório: $campo"]);
                        exit();
                    }
                }
                
                // Gerar código validador e número de controle
                if (empty($input['codigo_validador'])) {
                    $input['codigo_validador'] = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
                }
                
                if (empty($input['numero_controle'])) {
                    $ano = date('Y');
                    $ultimo = $laudo->listar(['search' => "$ano/"]);
                    $numero = count($ultimo) + 1;
                    $input['numero_controle'] = sprintf("%d/%04d", $ano, $numero);
                }
                
                $id = $laudo->criar($input);
                $resultado = $laudo->buscarPorId($id);
                
                http_response_code(201);
                echo json_encode($laudo->toArray($resultado));
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (preg_match('/^\/(\d+)\/finalizar$/', $path, $matches)) {
                // PUT /laudos/{id}/finalizar
                $id = $matches[1];
                if (empty($input['conteudo_laudo'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Conteúdo do laudo é obrigatório']);
                    exit();
                }
                
                $resultado = $laudo->finalizar($id, $input['conteudo_laudo']);
                echo json_encode([
                    'message' => 'Laudo finalizado com sucesso',
                    'laudo' => $laudo->toArray($resultado)
                ]);
            } elseif (preg_match('/^\/(\d+)$/', $path, $matches)) {
                // PUT /laudos/{id}
                $id = $matches[1];
                $resultado = $laudo->atualizar($id, $input);
                echo json_encode($laudo->toArray($resultado));
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

