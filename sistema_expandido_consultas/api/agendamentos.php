<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../models/Agendamento.php';
require_once __DIR__ . '/../models/Laudo.php';

$agendamento = new Agendamento();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if (preg_match('/^\/(\d+)$/', $path, $matches)) {
                // GET /agendamentos/{id}
                $id = $matches[1];
                $resultado = $agendamento->buscarPorId($id);
                if (!$resultado) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Agendamento não encontrado']);
                    exit();
                }
                echo json_encode($resultado);
            } elseif ($path === '/estatisticas') {
                // GET /agendamentos/estatisticas
                echo json_encode($agendamento->estatisticas());
            } else {
                // GET /agendamentos
                $filtros = [];
                if (isset($_GET['status'])) $filtros['status'] = $_GET['status'];
                if (isset($_GET['tipo_exame'])) $filtros['tipo_exame'] = $_GET['tipo_exame'];
                if (isset($_GET['data_inicio'])) $filtros['data_inicio'] = $_GET['data_inicio'];
                if (isset($_GET['data_fim'])) $filtros['data_fim'] = $_GET['data_fim'];
                
                echo json_encode($agendamento->listar($filtros));
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if ($path === '') {
                // POST /agendamentos
                $campos_obrigatorios = ['nome_paciente', 'telefone', 'data_agendamento', 'tipo_exame'];
                foreach ($campos_obrigatorios as $campo) {
                    if (empty($input[$campo])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Campo obrigatório: $campo"]);
                        exit();
                    }
                }
                
                // Validar formato de data
                $data_agendamento = DateTime::createFromFormat('Y-m-d H:i', $input['data_agendamento']);
                if (!$data_agendamento) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Formato de data inválido. Use: YYYY-MM-DD HH:MM']);
                    exit();
                }
                
                // Verificar se a data não é no passado
                if ($data_agendamento < new DateTime()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Data de agendamento não pode ser no passado']);
                    exit();
                }
                
                $id = $agendamento->criar($input);
                $resultado = $agendamento->buscarPorId($id);
                
                http_response_code(201);
                echo json_encode($resultado);
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (preg_match('/^\/(\d+)\/checkin$/', $path, $matches)) {
                // PUT /agendamentos/{id}/checkin
                $id = $matches[1];
                $resultado = $agendamento->checkin($id);
                echo json_encode([
                    'message' => 'Check-in realizado com sucesso',
                    'agendamento' => $resultado
                ]);
            } elseif (preg_match('/^\/(\d+)\/precadastro$/', $path, $matches)) {
                // PUT /agendamentos/{id}/precadastro
                $id = $matches[1];
                $resultado = $agendamento->precadastro($id, $input);
                echo json_encode([
                    'message' => 'Pré-cadastro realizado com sucesso',
                    'agendamento' => $resultado['agendamento'],
                    'laudo' => $resultado['laudo']
                ]);
            } elseif (preg_match('/^\/(\d+)$/', $path, $matches)) {
                // PUT /agendamentos/{id}
                $id = $matches[1];
                $resultado = $agendamento->atualizar($id, $input);
                echo json_encode($resultado);
            }
            break;
            
        case 'DELETE':
            if (preg_match('/^\/(\d+)$/', $path, $matches)) {
                // DELETE /agendamentos/{id}
                $id = $matches[1];
                $agendamento->cancelar($id);
                echo json_encode(['message' => 'Agendamento cancelado com sucesso']);
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

