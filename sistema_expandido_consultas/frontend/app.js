// Configuração da API
const API_BASE = window.location.origin;

// Utilitários
function showLoading() {
    document.getElementById('loading').classList.remove('hidden');
}

function hideLoading() {
    document.getElementById('loading').classList.add('hidden');
}

function showTab(tabName) {
    // Esconder todas as abas
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remover classe active de todos os botões
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active');
        button.classList.add('text-gray-500', 'border-transparent');
        button.classList.remove('text-blue-600', 'border-blue-500');
    });
    
    // Mostrar aba selecionada
    document.getElementById(tabName).classList.add('active');
    
    // Ativar botão correspondente
    event.target.classList.add('active', 'text-blue-600', 'border-blue-500');
    event.target.classList.remove('text-gray-500', 'border-transparent');
    
    // Carregar dados específicos da aba
    switch(tabName) {
        case 'dashboard':
            loadDashboard();
            break;
        case 'agendamentos':
            loadAgendamentos();
            break;
        case 'laudos':
            loadLaudos();
            break;
        case 'analises':
            loadAnalises();
            break;
    }
}

// API Calls
async function apiCall(endpoint, options = {}) {
    try {
        const response = await fetch(`${API_BASE}${endpoint}`, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        alert('Erro na comunicação com o servidor: ' + error.message);
        throw error;
    }
}

// Dashboard
async function loadDashboard() {
    try {
        showLoading();
        
        // Carregar estatísticas
        const [agendamentosStats, laudosStats, analisesStats] = await Promise.all([
            apiCall('/api/agendamentos/estatisticas'),
            apiCall('/api/laudos/estatisticas'),
            apiCall('/api/analises/estatisticas')
        ]);
        
        // Atualizar cards
        document.getElementById('total-agendamentos').textContent = agendamentosStats.total_agendamentos || 0;
        document.getElementById('total-laudos').textContent = laudosStats.total_laudos || 0;
        document.getElementById('total-analises').textContent = analisesStats.total_analises || 0;
        document.getElementById('qualidade-media').textContent = (analisesStats.qualidade_media || 0) + '%';
        
        // Gráficos
        createAgendamentosChart(agendamentosStats.por_status || {});
        createRecomendacoesChart(analisesStats.por_recomendacao || {});
        
    } catch (error) {
        console.error('Erro ao carregar dashboard:', error);
    } finally {
        hideLoading();
    }
}

function createAgendamentosChart(data) {
    const ctx = document.getElementById('agendamentosChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(data),
            datasets: [{
                data: Object.values(data),
                backgroundColor: [
                    '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

function createRecomendacoesChart(data) {
    const ctx = document.getElementById('recomendacoesChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(data),
            datasets: [{
                label: 'Quantidade',
                data: Object.values(data),
                backgroundColor: '#3B82F6'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Agendamentos
async function loadAgendamentos() {
    try {
        showLoading();
        const agendamentos = await apiCall('/api/agendamentos');
        renderAgendamentos(agendamentos);
    } catch (error) {
        console.error('Erro ao carregar agendamentos:', error);
    } finally {
        hideLoading();
    }
}

function renderAgendamentos(agendamentos) {
    const tbody = document.getElementById('lista-agendamentos');
    tbody.innerHTML = '';
    
    agendamentos.forEach(agendamento => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">${agendamento.nome_paciente}</div>
                <div class="text-sm text-gray-500">${agendamento.telefone}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${formatDateTime(agendamento.data_agendamento)}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${agendamento.tipo_exame}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusColor(agendamento.status)}">
                    ${getStatusText(agendamento.status)}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                ${getAgendamentoActions(agendamento)}
            </td>
        `;
        tbody.appendChild(row);
    });
}

function getStatusColor(status) {
    const colors = {
        'agendado': 'bg-blue-100 text-blue-800',
        'compareceu': 'bg-green-100 text-green-800',
        'pronto_exame': 'bg-yellow-100 text-yellow-800',
        'finalizado': 'bg-gray-100 text-gray-800',
        'cancelado': 'bg-red-100 text-red-800'
    };
    return colors[status] || 'bg-gray-100 text-gray-800';
}

function getStatusText(status) {
    const texts = {
        'agendado': 'Agendado',
        'compareceu': 'Compareceu',
        'pronto_exame': 'Pronto',
        'finalizado': 'Finalizado',
        'cancelado': 'Cancelado'
    };
    return texts[status] || status;
}

function getAgendamentoActions(agendamento) {
    let actions = '';
    
    if (agendamento.status === 'agendado') {
        actions += `<button onclick="checkinAgendamento(${agendamento.id})" class="text-green-600 hover:text-green-900 mr-2">Check-in</button>`;
    }
    
    if (agendamento.status === 'compareceu') {
        actions += `<button onclick="precadastroAgendamento(${agendamento.id})" class="text-blue-600 hover:text-blue-900 mr-2">Pré-cadastro</button>`;
    }
    
    actions += `<button onclick="editarAgendamento(${agendamento.id})" class="text-indigo-600 hover:text-indigo-900">Editar</button>`;
    
    return actions;
}

async function checkinAgendamento(id) {
    try {
        await apiCall(`/api/agendamentos/${id}/checkin`, { method: 'PUT' });
        alert('Check-in realizado com sucesso!');
        loadAgendamentos();
    } catch (error) {
        console.error('Erro no check-in:', error);
    }
}

async function precadastroAgendamento(id) {
    // Implementar modal de pré-cadastro
    const dados = {
        data_nascimento: prompt('Data de nascimento (YYYY-MM-DD):'),
        sexo: prompt('Sexo (M/F):'),
        indicacao: prompt('Indicação médica:'),
        rg: prompt('RG:') || '',
        cpf: prompt('CPF:') || '',
        convenio: prompt('Convênio:') || ''
    };
    
    if (dados.data_nascimento && dados.sexo && dados.indicacao) {
        try {
            await apiCall(`/api/agendamentos/${id}/precadastro`, {
                method: 'PUT',
                body: JSON.stringify(dados)
            });
            alert('Pré-cadastro realizado com sucesso!');
            loadAgendamentos();
        } catch (error) {
            console.error('Erro no pré-cadastro:', error);
        }
    }
}

// Novo Agendamento
document.getElementById('form-agendamento').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const dados = {
        nome_paciente: document.getElementById('nome-paciente').value,
        telefone: document.getElementById('telefone').value,
        email: document.getElementById('email').value,
        data_agendamento: document.getElementById('data-agendamento').value,
        tipo_exame: document.getElementById('tipo-exame').value,
        observacoes: document.getElementById('observacoes').value
    };
    
    try {
        showLoading();
        await apiCall('/api/agendamentos', {
            method: 'POST',
            body: JSON.stringify(dados)
        });
        
        alert('Agendamento criado com sucesso!');
        document.getElementById('form-agendamento').reset();
        showTab('agendamentos');
        
    } catch (error) {
        console.error('Erro ao criar agendamento:', error);
    } finally {
        hideLoading();
    }
});

// Laudos
async function loadLaudos() {
    try {
        showLoading();
        const laudos = await apiCall('/api/laudos');
        renderLaudos(laudos);
    } catch (error) {
        console.error('Erro ao carregar laudos:', error);
    } finally {
        hideLoading();
    }
}

function renderLaudos(laudos) {
    const tbody = document.getElementById('lista-laudos');
    tbody.innerHTML = '';
    
    laudos.forEach(laudo => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">${laudo.nome_paciente}</div>
                <div class="text-sm text-gray-500">${laudo.codigo_validador}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${laudo.numero_controle}
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${laudo.data_exame}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusColor(laudo.status)}">
                    ${getStatusText(laudo.status)}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                <button onclick="visualizarLaudo(${laudo.id})" class="text-indigo-600 hover:text-indigo-900">Visualizar</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Análises
async function loadAnalises() {
    try {
        showLoading();
        const analises = await apiCall('/api/analises');
        renderAnalises(analises);
    } catch (error) {
        console.error('Erro ao carregar análises:', error);
    } finally {
        hideLoading();
    }
}

function renderAnalises(analises) {
    const tbody = document.getElementById('lista-analises');
    tbody.innerHTML = '';
    
    analises.forEach(analise => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-gray-900">${analise.nome_paciente || 'N/A'}</div>
                <div class="text-sm text-gray-500">ID: ${analise.id}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                ${analise.total_paginas || 'N/A'}
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900">${analise.percentual_qualidade || 'N/A'}%</div>
                <div class="text-sm text-gray-500">${analise.qualidade_descritiva || 'N/A'}</div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getRecomendacaoColor(analise.recomendacao)}">
                    ${analise.recomendacao || 'N/A'}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${getStatusColor(analise.status)}">
                    ${getStatusText(analise.status)}
                </span>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function getRecomendacaoColor(recomendacao) {
    const colors = {
        'OK': 'bg-green-100 text-green-800',
        'REVISAR': 'bg-yellow-100 text-yellow-800',
        'REPETIR': 'bg-red-100 text-red-800',
        'PROCESSANDO': 'bg-blue-100 text-blue-800'
    };
    return colors[recomendacao] || 'bg-gray-100 text-gray-800';
}

// Filtros
async function filtrarAgendamentos() {
    const filtros = {
        status: document.getElementById('filtro-status-agendamento').value,
        data_inicio: document.getElementById('filtro-data-inicio').value,
        data_fim: document.getElementById('filtro-data-fim').value
    };
    
    // Remover filtros vazios
    Object.keys(filtros).forEach(key => {
        if (!filtros[key]) delete filtros[key];
    });
    
    try {
        showLoading();
        const params = new URLSearchParams(filtros);
        const agendamentos = await apiCall(`/api/agendamentos?${params}`);
        renderAgendamentos(agendamentos);
    } catch (error) {
        console.error('Erro ao filtrar agendamentos:', error);
    } finally {
        hideLoading();
    }
}

// Status do Sistema
async function checkSystemStatus() {
    try {
        const status = await apiCall('/api/status');
        const indicator = document.getElementById('status-indicator');
        
        if (status.status === 'online') {
            indicator.textContent = `Online (${status.database.toUpperCase()})`;
            indicator.className = 'text-xs font-medium text-green-800';
            indicator.parentElement.className = 'bg-green-100 px-3 py-1 rounded-full';
        } else {
            indicator.textContent = 'Offline';
            indicator.className = 'text-xs font-medium text-red-800';
            indicator.parentElement.className = 'bg-red-100 px-3 py-1 rounded-full';
        }
    } catch (error) {
        console.error('Erro ao verificar status:', error);
        const indicator = document.getElementById('status-indicator');
        indicator.textContent = 'Erro';
        indicator.className = 'text-xs font-medium text-red-800';
        indicator.parentElement.className = 'bg-red-100 px-3 py-1 rounded-full';
    }
}

// Utilitários de formatação
function formatDateTime(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

// Funções placeholder
function editarAgendamento(id) {
    alert(`Editar agendamento ${id} - Funcionalidade em desenvolvimento`);
}

function visualizarLaudo(id) {
    alert(`Visualizar laudo ${id} - Funcionalidade em desenvolvimento`);
}

// Verificar status periodicamente
setInterval(checkSystemStatus, 30000); // A cada 30 segundos

