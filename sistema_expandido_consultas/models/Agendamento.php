<?php
require_once __DIR__ . '/../config.php';

class Agendamento {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getConnection();
    }
    
    public function criar($dados) {
        $sql = "INSERT INTO agendamentos (
            nome_paciente, telefone, email, data_agendamento, 
            tipo_eeg, observacoes
        ) VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $dados['nome_paciente'],
            $dados['telefone'],
            $dados['email'] ?? '',
            $dados['data_agendamento'],
            $dados['tipo_exame'] ?? $dados['tipo_eeg'] ?? '', // Aceitar ambos os nomes
            $dados['observacoes'] ?? ''
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    public function listar($filtros = []) {
        $sql = "SELECT * FROM agendamentos WHERE 1=1";
        $params = [];
        
        if (isset($filtros['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filtros['status'];
        }
        
        if (isset($filtros['tipo_exame']) || isset($filtros['tipo_eeg'])) {
            $sql .= " AND tipo_eeg = ?";
            $params[] = $filtros['tipo_exame'] ?? $filtros['tipo_eeg'];
        }
        
        if (isset($filtros['data_inicio'])) {
            $sql .= " AND DATE(data_agendamento) >= ?";
            $params[] = $filtros['data_inicio'];
        }
        
        if (isset($filtros['data_fim'])) {
            $sql .= " AND DATE(data_agendamento) <= ?";
            $params[] = $filtros['data_fim'];
        }
        
        $sql .= " ORDER BY data_agendamento DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    public function buscarPorId($id) {
        $sql = "SELECT * FROM agendamentos WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        
        return $stmt->fetch();
    }
    
    public function checkin($id) {
        $agendamento = $this->buscarPorId($id);
        if (!$agendamento) {
            throw new Exception('Agendamento não encontrado');
        }
        
        if ($agendamento['status'] !== 'agendado') {
            throw new Exception('Agendamento não está no status "agendado"');
        }
        
        $sql = "UPDATE agendamentos SET status = 'compareceu', updated_at = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
        
        return $this->buscarPorId($id);
    }
    
    public function precadastro($id, $dados) {
        $agendamento = $this->buscarPorId($id);
        if (!$agendamento) {
            throw new Exception('Agendamento não encontrado');
        }
        
        if ($agendamento['status'] !== 'compareceu') {
            throw new Exception('Paciente deve fazer check-in primeiro');
        }
        
        // Validações
        $campos_obrigatorios = ['data_nascimento', 'sexo', 'indicacao'];
        foreach ($campos_obrigatorios as $campo) {
            if (empty($dados[$campo])) {
                throw new Exception("Campo obrigatório: $campo");
            }
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Atualiza agendamento
            $sql = "UPDATE agendamentos SET 
                data_nascimento = ?, sexo = ?, rg = ?, cpf = ?, 
                endereco = ?, convenio = ?, indicacao = ?, 
                medico_solicitante = ?, status = 'pronto_exame', 
                updated_at = ?
                WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $dados['data_nascimento'],
                $dados['sexo'],
                $dados['rg'] ?? '',
                $dados['cpf'] ?? '',
                $dados['endereco'] ?? '',
                $dados['convenio'] ?? '',
                $dados['indicacao'],
                $dados['medico_solicitante'] ?? '',
                date('Y-m-d H:i:s'),
                $id
            ]);
            
            // Criar laudo
            $laudo = new Laudo();
            $numero_controle = $this->gerarNumeroControle();
            
            $dados_laudo = [
                'codigo_validador' => $this->gerarCodigoValidador(),
                'nome_paciente' => $agendamento['nome_paciente'],
                'numero_controle' => $numero_controle,
                'data_nascimento' => $dados['data_nascimento'],
                'indicacao' => $dados['indicacao'],
                'sexo' => $dados['sexo'],
                'data_exame' => date('Y-m-d', strtotime($agendamento['data_agendamento'])),
                'rg' => $dados['rg'] ?? '',
                'cpf' => $dados['cpf'] ?? '',
                'convenio' => $dados['convenio'] ?? '',
                'tipo_exame' => $agendamento['tipo_eeg'],
                'medico_nome' => $dados['medico_nome'] ?? 'Dr. Neurologista',
                'medico_crm' => $dados['medico_crm'] ?? 'CRM/UF 12345',
                'medico_rqe' => $dados['medico_rqe'] ?? '',
                'medico_especialidade' => $dados['medico_especialidade'] ?? 'Neurologista'
            ];
            
            $laudo_id = $laudo->criar($dados_laudo);
            
            // Associar laudo ao agendamento
            $sql = "UPDATE agendamentos SET laudo_id = ? WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$laudo_id, $id]);
            
            $this->pdo->commit();
            
            return [
                'agendamento' => $this->buscarPorId($id),
                'laudo' => $laudo->buscarPorId($laudo_id)
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }
    
    public function atualizar($id, $dados) {
        $campos_permitidos = [
            'nome_paciente', 'telefone', 'email', 'data_agendamento',
            'tipo_eeg', 'observacoes', 'status'
        ];
        
        $sets = [];
        $params = [];
        
        foreach ($campos_permitidos as $campo) {
            if (isset($dados[$campo])) {
                $sets[] = "$campo = ?";
                $params[] = $dados[$campo];
            }
            // Aceitar tipo_exame como alias para tipo_eeg
            if ($campo === 'tipo_eeg' && isset($dados['tipo_exame'])) {
                $sets[] = "tipo_eeg = ?";
                $params[] = $dados['tipo_exame'];
            }
        }
        
        if (empty($sets)) {
            throw new Exception('Nenhum campo para atualizar');
        }
        
        $sets[] = "updated_at = ?";
        $params[] = date('Y-m-d H:i:s');
        $params[] = $id;
        
        $sql = "UPDATE agendamentos SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $this->buscarPorId($id);
    }
    
    public function cancelar($id) {
        $sql = "UPDATE agendamentos SET status = 'cancelado', updated_at = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([date('Y-m-d H:i:s'), $id]);
        
        return $this->buscarPorId($id);
    }
    
    public function estatisticas() {
        $stats = [];
        
        // Total de agendamentos
        $sql = "SELECT COUNT(*) as total FROM agendamentos";
        $stmt = $this->pdo->query($sql);
        $stats['total_agendamentos'] = $stmt->fetch()['total'];
        
        // Por status
        $sql = "SELECT status, COUNT(*) as count FROM agendamentos GROUP BY status";
        $stmt = $this->pdo->query($sql);
        $por_status = [];
        while ($row = $stmt->fetch()) {
            $por_status[$row['status']] = $row['count'];
        }
        $stats['por_status'] = $por_status;
        
        // Por tipo de exame
        $sql = "SELECT tipo_eeg, COUNT(*) as count FROM agendamentos GROUP BY tipo_eeg";
        $stmt = $this->pdo->query($sql);
        $por_tipo = [];
        while ($row = $stmt->fetch()) {
            $por_tipo[] = ['tipo' => $row['tipo_eeg'], 'count' => $row['count']];
        }
        $stats['por_tipo_exame'] = $por_tipo;
        
        return $stats;
    }
    
    private function gerarNumeroControle() {
        $ano = date('Y');
        
        $sql = "SELECT numero_controle FROM laudos WHERE numero_controle LIKE ? ORDER BY numero_controle DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(["$ano/%"]);
        $ultimo = $stmt->fetch();
        
        if ($ultimo) {
            $ultimo_numero = intval(explode('/', $ultimo['numero_controle'])[1]);
            $novo_numero = $ultimo_numero + 1;
        } else {
            $novo_numero = 1;
        }
        
        return sprintf("%d/%04d", $ano, $novo_numero);
    }
    
    private function gerarCodigoValidador() {
        return strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8));
    }
}
?>

