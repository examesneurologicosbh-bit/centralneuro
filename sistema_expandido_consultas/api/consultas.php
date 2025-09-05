<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../models/Consulta.php';

$consulta = new Consulta();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

try {
    // Criar tabelas se necessário
    $consulta->createTables();
    
    switch ($method) {
        case 'GET':
            if (preg_match('/^\/tipos-exame$/', $path)) {
                // GET /consultas/tipos-exame
                echo json_encode([
                    'success' => true,
                    'tipos_exame' => Consulta::EXAM_TYPES
                ]);
            } elseif (preg_match('/^\/especialidades$/', $path)) {
                // GET /consultas/especialidades
                echo json_encode([
                    'success' => true,
                    'especialidades' => Consulta::SPECIALTIES
                ]);
            } elseif (preg_match('/^\/status$/', $path)) {
                // GET /consultas/status
                echo json_encode([
                    'success' => true,
                    'status' => Consulta::STATUS
                ]);
            } elseif (preg_match('/^\/pacientes$/', $path)) {
                // GET /consultas/pacientes
                $termo = $_GET['q'] ?? '';
                $limite = $_GET['limit'] ?? 50;
                $pacientes = $consulta->buscarPacientes($termo, $limite);
                echo json_encode([
                    'success' => true,
                    'pacientes' => $pacientes,
                    'total' => count($pacientes)
                ]);
            } elseif (preg_match('/^\/horarios-disponiveis$/', $path)) {
                // GET /consultas/horarios-disponiveis?tipo_exame=X&data=Y&medico_id=Z
                $tipoExame = $_GET['tipo_exame'] ?? null;
                $data = $_GET['data'] ?? null;
                $medicoId = $_GET['medico_id'] ?? null;
                
                if (!$tipoExame || !$data) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Tipo de exame e data são obrigatórios']);
                    exit();
                }
                
                $horarios = $consulta->obterHorariosDisponiveis($tipoExame, $data, $medicoId);
                echo json_encode([
                    'success' => true,
                    'horarios_disponiveis' => $horarios,
                    'data' => $data,
                    'tipo_exame' => $tipoExame
                ]);
            } elseif (preg_match('/^\/periodo$/', $path)) {
                // GET /consultas/periodo?data_inicio=X&data_fim=Y&tipo_exame=Z&status=W
                $dataInicio = $_GET['data_inicio'] ?? date('Y-m-d');
                $dataFim = $_GET['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));
                $tipoExame = $_GET['tipo_exame'] ?? null;
                $status = $_GET['status'] ?? null;
                
                $consultas = $consulta->buscarConsultasPorPeriodo($dataInicio, $dataFim, $tipoExame, $status);
                echo json_encode([
                    'success' => true,
                    'consultas' => $consultas,
                    'periodo' => [
                        'data_inicio' => $dataInicio,
                        'data_fim' => $dataFim
                    ],
                    'total' => count($consultas)
                ]);
            } elseif (preg_match('/^\/estatisticas$/', $path)) {
                // GET /consultas/estatisticas?data_inicio=X&data_fim=Y
                $dataInicio = $_GET['data_inicio'] ?? null;
                $dataFim = $_GET['data_fim'] ?? null;
                
                $stats = $consulta->obterEstatisticas($dataInicio, $dataFim);
                echo json_encode([
                    'success' => true,
                    'estatisticas' => $stats,
                    'periodo' => [
                        'data_inicio' => $dataInicio,
                        'data_fim' => $dataFim
                    ]
                ]);
            } else {
                // GET /consultas - listar todas
                $filtros = [];
                if (isset($_GET['status'])) $filtros['status'] = $_GET['status'];
                if (isset($_GET['tipo_exame'])) $filtros['tipo_exame'] = $_GET['tipo_exame'];
                if (isset($_GET['data_inicio'])) $filtros['data_inicio'] = $_GET['data_inicio'];
                if (isset($_GET['data_fim'])) $filtros['data_fim'] = $_GET['data_fim'];
                
                $dataInicio = $filtros['data_inicio'] ?? date('Y-m-d');
                $dataFim = $filtros['data_fim'] ?? date('Y-m-d', strtotime('+30 days'));
                
                $consultas = $consulta->buscarConsultasPorPeriodo(
                    $dataInicio, 
                    $dataFim, 
                    $filtros['tipo_exame'] ?? null, 
                    $filtros['status'] ?? null
                );
                
                echo json_encode([
                    'success' => true,
                    'consultas' => $consultas,
                    'total' => count($consultas)
                ]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (preg_match('/^\/pacientes$/', $path)) {
                // POST /consultas/pacientes
                $campos_obrigatorios = ['nome', 'data_nascimento', 'sexo', 'telefone'];
                foreach ($campos_obrigatorios as $campo) {
                    if (empty($input[$campo])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Campo obrigatório: $campo"]);
                        exit();
                    }
                }
                
                $pacienteId = $consulta->registrarPaciente($input);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Paciente registrado com sucesso',
                    'paciente_id' => $pacienteId
                ]);
            } elseif (preg_match('/^\/medicos$/', $path)) {
                // POST /consultas/medicos
                $campos_obrigatorios = ['nome', 'crm', 'uf_crm', 'especialidade'];
                foreach ($campos_obrigatorios as $campo) {
                    if (empty($input[$campo])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Campo obrigatório: $campo"]);
                        exit();
                    }
                }
                
                $medicoId = $consulta->registrarMedico($input);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Médico registrado com sucesso',
                    'medico_id' => $medicoId
                ]);
            } elseif (preg_match('/^\/agendar$/', $path)) {
                // POST /consultas/agendar
                $campos_obrigatorios = ['paciente_id', 'tipo_exame', 'data_consulta'];
                foreach ($campos_obrigatorios as $campo) {
                    if (empty($input[$campo])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Campo obrigatório: $campo"]);
                        exit();
                    }
                }
                
                // Validar tipo de exame
                if (!isset(Consulta::EXAM_TYPES[$input['tipo_exame']])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Tipo de exame inválido']);
                    exit();
                }
                
                // Validar formato de data
                $dataConsulta = DateTime::createFromFormat('Y-m-d H:i', $input['data_consulta']);
                if (!$dataConsulta) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Formato de data inválido. Use: YYYY-MM-DD HH:MM']);
                    exit();
                }
                
                // Verificar se a data não é no passado
                if ($dataConsulta < new DateTime()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Data da consulta não pode ser no passado']);
                    exit();
                }
                
                $consultaId = $consulta->agendarConsulta($input);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Consulta agendada com sucesso',
                    'consulta_id' => $consultaId
                ]);
            } elseif (preg_match('/^\/(\d+)\/resultado$/', $path, $matches)) {
                // POST /consultas/{id}/resultado
                $consultaId = $matches[1];
                
                $campos_obrigatorios = ['tipo_resultado'];
                foreach ($campos_obrigatorios as $campo) {
                    if (empty($input[$campo])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Campo obrigatório: $campo"]);
                        exit();
                    }
                }
                
                $resultadoId = $consulta->registrarResultado($consultaId, $input);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Resultado registrado com sucesso',
                    'resultado_id' => $resultadoId
                ]);
            } else {
                // POST /consultas - criar consulta direta
                $campos_obrigatorios = ['paciente_id', 'tipo_exame', 'data_consulta'];
                foreach ($campos_obrigatorios as $campo) {
                    if (empty($input[$campo])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Campo obrigatório: $campo"]);
                        exit();
                    }
                }
                
                $consultaId = $consulta->agendarConsulta($input);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Consulta criada com sucesso',
                    'consulta_id' => $consultaId
                ]);
            }
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (preg_match('/^\/(\d+)\/status$/', $path, $matches)) {
                // PUT /consultas/{id}/status
                $consultaId = $matches[1];
                
                if (empty($input['status'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Status é obrigatório']);
                    exit();
                }
                
                // Validar status
                if (!isset(Consulta::STATUS[$input['status']])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Status inválido']);
                    exit();
                }
                
                $sucesso = $consulta->atualizarStatusConsulta(
                    $consultaId, 
                    $input['status'], 
                    $input['observacoes'] ?? null
                );
                
                if ($sucesso) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Status atualizado com sucesso'
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Consulta não encontrada']);
                }
            } elseif (preg_match('/^\/(\d+)\/preparo$/', $path, $matches)) {
                // PUT /consultas/{id}/preparo - enviar instruções de preparo
                $consultaId = $matches[1];
                
                $mensagem = $consulta->enviarInstrucoesPreparo($consultaId);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Instruções de preparo enviadas',
                    'instrucoes' => $mensagem
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'DELETE':
            if (preg_match('/^\/(\d+)$/', $path, $matches)) {
                // DELETE /consultas/{id} - cancelar consulta
                $consultaId = $matches[1];
                
                $sucesso = $consulta->atualizarStatusConsulta($consultaId, 'cancelado', 'Cancelado via API');
                
                if ($sucesso) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Consulta cancelada com sucesso'
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Consulta não encontrada']);
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
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

