// Dashboard Neurológico - JavaScript

// Configuração da API
const API_BASE = window.location.origin;

// Estado global da aplicação
let currentTab = 'dashboard';
let examTypes = {};
let consultaStatus = {};
let pacientes = [];
let consultas = [];

// Utilitários
function showLoading() {
    document.getElementById('loading').classList.remove('hidden');
}

function hideLoading() {
    document.getElementById('loading').classList.add('hidden');
}

function showNotification(message, type = 'info') {
    // Implementar sistema de notificações
    console.log(`${type.toUpperCase()}: ${message}`);
    alert(message); // Temporário
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('pt-BR');
}

function formatDateTime(dateString) {
    return new Date(dateString).toLocaleString('pt-BR');
}

// Navegação entre abas
function showTab(tabName) {
    // Esconder todas as abas
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remover classe active de todos os botões
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Mostrar aba selecionada
    document.getElementById(tabName).classList.add('active');
    
    // Ativar botão correspondente
    event.target.classList.add('active');
    
    // Atualizar título da página
    updatePageTitle(tabName);
    
    // Carregar dados específicos da aba
    loadTabData(tabName);
    
    currentTab = tabName;
}

function updatePageTitle(tabName) {
    const titles = {
        'dashboard': 'Dashboard Neurológico',
        'consultas': 'Gestão de Consultas',
        'pacientes': 'Gestão de Pacientes',
        'agendamentos': 'Agendamentos',
        'resultados': 'Resultados de Exames',
        'ftp-manager': 'Gerenciador FTP'
    };
    
    const subtitles = {
        'dashboard': 'Visão geral do sistema expandido',
        'consultas': 'Gerencie consultas neurológicas especializadas',
        'pacientes': 'Cadastro e acompanhamento de pacientes',
        'agendamentos': 'Visualização dos agendamentos do sistema principal',
        'resultados': 'Gestão de resultados e laudos neurológicos',
        'ftp-manager': 'Gestão de arquivos e implantação do sistema'
    };
    
    document.getElementById('page-title').textContent = titles[tabName] || 'Dashboard';
    document.getElementById('page-subtitle').textContent = subtitles[tabName] || '';
}

function loadTabData(tabName) {
    switch(tabName) {
        case 'dashboard':
            loadDashboardData();
            break;
        case 'consultas':
            loadConsultas();
            break;
        case 'pacientes':
            loadPacientes();
            break;
        case 'agendamentos':
            loadAgendamentos();
            break;
        case 'resultados':
            // Implementar carregamento de resultados
            break;
        case 'ftp-manager':
            // FTP manager não precisa carregar dados automaticamente
            break;
    }
}

// Verificar status do sistema
async function checkSystemStatus() {
    try {
        const response = await fetch(`${API_BASE}/api/status`);
        const data = await response.json();
        
        if (data.status === 'online') {
            document.getElementById('status-indicator').textContent = 'Online';
            document.getElementById('status-indicator').className = 'text-xs font-medium text-green-800';
            document.getElementById('status-indicator').parentElement.className = 'bg-green-100 px-3 py-1 rounded-full';
        }
    } catch (error) {
        console.error('Erro ao verificar status:', error);
        document.getElementById('status-indicator').textContent = 'Offline';
        document.getElementById('status-indicator').className = 'text-xs font-medium text-red-800';
        document.getElementById('status-indicator').parentElement.className = 'bg-red-100 px-3 py-1 rounded-full';
    }
}

// Carregar dados do dashboard
async function loadDashboardData() {
    try {
        showLoading();
        
        // Carregar estatísticas
        const statsResponse = await fetch(`${API_BASE}/api/consultas/estatisticas`);
        const statsData = await statsResponse.json();
        
        if (statsData.success) {
            updateDashboardStats(statsData.estatisticas);
            updateCharts(statsData.estatisticas);
        }
        
        // Carregar consultas recentes
        const consultasResponse = await fetch(`${API_BASE}/api/consultas/periodo?data_inicio=${getDateDaysAgo(7)}&data_fim=${getCurrentDate()}`);
        const consultasData = await consultasResponse.json();
        
        if (consultasData.success) {
            updateRecentConsultas(consultasData.consultas.slice(0, 5));
        }
        
    } catch (error) {
        console.error('Erro ao carregar dados do dashboard:', error);
        showNotification('Erro ao carregar dados do dashboard', 'error');
    } finally {
        hideLoading();
    }
}

function updateDashboardStats(stats) {
    document.getElementById('total-consultas').textContent = stats.total_consultas || 0;
    document.getElementById('total-pacientes').textContent = Object.keys(pacientes).length || 0;
    document.getElementById('exames-pendentes').textContent = stats.por_status?.laudo_pendente || 0;
    document.getElementById('receita-mensal').textContent = formatCurrency(stats.receita_total || 0);
}

function updateCharts(stats) {
    // Gráfico de tipos de exame
    const examTypesCtx = document.getElementById('examTypesChart').getContext('2d');
    new Chart(examTypesCtx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(stats.por_tipo_exame || {}),
            datasets: [{
                data: Object.values(stats.por_tipo_exame || {}),
                backgroundColor: [
                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Gráfico de status das consultas
    const statusCtx = document.getElementById('consultaStatusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'bar',
        data: {
            labels: Object.keys(stats.por_status || {}),
            datasets: [{
                label: 'Consultas',
                data: Object.values(stats.por_status || {}),
                backgroundColor: '#3b82f6'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function updateRecentConsultas(consultas) {
    const tbody = document.getElementById('consultas-recentes');
    tbody.innerHTML = '';
    
    consultas.forEach(consulta => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${consulta.paciente_nome || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${examTypes[consulta.tipo_exame]?.name || consulta.tipo_exame}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${formatDateTime(consulta.data_consulta)}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium rounded-full ${getStatusBadgeClass(consulta.status)}">
                    ${consultaStatus[consulta.status] || consulta.status}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <button onclick="viewConsulta(${consulta.id})" class="text-blue-600 hover:text-blue-800">
                    <i data-lucide="eye" class="w-4 h-4"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Recriar ícones
    lucide.createIcons();
}

// Carregar tipos de exame
async function loadExamTypes() {
    try {
        const response = await fetch(`${API_BASE}/api/consultas/tipos-exame`);
        const data = await response.json();
        
        if (data.success) {
            examTypes = data.tipos_exame.reduce((acc, type) => {
                acc[type.code] = type;
                return acc;
            }, {});
            
            // Preencher selects
            populateExamTypeSelects();
        }
    } catch (error) {
        console.error('Erro ao carregar tipos de exame:', error);
    }
}

// Carregar status de consultas
async function loadConsultaStatus() {
    try {
        const response = await fetch(`${API_BASE}/api/consultas/status`);
        const data = await response.json();
        
        if (data.success) {
            consultaStatus = data.status;
            populateStatusSelects();
        }
    } catch (error) {
        console.error('Erro ao carregar status de consultas:', error);
    }
}

function populateExamTypeSelects() {
    const selects = ['filtro-tipo-exame', 'consulta-tipo-exame'];
    
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            // Limpar opções existentes (exceto a primeira)
            while (select.children.length > 1) {
                select.removeChild(select.lastChild);
            }
            
            // Adicionar tipos de exame
            Object.entries(examTypes).forEach(([code, type]) => {
                const option = document.createElement('option');
                option.value = code;
                option.textContent = type.name;
                select.appendChild(option);
            });
        }
    });
}

function populateStatusSelects() {
    const selects = ['filtro-status-consulta'];
    
    selects.forEach(selectId => {
        const select = document.getElementById(selectId);
        if (select) {
            // Limpar opções existentes (exceto a primeira)
            while (select.children.length > 1) {
                select.removeChild(select.lastChild);
            }
            
            // Adicionar status
            Object.entries(consultaStatus).forEach(([code, name]) => {
                const option = document.createElement('option');
                option.value = code;
                option.textContent = name;
                select.appendChild(option);
            });
        }
    });
}

// Gestão de Consultas
async function loadConsultas() {
    try {
        showLoading();
        
        const response = await fetch(`${API_BASE}/api/consultas`);
        const data = await response.json();
        
        if (data.success) {
            consultas = data.consultas;
            updateConsultasList(consultas);
        }
    } catch (error) {
        console.error('Erro ao carregar consultas:', error);
        showNotification('Erro ao carregar consultas', 'error');
    } finally {
        hideLoading();
    }
}

function updateConsultasList(consultasList) {
    const tbody = document.getElementById('lista-consultas');
    tbody.innerHTML = '';
    
    consultasList.forEach(consulta => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${consulta.paciente_nome || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${examTypes[consulta.tipo_exame]?.name || consulta.tipo_exame}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${formatDateTime(consulta.data_consulta)}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-medium rounded-full ${getStatusBadgeClass(consulta.status)}">
                    ${consultaStatus[consulta.status] || consulta.status}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${consulta.valor ? formatCurrency(consulta.valor) : 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <div class="flex space-x-2">
                    <button onclick="viewConsulta(${consulta.id})" class="text-blue-600 hover:text-blue-800" title="Visualizar">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <button onclick="editConsulta(${consulta.id})" class="text-green-600 hover:text-green-800" title="Editar">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                    </button>
                    <button onclick="cancelConsulta(${consulta.id})" class="text-red-600 hover:text-red-800" title="Cancelar">
                        <i data-lucide="x-circle" class="w-4 h-4"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Recriar ícones
    lucide.createIcons();
}

function filtrarConsultas() {
    const tipoExame = document.getElementById('filtro-tipo-exame').value;
    const status = document.getElementById('filtro-status-consulta').value;
    const dataInicio = document.getElementById('filtro-data-inicio-consulta').value;
    
    let consultasFiltradas = [...consultas];
    
    if (tipoExame) {
        consultasFiltradas = consultasFiltradas.filter(c => c.tipo_exame === tipoExame);
    }
    
    if (status) {
        consultasFiltradas = consultasFiltradas.filter(c => c.status === status);
    }
    
    if (dataInicio) {
        consultasFiltradas = consultasFiltradas.filter(c => 
            new Date(c.data_consulta) >= new Date(dataInicio)
        );
    }
    
    updateConsultasList(consultasFiltradas);
}

// Gestão de Pacientes
async function loadPacientes() {
    try {
        showLoading();
        
        const response = await fetch(`${API_BASE}/api/consultas/pacientes`);
        const data = await response.json();
        
        if (data.success) {
            pacientes = data.pacientes;
            updatePacientesList(pacientes);
            populatePacienteSelects();
        }
    } catch (error) {
        console.error('Erro ao carregar pacientes:', error);
        showNotification('Erro ao carregar pacientes', 'error');
    } finally {
        hideLoading();
    }
}

function updatePacientesList(pacientesList) {
    const tbody = document.getElementById('lista-pacientes');
    tbody.innerHTML = '';
    
    pacientesList.forEach(paciente => {
        const idade = calculateAge(paciente.data_nascimento);
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${paciente.nome}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${paciente.cpf || 'N/A'}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${paciente.telefone}</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${idade} anos</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">N/A</td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                <div class="flex space-x-2">
                    <button onclick="viewPaciente(${paciente.id})" class="text-blue-600 hover:text-blue-800" title="Visualizar">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </button>
                    <button onclick="editPaciente(${paciente.id})" class="text-green-600 hover:text-green-800" title="Editar">
                        <i data-lucide="edit" class="w-4 h-4"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
    
    // Recriar ícones
    lucide.createIcons();
}

function populatePacienteSelects() {
    const select = document.getElementById('consulta-paciente');
    if (select) {
        // Limpar opções existentes (exceto a primeira)
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        
        // Adicionar pacientes
        pacientes.forEach(paciente => {
            const option = document.createElement('option');
            option.value = paciente.id;
            option.textContent = paciente.nome;
            select.appendChild(option);
        });
    }
}

async function buscarPacientes() {
    const termo = document.getElementById('busca-paciente').value;
    
    try {
        showLoading();
        
        const response = await fetch(`${API_BASE}/api/consultas/pacientes?q=${encodeURIComponent(termo)}`);
        const data = await response.json();
        
        if (data.success) {
            updatePacientesList(data.pacientes);
        }
    } catch (error) {
        console.error('Erro ao buscar pacientes:', error);
        showNotification('Erro ao buscar pacientes', 'error');
    } finally {
        hideLoading();
    }
}

// Agendamentos (integração com sistema principal)
async function loadAgendamentos() {
    try {
        showLoading();
        
        const response = await fetch(`${API_BASE}/api/agendamentos`);
        const data = await response.json();
        
        if (data.success) {
            showNotification('Agendamentos carregados com sucesso', 'success');
            // Implementar exibição dos agendamentos
        }
    } catch (error) {
        console.error('Erro ao carregar agendamentos:', error);
        showNotification('Erro ao carregar agendamentos', 'error');
    } finally {
        hideLoading();
    }
}

// FTP Manager
async function testFTPConnection() {
    try {
        showLoading();
        
        const response = await fetch(`${API_BASE}/api/ftp/test`);
        const data = await response.json();
        
        const statusDiv = document.getElementById('ftp-status');
        
        if (data.success) {
            statusDiv.innerHTML = `
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <strong>Conexão bem-sucedida!</strong><br>
                    ${data.message}<br>
                    Arquivos encontrados: ${data.files_count}
                </div>
            `;
        } else {
            statusDiv.innerHTML = `
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <strong>Erro na conexão:</strong><br>
                    ${data.message}
                </div>
            `;
        }
    } catch (error) {
        console.error('Erro ao testar conexão FTP:', error);
        document.getElementById('ftp-status').innerHTML = `
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <strong>Erro:</strong> ${error.message}
            </div>
        `;
    } finally {
        hideLoading();
    }
}

async function deploySystem() {
    if (!confirm('Tem certeza que deseja implantar o sistema? Esta ação irá sobrescrever os arquivos no servidor.')) {
        return;
    }
    
    try {
        showLoading();
        
        const response = await fetch(`${API_BASE}/api/ftp/deploy-system`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Sistema implantado com sucesso!', 'success');
            
            // Mostrar detalhes da implantação
            const statusDiv = document.getElementById('ftp-status');
            statusDiv.innerHTML = `
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    <strong>Implantação concluída!</strong><br>
                    Arquivos enviados: ${data.deployment_result.total_uploaded}<br>
                    Erros: ${data.deployment_result.total_errors}
                </div>
            `;
        } else {
            showNotification('Erro na implantação: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Erro ao implantar sistema:', error);
        showNotification('Erro ao implantar sistema: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
}

// Modais
function showModal(modalName) {
    document.getElementById(`modal-${modalName}`).classList.remove('hidden');
}

function hideModal(modalName) {
    document.getElementById(`modal-${modalName}`).classList.add('hidden');
}

// Formulários
document.getElementById('form-nova-consulta').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        paciente_id: document.getElementById('consulta-paciente').value,
        tipo_exame: document.getElementById('consulta-tipo-exame').value,
        data_consulta: document.getElementById('consulta-data').value + ' ' + document.getElementById('consulta-horario').value,
        observacoes: document.getElementById('consulta-observacoes').value
    };
    
    try {
        showLoading();
        
        const response = await fetch(`${API_BASE}/api/consultas/agendar`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Consulta agendada com sucesso!', 'success');
            hideModal('nova-consulta');
            loadConsultas();
            this.reset();
        } else {
            showNotification('Erro ao agendar consulta: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Erro ao agendar consulta:', error);
        showNotification('Erro ao agendar consulta: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
});

document.getElementById('form-novo-paciente').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = {
        nome: document.getElementById('paciente-nome').value,
        cpf: document.getElementById('paciente-cpf').value,
        data_nascimento: document.getElementById('paciente-nascimento').value,
        sexo: document.getElementById('paciente-sexo').value,
        telefone: document.getElementById('paciente-telefone').value,
        email: document.getElementById('paciente-email').value,
        endereco: document.getElementById('paciente-endereco').value,
        convenio: document.getElementById('paciente-convenio').value,
        numero_carteirinha: document.getElementById('paciente-carteirinha').value
    };
    
    try {
        showLoading();
        
        const response = await fetch(`${API_BASE}/api/consultas/pacientes`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Paciente cadastrado com sucesso!', 'success');
            hideModal('novo-paciente');
            loadPacientes();
            this.reset();
        } else {
            showNotification('Erro ao cadastrar paciente: ' + data.error, 'error');
        }
    } catch (error) {
        console.error('Erro ao cadastrar paciente:', error);
        showNotification('Erro ao cadastrar paciente: ' + error.message, 'error');
    } finally {
        hideLoading();
    }
});

// Utilitários auxiliares
function getStatusBadgeClass(status) {
    const classes = {
        'agendado': 'bg-blue-100 text-blue-800',
        'confirmado': 'bg-green-100 text-green-800',
        'em_andamento': 'bg-yellow-100 text-yellow-800',
        'concluido': 'bg-purple-100 text-purple-800',
        'cancelado': 'bg-red-100 text-red-800',
        'faltou': 'bg-gray-100 text-gray-800'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
}

function calculateAge(birthDate) {
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
        age--;
    }
    
    return age;
}

function getCurrentDate() {
    return new Date().toISOString().split('T')[0];
}

function getDateDaysAgo(days) {
    const date = new Date();
    date.setDate(date.getDate() - days);
    return date.toISOString().split('T')[0];
}

function refreshData() {
    loadTabData(currentTab);
    checkSystemStatus();
}

// Funções de ação (implementar conforme necessário)
function viewConsulta(id) {
    showNotification('Visualizar consulta: ' + id, 'info');
}

function editConsulta(id) {
    showNotification('Editar consulta: ' + id, 'info');
}

function cancelConsulta(id) {
    if (confirm('Tem certeza que deseja cancelar esta consulta?')) {
        // Implementar cancelamento
        showNotification('Consulta cancelada: ' + id, 'info');
    }
}

function viewPaciente(id) {
    showNotification('Visualizar paciente: ' + id, 'info');
}

function editPaciente(id) {
    showNotification('Editar paciente: ' + id, 'info');
}

// Carregar horários disponíveis quando tipo de exame e data forem selecionados
document.getElementById('consulta-tipo-exame').addEventListener('change', loadAvailableSlots);
document.getElementById('consulta-data').addEventListener('change', loadAvailableSlots);

async function loadAvailableSlots() {
    const tipoExame = document.getElementById('consulta-tipo-exame').value;
    const data = document.getElementById('consulta-data').value;
    const horarioSelect = document.getElementById('consulta-horario');
    
    // Limpar horários
    while (horarioSelect.children.length > 1) {
        horarioSelect.removeChild(horarioSelect.lastChild);
    }
    
    if (!tipoExame || !data) return;
    
    try {
        const response = await fetch(`${API_BASE}/api/consultas/horarios-disponiveis?tipo_exame=${tipoExame}&data=${data}`);
        const result = await response.json();
        
        if (result.success && result.horarios_disponiveis.length > 0) {
            result.horarios_disponiveis.forEach(horario => {
                const option = document.createElement('option');
                option.value = horario;
                option.textContent = horario;
                horarioSelect.appendChild(option);
            });
        } else {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'Nenhum horário disponível';
            option.disabled = true;
            horarioSelect.appendChild(option);
        }
    } catch (error) {
        console.error('Erro ao carregar horários:', error);
    }
}

