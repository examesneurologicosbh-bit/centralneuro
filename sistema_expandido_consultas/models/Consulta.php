<?php
require_once __DIR__ . '/../config.php';

class Consulta {
    private $pdo;
    
    // Tipos de exames neurológicos especializados
    const EXAM_TYPES = [
        'eeg_rotina' => [
            'name' => 'EEG de Rotina',
            'duration' => 30,
            'preparation' => 'Cabelo limpo e seco. Evitar cremes e óleos.',
            'price' => 150.00
        ],
        'eeg_sono' => [
            'name' => 'EEG com Privação de Sono',
            'duration' => 60,
            'preparation' => 'Dormir apenas 4 horas na noite anterior. Cabelo limpo.',
            'price' => 250.00
        ],
        'eeg_mapeamento' => [
            'name' => 'EEG com Mapeamento Cerebral',
            'duration' => 45,
            'preparation' => 'Cabelo limpo. Suspender medicações conforme orientação médica.',
            'price' => 300.00
        ],
        'video_eeg' => [
            'name' => 'Video-EEG',
            'duration' => 120,
            'preparation' => 'Jejum de 4 horas. Cabelo limpo. Trazer acompanhante.',
            'price' => 500.00
        ],
        'eletroneuromiografia' => [
            'name' => 'Eletroneuromiografia',
            'duration' => 60,
            'preparation' => 'Suspender anticoagulantes. Pele limpa e hidratada.',
            'price' => 400.00
        ],
        'potencial_evocado' => [
            'name' => 'Potencial Evocado',
            'duration' => 90,
            'preparation' => 'Cabelo limpo. Evitar cafeína 24h antes.',
            'price' => 350.00
        ]
    ];
    
    // Especialidades médicas
    const SPECIALTIES = [
        'neurologia' => 'Neurologia',
        'neurofisiologia' => 'Neurofisiologia Clínica',
        'epileptologia' => 'Epileptologia',
        'neurologia_pediatrica' => 'Neurologia Pediátrica',
        'medicina_sono' => 'Medicina do Sono'
    ];
    
    // Status de consultas
    const STATUS = [
        'agendado' => 'Agendado',
        'confirmado' => 'Confirmado',
        'em_preparo' => 'Em Preparo',
        'em_andamento' => 'Em Andamento',
        'concluido' => 'Concluído',
        'laudo_pendente' => 'Laudo Pendente',
        'finalizado' => 'Finalizado',
        'cancelado' => 'Cancelado',
        'faltou' => 'Paciente Faltou'
    ];
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getConnection();
    }
    
    /**
     * Cria as tabelas específicas para consultas neurológicas
     */
    public function createTables() {
        $tables = [
            // Tabela de pacientes expandida
            "CREATE TABLE IF NOT EXISTS pacientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                cpf VARCHAR(14) UNIQUE,
                rg VARCHAR(20),
                data_nascimento DATE NOT NULL,
                sexo VARCHAR(1) NOT NULL CHECK (sexo IN ('M', 'F')),
                telefone VARCHAR(20) NOT NULL,
                email VARCHAR(100),
                endereco TEXT,
                cep VARCHAR(10),
                cidade VARCHAR(100),
                estado VARCHAR(2),
                convenio VARCHAR(100),
                numero_carteirinha VARCHAR(50),
                contato_emergencia VARCHAR(255),
                telefone_emergencia VARCHAR(20),
                observacoes_medicas TEXT,
                alergias TEXT,
                medicamentos_uso TEXT,
                historico_familiar TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // Tabela de médicos
            "CREATE TABLE IF NOT EXISTS medicos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                crm VARCHAR(20) NOT NULL,
                uf_crm VARCHAR(2) NOT NULL,
                rqe VARCHAR(20),
                especialidade VARCHAR(100) NOT NULL,
                telefone VARCHAR(20),
                email VARCHAR(100),
                endereco TEXT,
                horario_atendimento JSON,
                ativo BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            // Tabela de consultas neurológicas
            "CREATE TABLE IF NOT EXISTS consultas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                paciente_id INT NOT NULL,
                medico_id INT,
                agendamento_id INT,
                tipo_exame VARCHAR(50) NOT NULL,
                data_consulta DATETIME NOT NULL,
                duracao_estimada INT DEFAULT 30,
                status VARCHAR(20) DEFAULT 'agendado',
                valor DECIMAL(10,2),
                convenio VARCHAR(100),
                numero_autorizacao VARCHAR(50),
                observacoes TEXT,
                instrucoes_preparo TEXT,
                data_preparo_enviado DATETIME,
                resultado_exame TEXT,
                laudo_medico TEXT,
                arquivo_resultado VARCHAR(255),
                data_resultado DATETIME,
                medico_laudo_id INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (paciente_id) REFERENCES pacientes(id),
                FOREIGN KEY (medico_id) REFERENCES medicos(id),
                FOREIGN KEY (medico_laudo_id) REFERENCES medicos(id),
                FOREIGN KEY (agendamento_id) REFERENCES agendamentos(id)
            )",
            
            // Tabela de resultados de exames
            "CREATE TABLE IF NOT EXISTS resultados_exames (
                id INT AUTO_INCREMENT PRIMARY KEY,
                consulta_id INT NOT NULL,
                tipo_resultado VARCHAR(50) NOT NULL,
                dados_tecnicos JSON,
                interpretacao TEXT,
                conclusao TEXT,
                recomendacoes TEXT,
                arquivo_pdf VARCHAR(255),
                arquivo_imagem VARCHAR(255),
                qualidade_exame VARCHAR(20),
                artefatos_detectados TEXT,
                tempo_exame INT,
                medico_responsavel_id INT,
                data_analise DATETIME,
                revisado BOOLEAN DEFAULT FALSE,
                data_revisao DATETIME,
                medico_revisor_id INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (consulta_id) REFERENCES consultas(id),
                FOREIGN KEY (medico_responsavel_id) REFERENCES medicos(id),
                FOREIGN KEY (medico_revisor_id) REFERENCES medicos(id)
            )",
            
            // Tabela de horários disponíveis
            "CREATE TABLE IF NOT EXISTS horarios_disponiveis (
                id INT AUTO_INCREMENT PRIMARY KEY,
                medico_id INT,
                tipo_exame VARCHAR(50) NOT NULL,
                dia_semana INT NOT NULL, -- 0=domingo, 1=segunda, etc
                hora_inicio TIME NOT NULL,
                hora_fim TIME NOT NULL,
                intervalo_minutos INT DEFAULT 30,
                ativo BOOLEAN DEFAULT TRUE,
                data_inicio DATE,
                data_fim DATE,
                observacoes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (medico_id) REFERENCES medicos(id)
            )",
            
            // Tabela de bloqueios de horários
            "CREATE TABLE IF NOT EXISTS bloqueios_horarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                medico_id INT,
                data_inicio DATETIME NOT NULL,
                data_fim DATETIME NOT NULL,
                motivo VARCHAR(255),
                tipo_bloqueio VARCHAR(50) DEFAULT 'manual',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (medico_id) REFERENCES medicos(id)
            )"
        ];
        
        foreach ($tables as $sql) {
            $this->pdo->exec($sql);
        }
    }
    
    /**
     * Registra um novo paciente
     */
    public function registrarPaciente($dados) {
        $sql = "INSERT INTO pacientes (
            nome, cpf, rg, data_nascimento, sexo, telefone, email, 
            endereco, cep, cidade, estado, convenio, numero_carteirinha,
            contato_emergencia, telefone_emergencia, observacoes_medicas,
            alergias, medicamentos_uso, historico_familiar
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $dados['nome'],
            $dados['cpf'] ?? null,
            $dados['rg'] ?? null,
            $dados['data_nascimento'],
            $dados['sexo'],
            $dados['telefone'],
            $dados['email'] ?? null,
            $dados['endereco'] ?? null,
            $dados['cep'] ?? null,
            $dados['cidade'] ?? null,
            $dados['estado'] ?? null,
            $dados['convenio'] ?? null,
            $dados['numero_carteirinha'] ?? null,
            $dados['contato_emergencia'] ?? null,
            $dados['telefone_emergencia'] ?? null,
            $dados['observacoes_medicas'] ?? null,
            $dados['alergias'] ?? null,
            $dados['medicamentos_uso'] ?? null,
            $dados['historico_familiar'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Registra um novo médico
     */
    public function registrarMedico($dados) {
        $sql = "INSERT INTO medicos (
            nome, crm, uf_crm, rqe, especialidade, telefone, email,
            endereco, horario_atendimento
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $dados['nome'],
            $dados['crm'],
            $dados['uf_crm'],
            $dados['rqe'] ?? null,
            $dados['especialidade'],
            $dados['telefone'] ?? null,
            $dados['email'] ?? null,
            $dados['endereco'] ?? null,
            json_encode($dados['horario_atendimento'] ?? [])
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Agenda uma consulta neurológica
     */
    public function agendarConsulta($dados) {
        // Verificar se o horário está disponível
        if (!$this->verificarDisponibilidade($dados['data_consulta'], $dados['medico_id'] ?? null, $dados['tipo_exame'])) {
            throw new Exception('Horário não disponível para este tipo de exame');
        }
        
        $examType = self::EXAM_TYPES[$dados['tipo_exame']] ?? null;
        if (!$examType) {
            throw new Exception('Tipo de exame inválido');
        }
        
        $sql = "INSERT INTO consultas (
            paciente_id, medico_id, agendamento_id, tipo_exame, data_consulta,
            duracao_estimada, valor, convenio, numero_autorizacao,
            observacoes, instrucoes_preparo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $dados['paciente_id'],
            $dados['medico_id'] ?? null,
            $dados['agendamento_id'] ?? null,
            $dados['tipo_exame'],
            $dados['data_consulta'],
            $examType['duration'],
            $dados['valor'] ?? $examType['price'],
            $dados['convenio'] ?? null,
            $dados['numero_autorizacao'] ?? null,
            $dados['observacoes'] ?? null,
            $examType['preparation']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Verifica disponibilidade de horário
     */
    public function verificarDisponibilidade($dataHora, $medicoId = null, $tipoExame = null) {
        $data = new DateTime($dataHora);
        $diaSemana = $data->format('w'); // 0=domingo
        $hora = $data->format('H:i:s');
        
        // Verificar se existe horário configurado
        $sql = "SELECT COUNT(*) as count FROM horarios_disponiveis 
                WHERE dia_semana = ? AND ? BETWEEN hora_inicio AND hora_fim 
                AND ativo = TRUE";
        $params = [$diaSemana, $hora];
        
        if ($medicoId) {
            $sql .= " AND (medico_id = ? OR medico_id IS NULL)";
            $params[] = $medicoId;
        }
        
        if ($tipoExame) {
            $sql .= " AND tipo_exame = ?";
            $params[] = $tipoExame;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $horarioDisponivel = $stmt->fetch()['count'] > 0;
        
        if (!$horarioDisponivel) {
            return false;
        }
        
        // Verificar se não há consulta já agendada
        $sql = "SELECT COUNT(*) as count FROM consultas 
                WHERE data_consulta = ? AND status NOT IN ('cancelado', 'faltou')";
        $params = [$dataHora];
        
        if ($medicoId) {
            $sql .= " AND medico_id = ?";
            $params[] = $medicoId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $consultaExistente = $stmt->fetch()['count'] > 0;
        
        if ($consultaExistente) {
            return false;
        }
        
        // Verificar bloqueios
        $sql = "SELECT COUNT(*) as count FROM bloqueios_horarios 
                WHERE ? BETWEEN data_inicio AND data_fim";
        $params = [$dataHora];
        
        if ($medicoId) {
            $sql .= " AND (medico_id = ? OR medico_id IS NULL)";
            $params[] = $medicoId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $bloqueado = $stmt->fetch()['count'] > 0;
        
        return !$bloqueado;
    }
    
    /**
     * Busca pacientes
     */
    public function buscarPacientes($termo = '', $limite = 50) {
        $sql = "SELECT * FROM pacientes WHERE 1=1";
        $params = [];
        
        if (!empty($termo)) {
            $sql .= " AND (nome LIKE ? OR cpf LIKE ? OR telefone LIKE ?)";
            $termoBusca = "%$termo%";
            $params = [$termoBusca, $termoBusca, $termoBusca];
        }
        
        $sql .= " ORDER BY nome LIMIT ?";
        $params[] = $limite;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Busca consultas por período
     */
    public function buscarConsultasPorPeriodo($dataInicio, $dataFim, $tipoExame = null, $status = null) {
        $sql = "SELECT c.*, p.nome as paciente_nome, p.telefone as paciente_telefone,
                       m.nome as medico_nome, m.crm as medico_crm
                FROM consultas c
                LEFT JOIN pacientes p ON c.paciente_id = p.id
                LEFT JOIN medicos m ON c.medico_id = m.id
                WHERE DATE(c.data_consulta) BETWEEN ? AND ?";
        
        $params = [$dataInicio, $dataFim];
        
        if ($tipoExame) {
            $sql .= " AND c.tipo_exame = ?";
            $params[] = $tipoExame;
        }
        
        if ($status) {
            $sql .= " AND c.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY c.data_consulta";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Atualiza status da consulta
     */
    public function atualizarStatusConsulta($consultaId, $novoStatus, $observacoes = null) {
        $sql = "UPDATE consultas SET status = ?, updated_at = ?";
        $params = [$novoStatus, date('Y-m-d H:i:s')];
        
        if ($observacoes) {
            $sql .= ", observacoes = ?";
            $params[] = $observacoes;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $consultaId;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Registra resultado do exame
     */
    public function registrarResultado($consultaId, $dadosResultado) {
        $sql = "INSERT INTO resultados_exames (
            consulta_id, tipo_resultado, dados_tecnicos, interpretacao,
            conclusao, recomendacoes, arquivo_pdf, arquivo_imagem,
            qualidade_exame, artefatos_detectados, tempo_exame,
            medico_responsavel_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $consultaId,
            $dadosResultado['tipo_resultado'],
            json_encode($dadosResultado['dados_tecnicos'] ?? []),
            $dadosResultado['interpretacao'] ?? null,
            $dadosResultado['conclusao'] ?? null,
            $dadosResultado['recomendacoes'] ?? null,
            $dadosResultado['arquivo_pdf'] ?? null,
            $dadosResultado['arquivo_imagem'] ?? null,
            $dadosResultado['qualidade_exame'] ?? null,
            $dadosResultado['artefatos_detectados'] ?? null,
            $dadosResultado['tempo_exame'] ?? null,
            $dadosResultado['medico_responsavel_id'] ?? null
        ]);
        
        // Atualizar status da consulta
        $this->atualizarStatusConsulta($consultaId, 'laudo_pendente');
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Obtém estatísticas de exames
     */
    public function obterEstatisticas($dataInicio = null, $dataFim = null) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if ($dataInicio && $dataFim) {
            $whereClause .= " AND DATE(data_consulta) BETWEEN ? AND ?";
            $params = [$dataInicio, $dataFim];
        }
        
        $stats = [];
        
        // Total de consultas
        $sql = "SELECT COUNT(*) as total FROM consultas $whereClause";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats['total_consultas'] = $stmt->fetch()['total'];
        
        // Por status
        $sql = "SELECT status, COUNT(*) as count FROM consultas $whereClause GROUP BY status";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats['por_status'] = [];
        while ($row = $stmt->fetch()) {
            $stats['por_status'][$row['status']] = $row['count'];
        }
        
        // Por tipo de exame
        $sql = "SELECT tipo_exame, COUNT(*) as count FROM consultas $whereClause GROUP BY tipo_exame";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats['por_tipo_exame'] = [];
        while ($row = $stmt->fetch()) {
            $stats['por_tipo_exame'][$row['tipo_exame']] = $row['count'];
        }
        
        // Receita total
        $sql = "SELECT SUM(valor) as receita_total FROM consultas $whereClause AND status NOT IN ('cancelado', 'faltou')";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $stats['receita_total'] = $stmt->fetch()['receita_total'] ?? 0;
        
        return $stats;
    }
    
    /**
     * Envia instruções de preparo
     */
    public function enviarInstrucoesPreparo($consultaId) {
        $sql = "SELECT c.*, p.nome as paciente_nome, p.telefone as paciente_telefone
                FROM consultas c
                LEFT JOIN pacientes p ON c.paciente_id = p.id
                WHERE c.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$consultaId]);
        $consulta = $stmt->fetch();
        
        if (!$consulta) {
            throw new Exception('Consulta não encontrada');
        }
        
        $examType = self::EXAM_TYPES[$consulta['tipo_exame']] ?? null;
        if (!$examType) {
            throw new Exception('Tipo de exame não encontrado');
        }
        
        $dataExame = new DateTime($consulta['data_consulta']);
        $mensagem = "Olá {$consulta['paciente_nome']}!\n\n";
        $mensagem .= "Seu exame de {$examType['name']} está agendado para {$dataExame->format('d/m/Y')} às {$dataExame->format('H:i')}.\n\n";
        $mensagem .= "INSTRUÇÕES DE PREPARO:\n";
        $mensagem .= $examType['preparation'] . "\n\n";
        $mensagem .= "Duração estimada: {$examType['duration']} minutos\n\n";
        $mensagem .= "Em caso de dúvidas, entre em contato conosco.\n\n";
        $mensagem .= "Clínica Neurológica";
        
        // Atualizar data de envio das instruções
        $sql = "UPDATE consultas SET data_preparo_enviado = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([date('Y-m-d H:i:s'), $consultaId]);
        
        return $mensagem;
    }
    
    /**
     * Obtém horários disponíveis para um tipo de exame
     */
    public function obterHorariosDisponiveis($tipoExame, $data, $medicoId = null) {
        $dataObj = new DateTime($data);
        $diaSemana = $dataObj->format('w');
        
        $sql = "SELECT hora_inicio, hora_fim, intervalo_minutos 
                FROM horarios_disponiveis 
                WHERE dia_semana = ? AND tipo_exame = ? AND ativo = TRUE";
        $params = [$diaSemana, $tipoExame];
        
        if ($medicoId) {
            $sql .= " AND (medico_id = ? OR medico_id IS NULL)";
            $params[] = $medicoId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $horariosConfig = $stmt->fetchAll();
        
        $horariosDisponiveis = [];
        
        foreach ($horariosConfig as $config) {
            $inicio = new DateTime($data . ' ' . $config['hora_inicio']);
            $fim = new DateTime($data . ' ' . $config['hora_fim']);
            $intervalo = $config['intervalo_minutos'];
            
            while ($inicio < $fim) {
                $horarioStr = $inicio->format('Y-m-d H:i:s');
                
                if ($this->verificarDisponibilidade($horarioStr, $medicoId, $tipoExame)) {
                    $horariosDisponiveis[] = $inicio->format('H:i');
                }
                
                $inicio->add(new DateInterval("PT{$intervalo}M"));
            }
        }
        
        return $horariosDisponiveis;
    }
}
?>

