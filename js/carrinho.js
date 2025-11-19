// carrinho.js

// --- Funções Auxiliares de LocalStorage (para usuários não logados) ---

/**
 * Obtém o carrinho armazenado no LocalStorage do navegador.
 * @returns {Array} O array de itens do carrinho.
 */
function getCarrinhoLocal() {
    const carrinho = localStorage.getItem('carrinho');
    try {
        // Retorna o array parseado ou um array vazio se não existir/houver erro.
        return carrinho ? JSON.parse(carrinho) : [];
    } catch (e) {
        console.error("Erro ao ler carrinho do LocalStorage. Resetando.", e);
        return []; // Retorna vazio para evitar quebrar o sistema
    }
}

/**
 * Salva o array de itens do carrinho no LocalStorage.
 * @param {Array} carrinho - O array de itens a ser salvo.
 */
function saveCarrinhoLocal(carrinho) {
    localStorage.setItem('carrinho', JSON.stringify(carrinho));
}


// --- Função Principal de Contagem e Atualização ---

/**
 * Atualiza o contador de itens do carrinho na interface.
 * Usa o BD (usuário logado) ou o LocalStorage (usuário não logado).
 */
function atualizarContadorCarrinho() {
    const contadorElement = document.getElementById('carrinho-contador');
    if (!contadorElement) return;

    // A variável ID_USUARIO deve ser definida via PHP no seu HTML, ex: 
    // <script>const ID_USUARIO = <?php echo (int)$id_usuario_logado; ?>;</script>
    const usuarioLogado = typeof ID_USUARIO !== 'undefined' && ID_USUARIO > 0;
    
    let totalItens = 0;

    if (usuarioLogado) {
        // Opção A: Usuário Logado - Pede a contagem ao PHP
        fetch('carrinho_contador.php')
            .then(response => {
                if (!response.ok) throw new Error('Falha na resposta do servidor');
                return response.json();
            })
            .then(data => {
                totalItens = data.total_itens || 0;
                contadorElement.textContent = totalItens;
                // Exibe ou esconde o contador com base no total de itens
                contadorElement.style.display = totalItens > 0 ? 'inline-block' : 'none';
            })
            .catch(error => {
                console.error('Erro ao buscar contador do BD:', error);
                // Em caso de falha, reseta a contagem
                contadorElement.textContent = 0;
                contadorElement.style.display = 'none';
            });

    } else {
        // Opção B: Usuário Não Logado - Contagem no LocalStorage
        const carrinho = getCarrinhoLocal();
        // Soma o campo 'quantidade' de todos os itens
        totalItens = carrinho.reduce((total, item) => total + (item.quantidade || 0), 0);
        
        contadorElement.textContent = totalItens;
        contadorElement.style.display = totalItens > 0 ? 'inline-block' : 'none';
    }
}

// Inicializa o contador ao carregar a página
document.addEventListener('DOMContentLoaded', atualizarContadorCarrinho);


// --- EXEMPLO DE INTEGRAÇÃO (ADAPTE AO SEU CÓDIGO) ---

/**
 * Função de exemplo que você deve adaptar para o seu código AJAX/Fetch.
 * Esta função deve ser chamada após o sucesso de adicionar_carrinho.php ou adicionar_carrinho_personalizado.php.
 * @param {Object} response - O objeto JSON de sucesso retornado pelo PHP.
 */
function handleAdicaoSucesso(response) {
    const usuarioLogado = typeof ID_USUARIO !== 'undefined' && ID_USUARIO > 0;
    
    if (response.sucesso) {
        if (!usuarioLogado) {
            // Lógica CRÍTICA: Se não está logado, salva o item no LocalStorage
            let carrinho = getCarrinhoLocal();
            
            // Cria o objeto do item com base na resposta do PHP
            const novoItem = {
                id_produto: response.id_produto,
                quantidade: response.quantidade,
                // Usa o preço unitário ou total, dependendo do PHP de origem
                preco_unitario: response.preco_unitario || response.preco_total, 
                subtotal: response.subtotal,
                id_tipo_item_carrinho: response.id_tipo_item_carrinho,
                // Adicione quaisquer outros detalhes (como ingredientes para personalizado) aqui
            };
            
            carrinho.push(novoItem);
            saveCarrinhoLocal(carrinho);
        }
        
        // Chamada FINAL: Atualiza o contador após o sucesso da adição e salvamento local
        atualizarContadorCarrinho();
        
        // Notificação opcional para o usuário
        console.log('Item adicionado ao carrinho com sucesso!');
    } else {
        alert(response.erro || 'Erro ao adicionar item ao carrinho.');
    }
}
// --- Função de Contagem de Pedidos Ativos ---

// Variável para armazenar o ID do intervalo (usado para parar o Polling)
let pollingPedidosInterval = null;
const POLLING_INTERVAL = 15000; // 15 segundos

/**
 * Faz uma requisição recorrente ao backend para atualizar o contador de pedidos ativos.
 */
/**
 * Faz uma requisição recorrente ao backend para atualizar o contador de pedidos ativos
 * (agora atualizando Desktop E Mobile).
 */
// --- Função atualizada para iniciar o contador de pedidos ativos (desktop + mobile) ---
function iniciarContadorPedidosAtivos() {
    // Função utilitária que tenta localizar os elementos alvo
    function encontrarElementosContadores() {
        const desktopEl = document.getElementById('pedidos-ativos-contador');
        const mobileEl = document.getElementById('pedidos-ativos-contador-mobile');

        // Fallbacks: procura por atributo data-pedidos-ativos ou por classe+texto
        const fallbackMobile = document.querySelector('[data-pedidos-ativos="mobile"]') ||
                                document.querySelector('.pedidos-ativos-contador-mobile') ||
                                null;
        const fallbackDesktop = document.querySelector('[data-pedidos-ativos="desktop"]') ||
                                 document.querySelector('.pedidos-ativos-contador-desktop') ||
                                 null;

        return {
            desktop: desktopEl || fallbackDesktop,
            mobile: mobileEl || fallbackMobile
        };
    }

    // Seleciona imediatamente possíveis elementos
    let { desktop: desktopElement, mobile: mobileElement } = encontrarElementosContadores();

    // Se nenhum elemento for encontrado de início, tentamos novamente por alguns segundos (caso o sidebar seja injetado depois)
   // Novo método: Observa quando novos elementos aparecem no DOM
function esperarElementos(resolve) {
    const found = encontrarElementosContadores();
    if (found.desktop || found.mobile) {
        desktopElement = found.desktop;
        mobileElement = found.mobile;
        resolve();
        return;
    }

    // Observador que detecta quando o menu mobile é criado
    const observer = new MutationObserver(() => {
        const retry = encontrarElementosContadores();
        if (retry.desktop || retry.mobile) {
            desktopElement = retry.desktop;
            mobileElement = retry.mobile;
            observer.disconnect();
            resolve();
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
}


    // Espera até termos ao menos um dos elementos (ou até o timeout)
new Promise(esperarElementos).then(() => {

        // Se após tentativas não tiver nenhum elemento ou usuário não logado, aborta
        const usuarioLogado = (typeof ID_USUARIO !== 'undefined' && ID_USUARIO > 0);
        if (!usuarioLogado || (!desktopElement && !mobileElement)) {
            // Esconde possíveis elementos que existam (segurança)
            if (desktopElement) desktopElement.style.display = 'none';
            if (mobileElement) mobileElement.style.display = 'none';
            return;
        }

        // Array de elementos que vamos atualizar
        const counterElements = [];
        if (desktopElement) counterElements.push(desktopElement);
        if (mobileElement) counterElements.push(mobileElement);

        // Função que atualiza os elementos com a resposta do servidor
        const fetchPedidosAtivos = () => {
            fetch('pedidos_ativos_contador.php')
                .then(response => {
                    if (response.status === 401) {
                        // Não autorizado -> limpar polling e esconder contadores
                        if (pollingPedidosInterval) {
                            clearInterval(pollingPedidosInterval);
                            pollingPedidosInterval = null;
                        }
                        counterElements.forEach(el => el.style.display = 'none');
                        return Promise.reject('Usuário deslogado.');
                    }
                    if (!response.ok) throw new Error('Falha na resposta do servidor.');
                    return response.json();
                })
                .then(data => {
                    const totalAtivos = data.total_pedidos_ativos || 0;
                    counterElements.forEach(el => {
                        el.textContent = totalAtivos;
                        el.style.display = totalAtivos > 0 ? 'inline-block' : 'none';
                    });
                })
                .catch(error => {
                    console.error('Erro ao buscar contador de pedidos ativos:', error);
                    // Em caso de erro, esconde todos os contadores encontrados
                    counterElements.forEach(el => el.style.display = 'none');
                });
        };

        // Executa imediatamente e configura polling
        fetchPedidosAtivos();
        if (pollingPedidosInterval === null) {
            pollingPedidosInterval = setInterval(fetchPedidosAtivos, POLLING_INTERVAL);
        }
    });
}

// Inicializa o contador de pedidos ativos ao carregar a página, APENAS SE ESTIVER LOGADO.
document.addEventListener('DOMContentLoaded', iniciarContadorPedidosAtivos);
// --- Funções de Notificação de Pedido Finalizado ---

let pollingFinalizadosInterval = null;
const POLLING_INTERVAL_FINALIZADOS = 10000; // 10 segundos

/**
 * Fecha o popup de notificação.
 */
function fecharPopupFinalizado() {
    const popup = document.getElementById('popup-pedido-finalizado');
    if (popup) {
        popup.style.display = 'none';
    }
}

/**
 * Faz uma requisição recorrente para verificar pedidos FINALIZADOS e NÃO VISTOS.
 */
function iniciarNotificacaoPedidosFinalizados() {
    const contadorElement = document.getElementById('finalizados-contador');
    const popupElement = document.getElementById('popup-pedido-finalizado');
    
    if (!contadorElement || !(typeof ID_USUARIO !== 'undefined' && ID_USUARIO > 0)) {
        return; 
    }

    const fetchPedidosFinalizados = () => {
        fetch('pedidos_finalizados_contador.php')
            .then(response => {
                if (!response.ok) throw new Error('Falha na resposta do servidor');
                return response.json();
            })
            .then(data => {
                const totalNaoVistos = data.total_finalizados_nao_vistos || 0;

                // 1. Atualiza Contador
                contadorElement.textContent = totalNaoVistos;
                contadorElement.style.display = totalNaoVistos > 0 ? 'inline-block' : 'none';

                // 2. Exibe ou esconde o Popup
                if (totalNaoVistos > 0) {
                    // Se houver pedidos não vistos, e o popup não estiver visível, exibe-o
                    if (popupElement && popupElement.style.display !== 'block') {
                        popupElement.style.display = 'block'; 
                    }
                } else {
                    // Se não houver pedidos não vistos, garante que o popup esteja fechado
                    fecharPopupFinalizado();
                }
            })
            .catch(error => {
                console.error('Erro ao buscar notificação de finalizados:', error);
                fecharPopupFinalizado();
                contadorElement.style.display = 'none';
            });
    };

    // 1. Executa a função imediatamente ao carregar
    fetchPedidosFinalizados();

    // 2. Configura a execução recorrente (Polling)
    if (pollingFinalizadosInterval === null) {
        pollingFinalizadosInterval = setInterval(fetchPedidosFinalizados, POLLING_INTERVAL_FINALIZADOS);
    }
}

// Inicia o contador ao carregar a página
document.addEventListener('DOMContentLoaded', iniciarNotificacaoPedidosFinalizados);