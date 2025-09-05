<?php
require_once __DIR__ . '/../config.php';

class AnaliseEEG {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getConnection();
    }
    
    public function criar($dados) {
        $sql = "INSERT INTO analises_eeg (
            laudo_id, arquivo_pdf, total_paginas, paginas_limpas,
            paginas_artefato, percentual_qualidade, recomendacao,
            dados_paciente, relatorio_qualidade, qeeg_data, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            $dados['laudo_id'],
            $dados['arquivo_pdf'],
            $dados['total_paginas'] ?? null,
            $dados['paginas_limpas'] ?? null,
            $dados['paginas_artefato'] ?? null,
            $dados['percentual_qualidade'] ?? null,
            $dados['recomendacao'] ?? 'PROCESSANDO',
            $this->jsonEncode($dados['dados_paciente'] ?? null),
            $this->jsonEncode($dados['relatorio_qualidade'] ?? null),
            $this->jsonEncode($dados['qeeg_data'] ?? null),
            $dados['status'] ?? 'processando'
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    public function listar($filtros = []) {
        $sql = "SELECT a.*, l.nome_paciente, l.numero_controle 
                FROM analises_eeg a 
                LEFT JOIN laudos l ON a.laudo_id = l.id 
                WHERE 1=1";
        $params = [];
        
        if (isset($filtros['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filtros['status'];
        }
        
        if (isset($filtros['recomendacao'])) {
            $sql .= " AND a.recomendacao = ?";
            $params[] = $filtros['recomendacao'];
        }
        
        if (isset($filtros['laudo_id'])) {
            $sql .= " AND a.laudo_id = ?";
            $params[] = $filtros['laudo_id'];
        }
        
        $sql .= " ORDER BY a.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $resultados = [];
        while ($row = $stmt->fetch()) {
            $resultados[] = $this->formatarAnalise($row);
        }
        
        return $resultados;
    }
    
    public function buscarPorId($id) {
        $sql = "SELECT a.*, l.nome_paciente, l.numero_controle 
                FROM analises_eeg a 
                LEFT JOIN laudos l ON a.laudo_id = l.id 
                WHERE a.id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        
        $analise = $stmt->fetch();
        return $analise ? $this->formatarAnalise($analise) : null;
    }
    
    public function buscarPorLaudo($laudo_id) {
        $sql = "SELECT * FROM analises_eeg WHERE laudo_id = ? ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$laudo_id]);
        
        $resultados = [];
        while ($row = $stmt->fetch()) {
            $resultados[] = $this->formatarAnalise($row);
        }
        
        return $resultados;
    }
    
    public function atualizar($id, $dados) {
        $campos_permitidos = [
            'total_paginas', 'paginas_limpas', 'paginas_artefato',
            'percentual_qualidade', 'recomendacao', 'dados_paciente',
            'relatorio_qualidade', 'qeeg_data', 'status'
        ];
        
        $sets = [];
        $params = [];
        
        foreach ($campos_permitidos as $campo) {
            if (isset($dados[$campo])) {
                $sets[] = "$campo = ?";
                if (in_array($campo, ['dados_paciente', 'relatorio_qualidade', 'qeeg_data'])) {
                    $params[] = $this->jsonEncode($dados[$campo]);
                } else {
                    $params[] = $dados[$campo];
                }
            }
        }
        
        if (empty($sets)) {
            throw new Exception('Nenhum campo para atualizar');
        }
        
        $sets[] = "updated_at = ?";
        $params[] = date('Y-m-d H:i:s');
        $params[] = $id;
        
        $sql = "UPDATE analises_eeg SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $this->buscarPorId($id);
    }
    
    public function processarPDF($laudo_id, $arquivo_pdf) {
        // Criar registro inicial
        $analise_id = $this->criar([
            'laudo_id' => $laudo_id,
            'arquivo_pdf' => $arquivo_pdf,
            'status' => 'processando'
        ]);
        
        try {
            // Simular processamento (aqui seria integrado com o sistema Python)
            $resultado_processamento = $this->simularProcessamento($arquivo_pdf);
            
            // Atualizar com resultados
            $this->atualizar($analise_id, [
                'total_paginas' => $resultado_processamento['total_paginas'],
                'paginas_limpas' => $resultado_processamento['paginas_limpas'],
                'paginas_artefato' => $resultado_processamento['paginas_artefato'],
                'percentual_qualidade' => $resultado_processamento['percentual_qualidade'],
                'recomendacao' => $resultado_processamento['recomendacao'],
                'dados_paciente' => $resultado_processamento['dados_paciente'],
                'relatorio_qualidade' => $resultado_processamento['relatorio_qualidade'],
                'status' => 'concluido'
            ]);
            
            return $this->buscarPorId($analise_id);
            
        } catch (Exception $e) {
            // Marcar como erro
            $this->atualizar($analise_id, [
                'status' => 'erro',
                'recomendacao' => 'ERRO'
            ]);
            
            throw $e;
        }
    }
    
    public function estatisticas() {
        $stats = [];
        
        // Total de análises
        $sql = "SELECT COUNT(*) as total FROM analises_eeg";
        $stmt = $this->pdo->query($sql);
        $stats['total_analises'] = $stmt->fetch()['total'];
        
        // Por status
        $sql = "SELECT status, COUNT(*) as count FROM analises_eeg GROUP BY status";
        $stmt = $this->pdo->query($sql);
        $por_status = [];
        while ($row = $stmt->fetch()) {
            $por_status[$row['status']] = $row['count'];
        }
        $stats['por_status'] = $por_status;
        
        // Por recomendação
        $sql = "SELECT recomendacao, COUNT(*) as count FROM analises_eeg WHERE status = 'concluido' GROUP BY recomendacao";
        $stmt = $this->pdo->query($sql);
        $por_recomendacao = [];
        while ($row = $stmt->fetch()) {
            $por_recomendacao[$row['recomendacao']] = $row['count'];
        }
        $stats['por_recomendacao'] = $por_recomendacao;
        
        // Qualidade média
        $sql = "SELECT AVG(percentual_qualidade) as media FROM analises_eeg WHERE percentual_qualidade IS NOT NULL";
        $stmt = $this->pdo->query($sql);
        $stats['qualidade_media'] = round($stmt->fetch()['media'] ?? 0, 2);
        
        return $stats;
    }
    
    private function formatarAnalise($analise) {
        return [
            'id' => $analise['id'],
            'laudo_id' => $analise['laudo_id'],
            'arquivo_pdf' => $analise['arquivo_pdf'],
            'total_paginas' => $analise['total_paginas'],
            'paginas_limpas' => $analise['paginas_limpas'],
            'paginas_artefato' => $analise['paginas_artefato'],
            'percentual_qualidade' => $analise['percentual_qualidade'],
            'recomendacao' => $analise['recomendacao'],
            'dados_paciente' => $this->jsonDecode($analise['dados_paciente']),
            'relatorio_qualidade' => $this->jsonDecode($analise['relatorio_qualidade']),
            'qeeg_data' => $this->jsonDecode($analise['qeeg_data']),
            'status' => $analise['status'],
            'created_at' => $analise['created_at'],
            'updated_at' => $analise['updated_at'],
            // Dados do laudo (se disponível)
            'nome_paciente' => $analise['nome_paciente'] ?? null,
            'numero_controle' => $analise['numero_controle'] ?? null,
            // Estatísticas calculadas
            'qualidade_descritiva' => $this->getQualidadeDescritiva($analise['percentual_qualidade']),
            'estatisticas_qualidade' => $this->getEstatisticasQualidade($analise)
        ];
    }
    
    private function getQualidadeDescritiva($percentual) {
        if ($percentual === null) return "Não avaliada";
        
        if ($percentual >= 80) return "Excelente";
        if ($percentual >= 60) return "Boa";
        if ($percentual >= 40) return "Regular";
        return "Ruim";
    }
    
    private function getEstatisticasQualidade($analise) {
        if (!$analise['total_paginas']) return null;
        
        $total_paginas = $analise['total_paginas'];
        $paginas_limpas = $analise['paginas_limpas'] ?? 0;
        $paginas_artefato = $analise['paginas_artefato'] ?? 0;
        
        return [
            'total_paginas' => $total_paginas,
            'paginas_limpas' => $paginas_limpas,
            'paginas_com_artefato' => $paginas_artefato,
            'percentual_limpas' => round($paginas_limpas / $total_paginas * 100, 2),
            'percentual_artefatos' => round($paginas_artefato / $total_paginas * 100, 2)
        ];
    }
    
    private function simularProcessamento($arquivo_pdf) {
        // Simulação do processamento - aqui seria integrado com o sistema Python real
        $total_paginas = rand(10, 50);
        $paginas_limpas = rand(5, $total_paginas);
        $paginas_artefato = $total_paginas - $paginas_limpas;
        $percentual_qualidade = round($paginas_limpas / $total_paginas * 100, 2);
        
        $recomendacao = 'OK';
        if ($percentual_qualidade < 40) {
            $recomendacao = 'REPETIR';
        } elseif ($percentual_qualidade < 70) {
            $recomendacao = 'REVISAR';
        }
        
        return [
            'total_paginas' => $total_paginas,
            'paginas_limpas' => $paginas_limpas,
            'paginas_artefato' => $paginas_artefato,
            'percentual_qualidade' => $percentual_qualidade,
            'recomendacao' => $recomendacao,
            'dados_paciente' => [
                'nome_extraido' => 'Paciente Exemplo',
                'data_nascimento_extraida' => '01/01/1980'
            ],
            'relatorio_qualidade' => [
                'pagina_1' => ['qualidade' => 'boa', 'artefatos' => []],
                'pagina_2' => ['qualidade' => 'regular', 'artefatos' => ['movimento']]
            ]
        ];
    }
    
    private function jsonEncode($data) {
        if ($data === null) return null;
        if (DatabaseConfig::getDbType() === 'sqlite') {
            return json_encode($data);
        }
        return json_encode($data);
    }
    
    private function jsonDecode($data) {
        if ($data === null) return null;
        return json_decode($data, true);
    }
}
?>

