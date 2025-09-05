<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class FTPManager {
    private $ftpHost;
    private $ftpUser;
    private $ftpPass;
    private $ftpPath;
    private $connection;
    
    public function __construct() {
        // Credenciais FTP fornecidas
        $this->ftpHost = 'ftp.examesneurologicos.com.br';
        $this->ftpUser = 'examesneurologicos';
        $this->ftpPass = 'FFet63gehj788';
        $this->ftpPath = '/home/examesneurologicos';
    }
    
    /**
     * Conecta ao servidor FTP
     */
    public function connect() {
        $this->connection = ftp_connect($this->ftpHost);
        
        if (!$this->connection) {
            throw new Exception('Não foi possível conectar ao servidor FTP');
        }
        
        $login = ftp_login($this->connection, $this->ftpUser, $this->ftpPass);
        
        if (!$login) {
            throw new Exception('Falha na autenticação FTP');
        }
        
        // Modo passivo para melhor compatibilidade
        ftp_pasv($this->connection, true);
        
        return true;
    }
    
    /**
     * Desconecta do servidor FTP
     */
    public function disconnect() {
        if ($this->connection) {
            ftp_close($this->connection);
        }
    }
    
    /**
     * Lista arquivos no diretório FTP
     */
    public function listFiles($directory = null) {
        if (!$this->connection) {
            $this->connect();
        }
        
        $dir = $directory ? $this->ftpPath . '/' . $directory : $this->ftpPath;
        
        $files = ftp_nlist($this->connection, $dir);
        
        if ($files === false) {
            throw new Exception('Erro ao listar arquivos do diretório');
        }
        
        $fileList = [];
        foreach ($files as $file) {
            $fileName = basename($file);
            if ($fileName !== '.' && $fileName !== '..') {
                $fileList[] = [
                    'name' => $fileName,
                    'path' => $file,
                    'size' => ftp_size($this->connection, $file),
                    'modified' => ftp_mdtm($this->connection, $file)
                ];
            }
        }
        
        return $fileList;
    }
    
    /**
     * Faz upload de um arquivo
     */
    public function uploadFile($localFile, $remoteFile) {
        if (!$this->connection) {
            $this->connect();
        }
        
        $remotePath = $this->ftpPath . '/' . $remoteFile;
        
        $upload = ftp_put($this->connection, $remotePath, $localFile, FTP_BINARY);
        
        if (!$upload) {
            throw new Exception('Erro ao fazer upload do arquivo');
        }
        
        return true;
    }
    
    /**
     * Faz download de um arquivo
     */
    public function downloadFile($remoteFile, $localFile) {
        if (!$this->connection) {
            $this->connect();
        }
        
        $remotePath = $this->ftpPath . '/' . $remoteFile;
        
        $download = ftp_get($this->connection, $localFile, $remotePath, FTP_BINARY);
        
        if (!$download) {
            throw new Exception('Erro ao fazer download do arquivo');
        }
        
        return true;
    }
    
    /**
     * Deleta um arquivo
     */
    public function deleteFile($remoteFile) {
        if (!$this->connection) {
            $this->connect();
        }
        
        $remotePath = $this->ftpPath . '/' . $remoteFile;
        
        $delete = ftp_delete($this->connection, $remotePath);
        
        if (!$delete) {
            throw new Exception('Erro ao deletar arquivo');
        }
        
        return true;
    }
    
    /**
     * Cria um diretório
     */
    public function createDirectory($directory) {
        if (!$this->connection) {
            $this->connect();
        }
        
        $remotePath = $this->ftpPath . '/' . $directory;
        
        $mkdir = ftp_mkdir($this->connection, $remotePath);
        
        if (!$mkdir) {
            throw new Exception('Erro ao criar diretório');
        }
        
        return true;
    }
    
    /**
     * Faz upload de múltiplos arquivos de um diretório
     */
    public function uploadDirectory($localDir, $remoteDir = '') {
        if (!$this->connection) {
            $this->connect();
        }
        
        $uploadedFiles = [];
        $errors = [];
        
        if (!is_dir($localDir)) {
            throw new Exception('Diretório local não existe');
        }
        
        // Criar diretório remoto se especificado
        if ($remoteDir && !empty($remoteDir)) {
            try {
                $this->createDirectory($remoteDir);
            } catch (Exception $e) {
                // Diretório pode já existir
            }
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($localDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $relativePath = str_replace($localDir . '/', '', $file->getPathname());
            $remoteFilePath = $remoteDir ? $remoteDir . '/' . $relativePath : $relativePath;
            
            if ($file->isDir()) {
                try {
                    $this->createDirectory($remoteFilePath);
                } catch (Exception $e) {
                    // Diretório pode já existir
                }
            } else {
                try {
                    $this->uploadFile($file->getPathname(), $remoteFilePath);
                    $uploadedFiles[] = $remoteFilePath;
                } catch (Exception $e) {
                    $errors[] = [
                        'file' => $relativePath,
                        'error' => $e->getMessage()
                    ];
                }
            }
        }
        
        return [
            'uploaded_files' => $uploadedFiles,
            'errors' => $errors,
            'total_uploaded' => count($uploadedFiles),
            'total_errors' => count($errors)
        ];
    }
    
    /**
     * Testa a conexão FTP
     */
    public function testConnection() {
        try {
            $this->connect();
            $files = $this->listFiles();
            $this->disconnect();
            
            return [
                'success' => true,
                'message' => 'Conexão FTP estabelecida com sucesso',
                'files_count' => count($files)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro na conexão FTP: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtém informações do servidor FTP
     */
    public function getServerInfo() {
        if (!$this->connection) {
            $this->connect();
        }
        
        return [
            'host' => $this->ftpHost,
            'user' => $this->ftpUser,
            'path' => $this->ftpPath,
            'system_type' => ftp_systype($this->connection),
            'current_directory' => ftp_pwd($this->connection)
        ];
    }
}

// Processar requisições da API
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '';

try {
    $ftpManager = new FTPManager();
    
    switch ($method) {
        case 'GET':
            if (preg_match('/^\/test$/', $path)) {
                // GET /ftp_manager/test
                $result = $ftpManager->testConnection();
                echo json_encode($result);
            } elseif (preg_match('/^\/info$/', $path)) {
                // GET /ftp_manager/info
                $ftpManager->connect();
                $info = $ftpManager->getServerInfo();
                $ftpManager->disconnect();
                echo json_encode([
                    'success' => true,
                    'server_info' => $info
                ]);
            } elseif (preg_match('/^\/files$/', $path)) {
                // GET /ftp_manager/files?directory=X
                $directory = $_GET['directory'] ?? null;
                $ftpManager->connect();
                $files = $ftpManager->listFiles($directory);
                $ftpManager->disconnect();
                echo json_encode([
                    'success' => true,
                    'files' => $files,
                    'directory' => $directory
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (preg_match('/^\/upload$/', $path)) {
                // POST /ftp_manager/upload
                if (!isset($_FILES['file'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Nenhum arquivo enviado']);
                    exit();
                }
                
                $uploadedFile = $_FILES['file'];
                $remoteFileName = $_POST['remote_name'] ?? $uploadedFile['name'];
                
                if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Erro no upload do arquivo']);
                    exit();
                }
                
                $ftpManager->connect();
                $ftpManager->uploadFile($uploadedFile['tmp_name'], $remoteFileName);
                $ftpManager->disconnect();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Arquivo enviado com sucesso',
                    'remote_file' => $remoteFileName
                ]);
            } elseif (preg_match('/^\/upload-directory$/', $path)) {
                // POST /ftp_manager/upload-directory
                if (!$input || !isset($input['local_directory'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Diretório local é obrigatório']);
                    exit();
                }
                
                $localDir = $input['local_directory'];
                $remoteDir = $input['remote_directory'] ?? '';
                
                if (!is_dir($localDir)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Diretório local não existe']);
                    exit();
                }
                
                $ftpManager->connect();
                $result = $ftpManager->uploadDirectory($localDir, $remoteDir);
                $ftpManager->disconnect();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Upload do diretório concluído',
                    'result' => $result
                ]);
            } elseif (preg_match('/^\/deploy-system$/', $path)) {
                // POST /ftp_manager/deploy-system
                $systemDir = '/home/ubuntu/sistema_expandido_consultas';
                
                if (!is_dir($systemDir)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Sistema não encontrado']);
                    exit();
                }
                
                $ftpManager->connect();
                $result = $ftpManager->uploadDirectory($systemDir, '');
                $ftpManager->disconnect();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Sistema implantado com sucesso',
                    'deployment_result' => $result
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint não encontrado']);
            }
            break;
            
        case 'DELETE':
            if (preg_match('/^\/file\/(.+)$/', $path, $matches)) {
                // DELETE /ftp_manager/file/{filename}
                $fileName = urldecode($matches[1]);
                
                $ftpManager->connect();
                $ftpManager->deleteFile($fileName);
                $ftpManager->disconnect();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Arquivo deletado com sucesso',
                    'deleted_file' => $fileName
                ]);
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

