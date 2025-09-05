<?php
require_once __DIR__ . '/../config.php';

class Laudo {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getConnection();
    }
    
    public function criar($dados) {
        $sql = "INSERT INTO laudos (
            codigo_validador, nome_paciente, numero_controle, data_nascimento,
            indicacao, sexo, data_exame, rg, cpf, convenio, tipo_exame,
            medico_nome, medico_crm, medico_rqe, medico_especialidade
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $dados['codigo_validador'],
            $dados['nome_paciente'],
            $dados['numero_controle'],
            $dados['data_nascimento'],
            $dados['indicacao'],
            $dados['sexo'],
            $dados['data_exame'],
            $dados['rg'] ?? '',
            $dados['cpf'] ?? '',
            $dados['convenio'] ?? '',
            $dados['tipo_exame'],
            $dados['medico_nome'],
            $dados['medico_crm'],
            $dados['medico_rqe'] ?? '',
            $dados['medico_especialidade']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    public function listar($filtros = []) {
        $sql = "SELECT * FROM laudos WHERE 1=1";
        $params = [];
        
        if (isset($filtros['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filtros['status'];
        }
        
        if (isset($filtros['tipo_exame'])) {
            $sql .= " AND tipo_exame = ?";
            $params[] = $filtros['tipo_exame'];
        }
        
        if (isset($filtros['data_inicio'])) {
            $sql .= " AND DATE(data_exame) >= ?";
            $params[] = $filtros['data_inicio'];
        }
        
        if (isset($filtros['data_fim'])) {
            $sql .= " AND DATE(data_exame) <= ?";
            $params[] = $filtros['data_fim'];
        }
        
        if (isset($filtros['search'])) {
            $sql .= " AND (nome_paciente LIKE ? OR numero_controle LIKE ? OR codigo_validador LIKE ?)";
            $search = '%' . $filtros['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function buscarPorId($id) {
        $sql = "SELECT * FROM laudos WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    public function buscarPorCodigo($codigo_validador) {
        $sql = "SELECT * FROM laudos WHERE codigo_validador = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$codigo_validador]);
        
        return $stmt->fetch();
    }
    
    public function atualizar($id, $dados) {
        $campos_permitidos = [
            'nome_paciente', 'data_nascimento', 'indicacao', 'sexo',
            'rg', 'cpf', 'convenio', 'tipo_exame', 'medico_nome',
            'medico_crm', 'medico_rqe', 'medico_especialidade',
            'status', 'conteudo_laudo'
        ];
        
        $sets = [];
        $params = [];
        
        foreach ($campos_permitidos as $campo) {
            if (isset($dados[$campo])) {
                $sets[] = "$campo = ?";
                $params[] = $dados[$campo];
            }
        }
        
        if (empty($sets)) {
            throw new Exception('Nenhum campo para atualizar');
        }
        
        $sets[] = "updated_at = ?";
        $params[] = date('Y-m-d H:i:s');
        $params[] = $id;
        
        $sql = "UPDATE laudos SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $this->buscarPorId($id);
    }
    
    public function finalizar($id, $conteudo_laudo) {
        $sql = "UPDATE laudos SET 
            conteudo_laudo = ?, 
            status = 'finalizado', 
            updated_at = ? 
            WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conteudo_laudo, date('Y-m-d H:i:s'), $id]);
        
        return $this->buscarPorId($id);
    }
    
    public function estatisticas() {
        $stats = [];
        
        // Total de laudos
        $sql = "SELECT COUNT(*) as total FROM laudos";
        $stmt = $this->pdo->query($sql);
        $stats['total_laudos'] = $stmt->fetch()['total'];
        
        // Por status
        $sql = "SELECT status, COUNT(*) as count FROM laudos GROUP BY status";
        $stmt = $this->pdo->query($sql);
        $por_status = [];
        while ($row = $stmt->fetch()) {
            $por_status[$row['status']] = $row['count'];
        }
        $stats['por_status'] = $por_status;
        
        // Por tipo de exame
        $sql = "SELECT tipo_exame, COUNT(*) as count FROM laudos GROUP BY tipo_exame";
        $stmt = $this->pdo->query($sql);
        $por_tipo = [];
        while ($row = $stmt->fetch()) {
            $por_tipo[] = ['tipo' => $row['tipo_exame'], 'count' => $row['count']];
        }
        $stats['por_tipo_exame'] = $por_tipo;
        
        // Laudos por mês (últimos 12 meses)
        $sql = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as mes,
            COUNT(*) as count
            FROM laudos 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY mes";
        
        if (DatabaseConfig::getDbType() === 'sqlite') {
            $sql = "SELECT 
                strftime('%Y-%m', created_at) as mes,
                COUNT(*) as count
                FROM laudos 
                WHERE created_at >= date('now', '-12 months')
                GROUP BY strftime('%Y-%m', created_at)
                ORDER BY mes";
        }
        
        $stmt = $this->pdo->query($sql);
        $por_mes = [];
        while ($row = $stmt->fetch()) {
            $por_mes[] = ['mes' => $row['mes'], 'count' => $row['count']];
        }
        $stats['por_mes'] = $por_mes;
        
        return $stats;
    }
    
    public function toArray($laudo) {
        if (!$laudo) return null;
        
        return [
            'id' => $laudo['id'],
            'codigo_validador' => $laudo['codigo_validador'],
            'nome_paciente' => $laudo['nome_paciente'],
            'numero_controle' => $laudo['numero_controle'],
            'data_nascimento' => $laudo['data_nascimento'] ? date('d/m/Y', strtotime($laudo['data_nascimento'])) : null,
            'indicacao' => $laudo['indicacao'],
            'sexo' => $laudo['sexo'],
            'data_exame' => $laudo['data_exame'] ? date('d/m/Y', strtotime($laudo['data_exame'])) : null,
            'rg' => $laudo['rg'],
            'cpf' => $laudo['cpf'],
            'convenio' => $laudo['convenio'],
            'tipo_exame' => $laudo['tipo_exame'],
            'medico_nome' => $laudo['medico_nome'],
            'medico_crm' => $laudo['medico_crm'],
            'medico_rqe' => $laudo['medico_rqe'],
            'medico_especialidade' => $laudo['medico_especialidade'],
            'status' => $laudo['status'],
            'conteudo_laudo' => $laudo['conteudo_laudo'],
            'created_at' => $laudo['created_at'] ? date('d/m/Y H:i:s', strtotime($laudo['created_at'])) : null,
            'updated_at' => $laudo['updated_at'] ? date('d/m/Y H:i:s', strtotime($laudo['updated_at'])) : null
        ];
    }
}
?>

