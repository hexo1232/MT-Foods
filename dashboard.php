<?php 
include "conexao.php";
require_once "require_login.php";
include "usuario_info.php";

// ===============================================
// NOVO CÓDIGO PARA GESTÃO DE ALERTA DE INGREDIENTES NA DASHBOARD
// ===============================================

// REMOVEMOS TODA A LÓGICA DE BUSCA NO BANCO DE DADOS E DEPENDÊNCIA DE SESSÃO PHP
// para o alerta de ingredientes. O AJAX fará esse trabalho em tempo real.
// O código abaixo foi deixado vazio:

// ===============================================
// FIM NOVO CÓDIGO PARA GESTÃO DE ALERTA DE INGREDIENTES
// ===============================================


header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" 
       content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

  <title>Dashboard</title>
  <script src="logout_auto.js"></script>
  <link rel="stylesheet" href="css/admin.css">
  <script src="js/alerta_estoque_inteligente.js"></script>
  <script src="js/darkmode2.js"></script>
    <script src="js/sidebar.js"></script>
<script src="js/dropdown2.js"></script>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* NOVO CÓDIGO CSS PARA O ALERTA DE ESTOQUE */
/* Estilo para o Toast/Popup (igual ao de ver_ingredientes.php) */
.toast-ingrediente {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1001; /* Maior z-index para garantir que fique acima do toast de pedido */
    padding: 15px 25px;
    border-radius: 8px;
    color: white;
    font-size: 14px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    opacity: 0;
    transition: opacity 0.5s, transform 0.5s;
    transform: translateX(100%);
}
.toast-ingrediente.show {
    opacity: 1;
    transform: translateX(0);
}
.toast-ingrediente.laranja {
    background-color: #ff9900;
}
.toast-ingrediente.vermelho {
    background-color: #cc0000;
}
.toast-ingrediente ul {
    list-style-type: none;
    padding-left: 0;
    margin-top: 5px;
}
.toast-ingrediente li {
    margin-top: 3px;
    font-weight: bold;
}
.toast-ingrediente .close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    float: right;
    margin-left: 10px;
}

/* Badge para a Sidebar */
.badge-alerta {
    background-color: #cc0000;
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: bold;
    margin-left: 8px;
    vertical-align: middle;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(204, 0, 0, 0.7); }
    70% { box-shadow: 0 0 0 6px rgba(204, 0, 0, 0); }
    100% { box-shadow: 0 0 0 0 rgba(204, 0, 0, 0); }
}
/* FIM NOVO CÓDIGO CSS */
</style>
</head>

<body>
  <div class="dashboard-container">
    <button class="menu-btn">☰</button>

<div class="sidebar-overlay"></div>

    <sidebar class="sidebar">

      <br><br>
      <a href="cardapio.php?modo=admin_pedido" class="link-acao-principal">
        Adicionar Pedido Manual</a>
          <a href="historico_compras_admin.php">Histórico de Compras
            <span id="finalizados-contador" class="badge bg-success rounded-pill" 
    style=" background: red; color: white; padding: 4px; border-radius: 50%; font-size: 12px;
    font-weight: bold; display: none; line-height: 1; text-align: center; min-width: 16px; height: 16px;">0</span></a>

      <a href="ver_usuarios.php">Ver Usuários</a>
      <a href="ver_pratos.php">Ver Refeições</a>
 <a href="ver_ingredientes.php" id="link-ver-ingredientes">
    Ver Ingredientes <span id="badge-estoque" style="display:none;" class="badge-alerta">0</span>
</a>
    
      <a href="cozinha_admin.php" id="link-cozinha">
        Monitorar Pedidos  
        <span id="contador-notificacao">  0 </span>
      </a>
      <a href="fidelidade.php">Ver Programa de Fidelidade</a>
      <!-- <a href="ver_logs.php">Histórico de Logs</a> -->
      <a href="gerenciar_banner.php">Gerenciar Banners</a>
  

<div class="sidebar-user-wrapper">

    <div class="sidebar-user" id="usuarioDropdown">

        <div class="usuario-avatar" style="background-color: <?= $corAvatar ?>;">
            <?= $iniciais ?>
        </div>

        <div class="usuario-dados">
            <div class="usuario-nome"><?= $nome ?></div>
            <div class="usuario-apelido"><?= $apelido ?></div>
        </div>

        <div class="usuario-menu" id="menuPerfil">
            <a href='editarusuario.php?id_usuario=<?= $usuario['id_usuario'] ?>'>
            <img class="icone" src="icones/user1.png" alt="Editar" title="Editar">  
            Editar Dados Pessoais</a>
            <a href="alterar_senha2.php">
            
            <img class="icone" src="icones/cadeado1.png" alt="Alterar" title="Alterar"> 
            Alterar Senha</a>
            <a href="logout.php">
            <img class="iconelogout" src="icones/logout1.png" alt="Logout" title="Sair">  
            Sair</a>
        </div>

    </div>

    <img class="dark-toggle" id="darkToggle"
           src="icones/lua.png"
           alt="Modo Escuro"
           title="Alternar modo escuro">
</div>

    </sidebar>

    <div class="conteudo">
      <div class="card">
        <h3>Bem-vindo à área administrativa</h3>
        <p>Use o menu à esquerda para navegar nas funcionalidades.</p>
      </div>

      <div class="card-admin">
        <div class="filtros">
          <label for="periodo">Período:</label>
          <select id="periodo">
            <option value="diario" selected>Diário</option>
            <option value="semanal" >Semanal</option>
            <option value="mensal" >Mensal</option>
            <option value="anual">Anual</option>
          </select>
        </div>

    <div class="chart-container">
  <div class="chart-box">
    <canvas id="graficoPizza"></canvas>
  </div>
  <div class="chart-box">
    <canvas id="graficoBarras"></canvas>
  </div>
</div>
      </div>
    </div>
  </div>

<script>

// Variável para controlar o "Soneca" do alerta
    const TEMPO_SONECA_MS = 1000 * 60 * 30; // 30 minutos sem incomodar após fechar

    function atualizarAlertaIngredientes() {
        fetch('ajax_alerta_estoque.php')
            .then(response => response.json())
            .then(data => {
                const alertas = data.alertas;
                const tipoAlerta = data.tipo;
                const badge = document.getElementById('badge-estoque');

                // 1. ATUALIZAR O BADGE NA SIDEBAR (Sempre visível se houver problema)
                if (alertas.length > 0) {
                    badge.textContent = alertas.length;
                    badge.style.display = 'inline-block';
                    // Opcional: Mudar cor baseada na gravidade
                    badge.style.backgroundColor = (tipoAlerta === 'vermelho') ? '#cc0000' : '#ff9900';
                } else {
                    badge.style.display = 'none';
                }

                // 2. LÓGICA DO POP-UP (TOAST)
                // Verifica se devemos mostrar o pop-up ou se o usuário já fechou recentemente
                const ultimaVisualizacao = localStorage.getItem('alerta_estoque_fechado_em');
                const agora = new Date().getTime();
                const deveMostrar = !ultimaVisualizacao || (agora - ultimaVisualizacao > TEMPO_SONECA_MS);

                // Se tiver alertas E (não tiver popup aberto) E (o tempo de soneca expirou)
                if (alertas.length > 0 && !document.querySelector('.toast-ingrediente') && deveMostrar) {
                    
                    let listaItens = alertas.map(item => `<li>${item.nome} (${item.estoque} unid.)</li>`).join('');
                    
                    const popup = document.createElement('div');
                    popup.className = `toast-ingrediente ${tipoAlerta}`; 
                    
                    popup.innerHTML = `
                        <button class="close-btn" aria-label="Fechar Alerta">&times;</button>
                        <h4>⚠️ ATENÇÃO AO ESTOQUE</h4>
                        <p>Existem <strong>${alertas.length}</strong> ingredientes com estoque baixo.</p>
                        <ul style="max-height: 100px; overflow-y: auto;">${listaItens}</ul>
                        <a href="ver_ingredientes.php" style="color: white; text-decoration: underline; font-weight:bold; display: block; margin-top: 10px;">Resolver Agora</a>
                    `;
                    
                    document.body.appendChild(popup);
                    setTimeout(() => popup.classList.add('show'), 100);

                    // Ao fechar, salvamos o Timestamp no LocalStorage
                    popup.querySelector('.close-btn').addEventListener('click', function() {
                        localStorage.setItem('alerta_estoque_fechado_em', new Date().getTime());
                        popup.classList.remove('show');
                        setTimeout(() => popup.remove(), 500); 
                    });
                }
            })
            .catch(error => console.error("Erro ao atualizar alerta de estoque:", error));
    }

document.addEventListener('DOMContentLoaded', function() {
    // A lógica anterior de exibição baseada na sessão PHP foi removida daqui.
    
    // Inicia a verificação de alerta de ingredientes a cada 10 segundos
    setInterval(atualizarAlertaIngredientes, 10000); // Ajuste o tempo conforme necessário (ex: 10000ms = 10 segundos)
    
    // Executa imediatamente ao carregar
    atualizarAlertaIngredientes(); 
});
</script>
<script>
  document.addEventListener("DOMContentLoaded", () => {
    const graficoPizza = document.getElementById('graficoPizza');
    const graficoBarras = document.getElementById('graficoBarras');
    const seletorPeriodo = document.getElementById('periodo');

    let chartPizza, chartBarras;

    async function carregarDados(periodo = 'diario') {
      const response = await fetch(`estatisticas_pedidos.php?periodo=${periodo}`);
      const data = await response.json();

      const labels = data.map(item => item.label);
      const valores = data.map(item => item.total);

      if (chartPizza) chartPizza.destroy();
      if (chartBarras) chartBarras.destroy();

      chartPizza = new Chart(graficoPizza, {
        type: 'pie',
        data: {
          labels: labels,
          datasets: [{
            data: valores,
            backgroundColor: ['#f1bf1b','#cc9d01','#222','#ff5733','#28a745','#007bff'],
            borderWidth: 1
          }]
        },
        options: {
          plugins: {
            title: { display: true, text: 'Distribuição de Pedidos', font: { size: 18 } },
            legend: { position: 'bottom' }
          }
        }
      });

      chartBarras = new Chart(graficoBarras, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Total de Pedidos',
            data: valores,
            backgroundColor: '#f1bf1b'
          }]
        },
        options: {
          scales: { y: { beginAtZero: true } },
          plugins: {
            title: { display: true, text: 'Distribuição de Pedidos', font: { size: 18 } },
            legend: { display: false }
          }
        }
      });
    }

    seletorPeriodo.addEventListener('change', () => carregarDados(seletorPeriodo.value));
    carregarDados(); // inicial
  });
  </script>

  <script>
document.addEventListener("DOMContentLoaded", () => {
    const contadorLink = document.getElementById("contador-notificacao");
    const contadorCard = document.getElementById("contador-card");
    let popUpAtivo = false;

    // Função para exibir um pop-up com base nos dados do servidor
    function exibirPopUp(notificacao) {
        // Se já houver um pop-up ativo, não exibe outro
        if (popUpAtivo) return;

        popUpAtivo = true;
        
        let itensHtml = '';
        notificacao.itens.forEach(item => {
            itensHtml += `
                <div class="pedido-item">
                    <p><strong>- Produto:</strong> ${item.quantidade}x ${item.nome_produto}</p>
            `;
          if (item.incrementados.length > 0) {
    itensHtml += `<ul class="ingredientes-lista incrementados"><strong>Incrementos:</strong>`;
    item.incrementados.forEach(ing => {
        let qtd = ing.quantidade && ing.quantidade > 1 ? ` (${ing.quantidade}x)` : "";
        itensHtml += `<li>+ ${ing.ingrediente_nome}${qtd}</li>`;
    });
    itensHtml += `</ul>`;
}
if (item.reduzidos.length > 0) {
    itensHtml += `<ul class="ingredientes-lista reduzidos"><strong>Removidos:</strong>`;
    item.reduzidos.forEach(ing => {
        let qtd = ing.quantidade && ing.quantidade > 1 ? ` (${ing.quantidade}x)` : "";
        itensHtml += `<li>- ${ing.ingrediente_nome}${qtd}</li>`;
    });
    itensHtml += `</ul>`;
}

            itensHtml += `</div>`;
        });

        // Procura e remove pop-ups existentes para evitar duplicação em caso de novo pedido
        document.querySelectorAll('.toast-dashboard').forEach(t => t.remove());

        const toast = document.createElement("div");
        toast.className = "toast-dashboard";
        // Adiciona um ID para que ele possa ser removido se o pedido for processado
        toast.setAttribute('data-id-pedido', notificacao.id_pedido); 
        
        toast.innerHTML = `
 <div class="toast-content">
            <div class="toast-content">
                <h3 style='color:red;'>NOVO PEDIDO PENDENTE #${notificacao.id_pedido}</h3>
                <p><strong>Cliente:</strong> ${notificacao.nome_cliente}</p>
                <p><strong>Total:</strong> ${notificacao.total} MZN</p>
                <p><strong>Entrega:</strong> ${notificacao.nome_tipo_entrega}</p>
                <p><strong>Pagamento:</strong> ${notificacao.tipo_pagamento}</p>
                <p><strong>Horário:</strong> ${notificacao.horario}</p>
                <hr>
                <h4>Itens do Pedido:</h4>
                ${itensHtml}
                <div class="toast-actions">
                    <button class="btn-monitorar" onclick="window.location.href='cozinha_admin.php'">Ir para Monitoramento</button>
                    <button class="btn-fechar-pop-up">Fechar Alerta</button> 
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add("show"), 100);

        // Novo: Adiciona funcionalidade para fechar manualmente
        toast.querySelector('.btn-fechar-pop-up').addEventListener('click', () => {
            toast.classList.remove("show");
            setTimeout(() => {
                toast.remove();
                popUpAtivo = false;
            }, 500);
        });
        
        // REMOVIDA A CHAMADA AUTOMÁTICA DE setTimeout para fechar o pop-up
    }
    
    let pedidosPendentes = [];

    // Função principal para buscar e processar notificações
    async function atualizarNotificacoes() {
        try {
            const response = await fetch("ajax_notificacoes.php");
            const data = await response.json();

            // Atualiza o contador de pedidos pendentes
            if (data.contagem > 0) {
                contadorLink.textContent = data.contagem;
                contadorLink.style.display = "inline-block";
            } else {
                contadorLink.style.display = "none";
                // Se a contagem for 0, remove o pop-up permanentemente (se existir)
                document.querySelectorAll('.toast-dashboard').forEach(t => t.remove());
                popUpAtivo = false;
            }

            // Verifica se há um novo pedido pendente para exibir o pop-up
            const novosPedidos = data.notificacoes.filter(
                novoPedido => !pedidosPendentes.some(p => p.id_pedido === novoPedido.id_pedido)
            );

            // Se houver novos pedidos E não houver pop-up na tela, exibe o pop-up.
            if (novosPedidos.length > 0 && !popUpAtivo) {
                exibirPopUp(novosPedidos[0]);
            }
            
            pedidosPendentes = data.notificacoes;

        } catch (err) {
            console.error("Erro ao atualizar notificações:", err);
        }
    }

    // Atualiza a cada 5 segundos
    setInterval(atualizarNotificacoes, 5000);
    // Atualiza imediatamente ao carregar
    atualizarNotificacoes();
});
  </script>

</body>
</html>