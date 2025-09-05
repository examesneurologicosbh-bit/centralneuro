<?php
class DatabaseConfig {
    // Configurações MySQL (primário)
    private static $mysql_config = [
        'host' => 'mysql.examesneurologicos.com.br',
        'dbname' => 'examesneurolog02',
        'username' => 'examesneurolog02',
        'password' => 'FRED4321',
        'charset' => 'utf8mb4'
    ];
    
    // Configurações SQLite (fallback)
    private static $sqlite_config = [
        'path' => __DIR__ . '/database/app.db'
    ];
    
    private static $connection = null;
    private static $db_type = null;
    
    public static function getConnection() {
        if (self::$connection === null) {
            self::$connection = self::createConnection();
        }
        return self::$connection;
    }
    
    private static function createConnection() {
        // Tentar MySQL primeiro
        try {
            $mysql = self::$mysql_config;
            $dsn = "mysql:host={$mysql['host']};dbname={$mysql['dbname']};charset={$mysql['charset']}";
            $pdo = new PDO($dsn, $mysql['username'], $mysql['password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            self::$db_type = 'mysql';
            error_log("Conectado ao MySQL com sucesso");
            return $pdo;
            
        } catch (PDOException $e) {
            error_log("Erro ao conectar no MySQL: " . $e->getMessage());
            
            // Fallback para SQLite
            try {
                // Criar diretório se não existir
                $db_dir = dirname(self::$sqlite_config['path']);
                if (!is_dir($db_dir)) {
                    mkdir($db_dir, 0755, true);
                }
                
                $dsn = "sqlite:" . self::$sqlite_config['path'];
                $pdo = new PDO($dsn);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                
                self::$db_type = 'sqlite';
                error_log("Conectado ao SQLite como fallback");
                return $pdo;
                
            } catch (PDOException $e) {
                error_log("Erro ao conectar no SQLite: " . $e->getMessage());
                throw new Exception("Não foi possível conectar a nenhum banco de dados");
            }
        }
    }
    
    public static function getDbType() {
        if (self::$db_type === null) {
            self::getConnection(); // Força a conexão
        }
        return self::$db_type;
    }
    
    public static function createTables() {
        $pdo = self::getConnection();
        $db_type = self::getDbType();
        
        // SQL para MySQL
        if ($db_type === 'mysql') {
            $sql_agendamentos = "
                CREATE TABLE IF NOT EXISTS agendamentos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    nome_paciente VARCHAR(100) NOT NULL,
                    telefone VARCHAR(20) NOT NULL,
                    email VARCHAR(100),
                    data_agendamento DATETIME NOT NULL,
                    tipo_exame VARCHAR(100) NOT NULL,
                    status VARCHAR(20) DEFAULT 'agendado',
                    observacoes TEXT,
                    data_nascimento DATE,
                    sexo VARCHAR(10),
                    rg VARCHAR(20),
                    cpf VARCHAR(14),
                    endereco TEXT,
                    convenio VARCHAR(100),
                    indicacao VARCHAR(200),
                    medico_solicitante VARCHAR(100),
                    laudo_id INT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ";
            
            $sql_laudos = "
                CREATE TABLE IF NOT EXISTS laudos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    codigo_validador VARCHAR(20) UNIQUE NOT NULL,
                    nome_paciente VARCHAR(100) NOT NULL,
                    numero_controle VARCHAR(20) NOT NULL,
                    data_nascimento DATE NOT NULL,
                    indicacao VARCHAR(200) NOT NULL,
                    sexo VARCHAR(10) NOT NULL,
                    data_exame DATE NOT NULL,
                    rg VARCHAR(20),
                    cpf VARCHAR(14),
                    convenio VARCHAR(100),
                    tipo_exame VARCHAR(100) NOT NULL,
                    medico_nome VARCHAR(100) NOT NULL,
                    medico_crm VARCHAR(20) NOT NULL,
                    medico_rqe VARCHAR(20),
                    medico_especialidade VARCHAR(50) NOT NULL,
                    status VARCHAR(20) DEFAULT 'pendente',
                    conteudo_laudo TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ";
            
            $sql_analises_eeg = "
                CREATE TABLE IF NOT EXISTS analises_eeg (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    laudo_id INT NOT NULL,
                    arquivo_pdf VARCHAR(255) NOT NULL,
                    total_paginas INT,
                    paginas_limpas INT,
                    paginas_artefato INT,
                    percentual_qualidade DECIMAL(5,2),
                    recomendacao VARCHAR(50),
                    dados_paciente JSON,
                    relatorio_qualidade JSON,
                    qeeg_data JSON,
                    status VARCHAR(20) DEFAULT 'processando',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (laudo_id) REFERENCES laudos(id)
                )
            ";
        } else {
            // SQL para SQLite
            $sql_agendamentos = "
                CREATE TABLE IF NOT EXISTS agendamentos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    nome_paciente TEXT NOT NULL,
                    telefone TEXT NOT NULL,
                    email TEXT,
                    data_agendamento TEXT NOT NULL,
                    tipo_exame TEXT NOT NULL,
                    status TEXT DEFAULT 'agendado',
                    observacoes TEXT,
                    data_nascimento TEXT,
                    sexo TEXT,
                    rg TEXT,
                    cpf TEXT,
                    endereco TEXT,
                    convenio TEXT,
                    indicacao TEXT,
                    medico_solicitante TEXT,
                    laudo_id INTEGER,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ";
            
            $sql_laudos = "
                CREATE TABLE IF NOT EXISTS laudos (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    codigo_validador TEXT UNIQUE NOT NULL,
                    nome_paciente TEXT NOT NULL,
                    numero_controle TEXT NOT NULL,
                    data_nascimento TEXT NOT NULL,
                    indicacao TEXT NOT NULL,
                    sexo TEXT NOT NULL,
                    data_exame TEXT NOT NULL,
                    rg TEXT,
                    cpf TEXT,
                    convenio TEXT,
                    tipo_exame TEXT NOT NULL,
                    medico_nome TEXT NOT NULL,
                    medico_crm TEXT NOT NULL,
                    medico_rqe TEXT,
                    medico_especialidade TEXT NOT NULL,
                    status TEXT DEFAULT 'pendente',
                    conteudo_laudo TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )
            ";
            
            $sql_analises_eeg = "
                CREATE TABLE IF NOT EXISTS analises_eeg (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    laudo_id INTEGER NOT NULL,
                    arquivo_pdf TEXT NOT NULL,
                    total_paginas INTEGER,
                    paginas_limpas INTEGER,
                    paginas_artefato INTEGER,
                    percentual_qualidade REAL,
                    recomendacao TEXT,
                    dados_paciente TEXT,
                    relatorio_qualidade TEXT,
                    qeeg_data TEXT,
                    status TEXT DEFAULT 'processando',
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (laudo_id) REFERENCES laudos(id)
                )
            ";
        }
        
        // Executar SQLs
        $pdo->exec($sql_agendamentos);
        $pdo->exec($sql_laudos);
        $pdo->exec($sql_analises_eeg);
        
        return true;
    }
}
?>

