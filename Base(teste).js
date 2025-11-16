<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Balanceamentos - DASS (Vue.js)</title>
    <!-- Carrega o Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Carrega as bibliotecas JS (Vue.js é o principal) -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = `https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.11.338/pdf.worker.min.js`;
    </script>
    <script src="https://unpkg.com/lucide@0.292.0/dist/umd/lucide.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Fontes -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Roboto+Mono&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f2f5; }
        .font-mono { font-family: 'Roboto Mono', monospace; }
        .nav-link { transition: all 0.2s ease-in-out; border-left: 3px solid transparent; }
        .nav-link.active { background-color: #374151; border-left-color: #ef4444; }
        .loader { border: 4px solid #e5e7eb; border-radius: 50%; border-top: 4px solid #ef4444; width: 40px; height: 40px; animation: spin 1.5s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        tbody tr:nth-child(even) { background-color: #f8fafc; }
        .modal-backdrop { background-color: rgba(0,0,0,0.5); transition: opacity 0.3s ease-in-out; }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }
    </style>
</head>
<body class="text-slate-800">

    <!-- O Vue.js irá controlar todo o conteúdo dentro deste div -->
    <div id="app">
        <!-- Loader inicial enquanto o Vue verifica a autenticação -->
        <div v-if="isLoading" class="flex items-center justify-center min-h-screen">
            <div class="loader"></div>
        </div>

        <!-- Renderiza a Página de Login -->
        <login-page 
            v-if="currentView === 'login'" 
            @login-success="handleLoginSuccess" 
            @public-access="currentView = 'public'">
        </login-page>

        <!-- Renderiza a Visão Pública -->
        <public-view 
            v-else-if="currentView === 'public'" 
            @go-to-login="currentView = 'login'">
        </public-view>

        <!-- Renderiza a Aplicação Principal (Shell) -->
        <app-shell 
            v-else-if="currentView === 'app'" 
            :user="currentUser" 
            :current-page="currentPage"
            @navigate="handleNavigate" 
            @logout="handleLogout"
            :can="can">
        </app-shell>
    </div>

    <script type="module">
        // Importa funções do Vue.js
        const { createApp, ref, reactive, computed, onMounted, watch, nextTick } = Vue;

        // --- Constantes da API (apontam para seu backend PHP) ---
        const API_URL = 'api.php';
        const UPLOAD_URL = 'upload.php';

        // --- Função Auxiliar de API (copiada do seu JS original) ---
        async function apiRequest(url, options = {}) {
            try {
                const response = await fetch(url, options);
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error("Erro de API (não-JSON):", text);
                    throw new Error(`O servidor respondeu com um erro inesperado. Verifique os logs do PHP/Apache. Detalhe: ${text.substring(0, 150)}...`);
                }
                if (!response.ok) {
                    throw new Error(data.message || `Erro de rede: ${response.statusText}`);
                }
                if (data.status === 'error' && data.message) {
                    console.error('Erro da API:', data.message);
                }
                return data;
            } catch (error) {
                console.error('Erro na requisição:', error);
                const msg = 'Falha de comunicação com o servidor. Verifique se o XAMPP (Apache, MySQL) está rodando.';
                throw error;
            }
        }

        // =================================================================
        // COMPONENTE: PÁGINA DE LOGIN (app-login-page)
        // =S===============================================================
        const AppLoginPage = {
            template: `
                <div class="flex items-center justify-center min-h-screen bg-gray-100 p-4">
                    <div class="w-full max-w-md p-8 space-y-6 bg-white rounded-2xl shadow-lg">
                        <div class="text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto" width="200" height="50" viewBox="0 0 200 50">
                                <text x="0" y="30" font-family="Arial, sans-serif" font-size="32" font-weight="bold" fill="#ef4444">Dass</text>
                                <text x="0" y="45" font-family="Arial, sans-serif" font-size="7" font-weight="normal" fill="#94a3b8">IMPLEMENTING SPORTSWEAR BRANDS</text>
                            </svg>
                            <h2 class="mt-6 text-2xl font-bold text-gray-900">Acesse sua conta</h2>
                        </div>
                        <form @submit.prevent="submitLogin" class="space-y-6">
                            <div>
                                <label for="email-input" class="text-sm font-bold text-gray-600 block">Usuário</label>
                                <input type="text" id="email-input" v-model="username" class="w-full p-2 mt-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500" required :disabled="isLoading">
                            </div>
                            <div>
                                <label for="password-input" class="text-sm font-bold text-gray-600 block">Senha</label>
                                <input type="password" id="password-input" v-model="password" class="w-full p-2 mt-1 border border-gray-300 rounded-md focus:ring-2 focus:ring-red-500" required :disabled="isLoading">
                            </div>
                            <button type="submit" class="w-full py-2.5 px-4 text-sm font-semibold rounded-lg text-white bg-red-600 hover:bg-red-700 disabled:bg-gray-400" :disabled="isLoading">
                                <span v-if="!isLoading">Entrar</span>
                                <span v-else class="flex items-center justify-center">
                                    <div class="loader !w-5 !h-5 !border-2 !border-t-white"></div>
                                </span>
                            </button>
                            <p class="text-sm text-red-600 text-center min-h-[20px]">{{ error }}</p>
                        </form>
                        <div class="text-center">
                            <button @click="$emit('publicAccess')" class="text-sm font-medium text-red-600 hover:text-red-800">Acessar Consulta Pública</button>
                        </div>
                    </div>
                </div>
            `,
            emits: ['loginSuccess', 'publicAccess'],
            setup(_, { emit }) {
                const username = ref('');
                const password = ref('');
                const error = ref('');
                const isLoading = ref(false);

                const submitLogin = async () => {
                    isLoading.value = true;
                    error.value = '';
                    try {
                        const response = await apiRequest(API_URL, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'login', username: username.value, password: password.value })
                        });

                        if (response.status === 'success') {
                            emit('loginSuccess', response.user);
                        } else {
                            error.value = response.message || "Usuário ou senha inválidos.";
                        }
                    } catch (err) {
                        error.value = err.message || 'Falha de comunicação com o servidor.';
                    } finally {
                        isLoading.value = false;
                    }
                };

                return { username, password, error, isLoading, submitLogin };
            }
        };

        // =================================================================
        // COMPONENTE: VISÃO PÚBLICA (app-public-view)
        // =================================================================
        const AppPublicView = {
            template: `
                <div class="bg-gray-100 min-h-screen">
                    <header class="bg-white shadow-md p-4 flex flex-col sm:flex-row justify-between items-center sticky top-0 z-10 gap-4 sm:gap-0">
                        <div class="flex items-center gap-3">
                            <svg xmlns="http://www.w3.org/2000/svg" width="150" height="40" viewBox="0 0 150 40">
                                <text x="0" y="25" font-family="Arial, sans-serif" font-size="24" font-weight="bold" fill="#ef4444">Dass</text>
                                <text x="0" y="35" font-family="Arial, sans-serif" font-size="6" font-weight="normal" fill="#94a3b8">IMPLEMENTING SPORTSWEAR BRANDS</text>
                            </svg>
                            <span class="text-lg font-semibold text-slate-700">Modo de Consulta</span>
                        </div>
                        <button @click="$emit('goToLogin')" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold w-full sm:w-auto">Login de Administrador</button>
                    </header>
                    <div class="p-4 md:p-8">
                        <!-- O conteúdo público (lista de processos, etc.) será migrado para cá -->
                        <div class="p-6 bg-white rounded-xl shadow-sm border">
                            <h2 class="text-xl font-bold">Consulta Pública</h2>
                            <p class="mt-4 text-slate-600">A funcionalidade de consulta pública será migrada para esta área.</p>
                        </div>
                    </div>
                </div>
            `,
            emits: ['goToLogin']
        };

        // =================================================================
        // COMPONENTE: ESTRUTURA DA APLICAÇÃO (app-shell)
        // =================================================================
        const AppShell = {
            props: ['user', 'currentPage', 'can'],
            emits: ['navigate', 'logout'],
            template: `
                <div class="flex h-screen overflow-hidden">
                    <!-- Sidebar -->
                    <aside :class="['w-64 bg-gray-900 text-white flex-col p-4 flex absolute inset-y-0 left-0 transform md:relative md:translate-x-0 transition-transform duration-300 ease-in-out z-30', { '-translate-x-full': !isSidebarOpen }]">
                        <div class="flex items-center justify-center gap-3 text-center py-4 border-b border-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" width="200" height="50" viewBox="0 0 200 50">
                                <text x="0" y="30" font-family="Arial, sans-serif" font-size="32" font-weight="bold" fill="#ef4444">Dass</text>
                                <text x="0" y="45" font-family="Arial, sans-serif" font-size="7" font-weight="normal" fill="#cbd5e1">IMPLEMENTING SPORTSWEAR BRANDS</text>
                            </svg>
                        </div>
                        <nav class="mt-8 flex-grow">
                            <button @click="navigate('home')" :class="['nav-link w-full text-left flex items-center gap-3 font-semibold py-3 px-4 rounded-lg', { 'active': currentPage === 'home' }]">
                                <i data-lucide="layout-dashboard" class="w-5 h-5"></i>Painel
                            </button>
                            <button @click="navigate('analytics')" :class="['nav-link w-full text-left flex items-center gap-3 font-semibold py-3 px-4 rounded-lg', { 'active': currentPage === 'analytics' }]" v-if="can(['admin', 'gerente'])">
                                <i data-lucide="pie-chart" class="w-5 h-5"></i>Análises
                            </button>
                            <button @click="navigate('processes')" :class="['nav-link w-full text-left flex items-center gap-3 font-semibold py-3 px-4 rounded-lg', { 'active': currentPage === 'processes' }]" v-if="can(['admin', 'gerente', 'analista', 'lideranca'])">
                                <i data-lucide="folder-kanban" class="w-5 h-5"></i>Balanceamentos
                            </button>
                            <button @click="navigate('admin')" :class="['nav-link w-full text-left flex items-center gap-3 font-semibold py-3 px-4 rounded-lg', { 'active': currentPage === 'admin' }]" v-if="can(['admin'])">
                                <i data-lucide="shield-check" class="w-5 h-5"></i>Administrador
                            </button>
                        </nav>
                        <div class="mt-auto border-t border-gray-700 pt-4">
                            <div>
                                <p class="text-sm font-medium text-slate-200 truncate">{{ user.name }}</p>
                                <p class="text-xs text-slate-400 capitalize">{{ user.role }}</p>
                            </div>
                            <button @click="$emit('logout')" class="w-full mt-4 text-left flex items-center gap-3 text-slate-300 hover:text-white font-semibold py-2 px-4 rounded-lg hover:bg-red-800/50">
                                <i data-lucide="log-out" class="w-5 h-5"></i>Sair
                            </button>
                        </div>
                    </aside>

                    <!-- Conteúdo Principal -->
                    <div class="flex-1 flex flex-col overflow-hidden">
                        <header class="bg-white shadow-sm p-2 md:hidden flex items-center">
                             <button @click="isSidebarOpen = !isSidebarOpen" class="p-2 text-gray-600 rounded-md hover:bg-gray-100">
                                  <i data-lucide="menu" class="w-6 h-6"></i>
                             </button>
                        </header>
                        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                            <!-- Roteamento de Página Interno -->
                            <page-home v-if="currentPage === 'home'"></page-home>
                            <page-analytics v-else-if="currentPage === 'analytics'"></page-analytics>
                            <page-processes v-else-if="currentPage === 'processes'"></page-processes>
                            <page-admin v-else-if="currentPage === 'admin'"></page-admin>
                        </main>
                    </div>
                </div>
            `,
            setup(_, { emit }) {
                const isSidebarOpen = ref(false);

                const navigate = (page) => {
                    emit('navigate', page);
                    isSidebarOpen.value = false; // Fecha o menu mobile ao navegar
                };

                return { isSidebarOpen, navigate };
            }
        };

        // =================================================================
        // COMPONENTES: PÁGINAS (Placeholders)
        // =================================================================
        const PageHome = { template: `<div><h2 class="text-3xl font-bold mb-6">Painel Geral</h2><div class="p-6 bg-white rounded-xl shadow-sm border"><p>A página inicial com o formulário de upload e consulta de operador será migrada para cá.</p></div></div>` };
        const PageAnalytics = { template: `<div><h2 class="text-3xl font-bold mb-6">Análises</h2><div class="p-6 bg-white rounded-xl shadow-sm border"><p>Os gráficos e estatísticas serão migrados para cá.</p></div></div>` };
        const PageProcesses = { template: `<div><h2 class="text-3xl font-bold mb-6">Balanceamentos</h2><div class="p-6 bg-white rounded-xl shadow-sm border"><p>A grade de balanceamentos salvos será migrada para cá.</p></div></div>` };
        const PageAdmin = { template: `<div><h2 class="text-3xl font-bold mb-6">Administração</h2><div class="p-6 bg-white rounded-xl shadow-sm border"><p>A gestão de usuários (criar, editar, excluir) será migrada para cá.</p></div></div>` };


        // =================================================================
        // APLICAÇÃO VUE PRINCIPAL
        // =================================================================
        createApp({
            setup() {
                // --- Estado Principal ---
                const isLoading = ref(true);
                const currentUser = ref(null);
                const currentView = ref('login'); // 'login', 'app', 'public'
                const currentPage = ref('home'); // 'home', 'analytics', 'processes', 'admin'

                // --- Permissões ---
                const can = (allowedRoles) => {
                    return computed(() => {
                        const role = currentUser.value?.role || 'publico';
                        return allowedRoles.includes(role);
                    });
                };

                // --- Autenticação ---
                const checkAuthState = () => {
                    isLoading.value = true;
                    const userJSON = sessionStorage.getItem('currentUser');
                    if (userJSON) {
                        currentUser.value = JSON.parse(userJSON);
                        currentView.value = 'app';
                        currentPage.value = 'home';
                    } else {
                        currentUser.value = null;
                        currentView.value = 'login';
                    }
                    isLoading.value = false;
                };

                const handleLoginSuccess = (user) => {
                    currentUser.value = user;
                    currentView.value = 'app';
                    currentPage.value = 'home';
                    sessionStorage.setItem('currentUser', JSON.stringify(user));
                };

                const handleLogout = () => {
                    sessionStorage.removeItem('currentUser');
                    currentUser.value = null;
                    currentView.value = 'login';
                };
                
                const handleNavigate = (page) => {
                    currentPage.value = page;
                };

                // --- Ciclo de Vida ---
                onMounted(() => {
                    checkAuthState();
                });

                // --- Observadores ---
                // Recarrega os ícones do Lucide sempre que a view ou a página mudar
                watch([currentView, currentPage], async () => {
                    await nextTick();
                    lucide.createIcons();
                });

                return {
                    isLoading,
                    currentUser,
                    currentView,
                    currentPage,
                    can,
                    handleLoginSuccess,
                    handleLogout,
                    handleNavigate
                };
            }
        })
        .component('login-page', AppLoginPage)
        .component('public-view', AppPublicView)
        .component('app-shell', AppShell)
        .component('page-home', PageHome)
        .component('page-analytics', PageAnalytics)
        .component('page-processes', PageProcesses)
        .component('page-admin', PageAdmin)
        .mount('#app');

    </script>
</body>
</html>
