<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajuda e Perguntas Frequentes | MT Foods</title>
    <!-- Inclua aqui o link para o seu ficheiro CSS principal (index.css) -->
    <!-- <link rel="stylesheet" href="index.css"> -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
      <link rel="stylesheet" href="css/index.css">
       <link rel="stylesheet" href="css/creditos.css">

    <style>
        /* Animação de entrada suave para a página */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        main {
            animation: fadeIn 0.6s ease-out;
        }

        /* Estilos personalizados para o Acordeão */
        .faq-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px; /* Ajuste para melhor responsividade em telas menores */
        }

        .faq-item {
            border: 1px solid #e5e5e5;
            margin-bottom: 12px; /* Aumentado ligeiramente o espaço */
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            transition: box-shadow 0.3s ease;
        }
        
        .faq-item:hover {
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .faq-question {
            background-color: #ffb300;
            color: #2b2b2b;
            padding: 18px 20px; /* Maior altura para facilitar o toque em mobile */
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            transition: background-color 0.3s ease;
            user-select: none; /* Previne seleção acidental em mobile */
        }

        .faq-question:hover {
            background-color: #ffc233; /* Um pouco mais claro no hover */
        }
        
        .faq-item.active .faq-question {
            background-color: #ff9900; /* Mais escuro quando ativo */
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            /* Aumentamos a duração da transição para um movimento mais "smooth" */
            transition: max-height 0.4s ease-in-out; 
            background-color: #f9f9f9;
            color: #333;
        }

        .faq-answer p {
            padding: 15px 20px;
            line-height: 1.6; /* Melhor legibilidade */
        }

        .faq-item.active .faq-answer {
            /* max-height será definido via JS para a altura exata */
        }

        .arrow {
            font-size: 1.2em;
            transition: transform 0.4s ease;
        }

        .faq-item.active .arrow {
            transform: rotate(180deg);
            color: #2b2b2b;
        }

        /* Estilo para a seção de Contacto de Emergência */
        .emergency-contact {
            background-color: #fef4e5; /* Cor clara com toque de laranja */
            border-left: 5px solid #ffb300;
            padding: 25px;
            border-radius: 8px;
            margin-top: 40px;
            text-align: center;
        }
        
        /* Media Query para Responsividade */
        @media (max-width: 640px) {
            .faq-container {
                margin: 20px auto;
                padding: 0 15px;
            }
            .faq-question {
                padding: 15px;
                font-size: 0.95rem;
            }
            .emergency-contact a {
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body style="font-family: 'Inter', sans-serif;">

<header class="topbar">
  <div class="container">

    <!-- 🟠 LOGO DA EMPRESA -->
    <div class="logo">
      <a href="index.php">
        <img class="logo" src="icones/logo.png" alt="Logo do Restaurante">
      </a>
    </div>

    <!-- 🔹 NAVEGAÇÃO DESKTOP -->
    <nav class="nav-desktop">
      <a href="index.php">Início</a>
      <a href="cardapio.php">Cardápio</a>
      <a href="acerca_de_nos.php">Acerca de Nós</a>
      <a href="ajuda.php" class="active">Ajuda</a>
       <a href="contactos.php">Contactos</a>
    </nav>

    <!-- 🔹 BOTÃO MOBILE -->
    <button class="menu-btn" id="menuBtnMobile">&#9776;</button>
  </div>

  <!-- 🔹 MENU MOBILE -->
  <nav id="mobileMenu" class="nav-mobile hidden">
    <a href="index.php">Início</a>
    <a href="cardapio.php">Cardápio</a>
    <a href="acerca_de_nos.php">Acerca de Nós</a>
    <a href="ajuda.php" class="active">Ajuda</a>
     <a href="contactos.php">Contactos</a>
  </nav>
</header>


    <!-- CONTEÚDO PRINCIPAL DA AJUDA -->
    <main class="max-w-7xl mx-auto px-4 py-8">
        <h2 class="text-4xl font-bold text-center text-[#2b2b2b] mb-4">Central de Ajuda e FAQ</h2>
        <p class="text-center text-gray-600 mb-10">Encontre respostas rápidas para as suas perguntas mais frequentes. Se não encontrar o que procura, contacte-nos no final.</p>

        <div class="faq-container">
            
            <!-- Secção 1: Pedidos e Encomendas -->
            <h3 class="text-2xl font-semibold text-[#2b2b2b] mt-6 mb-3 border-b pb-2">Pedidos e Encomendas</h3>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    Como posso acompanhar o meu pedido?
                    <span class="arrow">&#9660;</span>
                </div>
                <div class="faq-answer">
                    <p>Assim que o seu pedido for aceite e a preparação começar, enviaremos uma notificação. Pode acompanhar o estado em tempo real na secção "Meus Pedidos" no topo da página ou na aplicação (se aplicável). **Na MT Foods, a transparência é essencial**; tentamos sempre dar-lhe a localização exata do estafeta.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    Posso cancelar ou modificar um pedido depois de o enviar?
                    <span class="arrow">&#9660;</span>
                </div>
                <div class="faq-answer">
                    <p>As modificações ou cancelamentos são possíveis apenas nos primeiros **5 minutos** após a confirmação. Após esse período, a preparação é iniciada e não é possível fazer alterações. Por favor, contacte o nosso suporte imediatamente através do chat ou telefone, referindo o número da sua encomenda.</p>
                </div>
            </div>

            <!-- Secção 2: Pagamento -->
            <h3 class="text-2xl font-semibold text-[#2b2b2b] mt-6 mb-3 border-b pb-2">Pagamento</h3>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    Quais são os métodos de pagamento aceites?
                    <span class="arrow">&#9660;</span>
                </div>
                <div class="faq-answer">
                    <p>Aceitamos **Multibanco**, **Cartões de Crédito** (Visa, Mastercard), **MB Way** e pagamento em **dinheiro** na entrega (apenas para encomendas inferiores a 50€).</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    O meu pagamento falhou. O que devo fazer?
                    <span class="arrow">&#9660;</span>
                </div>
                <div class="faq-answer">
                    <p>Verifique a validade do cartão ou os fundos. Tente novamente ou escolha um método de pagamento diferente. Se a cobrança aparecer no seu extrato bancário, mas o pedido não tiver sido confirmado, contacte o seu banco primeiro e depois o nosso suporte com o comprovativo da transação para que possamos resolver a situação.</p>
                </div>
            </div>

            <!-- Secção 3: Entregas -->
            <h3 class="text-2xl font-semibold text-[#2b2b2b] mt-6 mb-3 border-b pb-2">Entregas</h3>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    Qual é o tempo médio de entrega e a área de cobertura?
                    <span class="arrow">&#9660;</span>
                </div>
                <div class="faq-answer">
                    <p>O tempo de entrega varia consoante a sua localização e o volume de pedidos, mas geralmente situa-se entre **30 a 45 minutos**. A nossa área de entrega abrange um raio de 10km a partir da nossa cozinha central. Pode verificar a elegibilidade do seu código postal no checkout.</p>
                </div>
            </div>

            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    O meu pedido chegou frio ou incorreto. O que faço?
                    <span class="arrow">&#9660;</span>
                </div>
                <div class="faq-answer">
                    <p>Lamentamos profundamente! A sua satisfação é a nossa prioridade. Por favor, **contacte-nos imediatamente** por telefone (21 123 45 67) ou chat. Resolveremos a situação enviando um novo pedido ou oferecendo um crédito total, dependendo da sua preferência.</p>
                </div>
            </div>
            
            <!-- Secção 4: Alergias e Restrições -->
            <h3 class="text-2xl font-semibold text-[#2b2b2b] mt-6 mb-3 border-b pb-2">Alergias e Restrições</h3>
            <div class="faq-item">
                <div class="faq-question" onclick="toggleFAQ(this)">
                    Como posso indicar alergias alimentares?
                    <span class="arrow">&#9660;</span>
                </div>
                <div class="faq-answer">
                    <p>Pode e deve indicar quaisquer alergias ou restrições alimentares no campo de "Notas" durante o checkout. No entanto, alertamos que, embora tomemos todas as precauções, os nossos alimentos são preparados numa cozinha que manuseia vários alergénios (como glúten, frutos secos e leite).</p>
                </div>
            </div>


            <!-- Contacto de Emergência -->
            <div class="emergency-contact">
                <h3>Ainda Precisa de Ajuda Imediata?</h3>
                <p>Se as suas dúvidas não foram resolvidas ou se tem uma emergência com o seu pedido atual, a nossa equipa de apoio está pronta para ajudar.</p>
                <a href="tel:+351211234567">Ligue Agora (21 123 45 67)</a>
            </div>

        </div>

    </main>
    
    <!-- Simulação do rodapé (Ajustado para usar o gradiente) -->
    <footer class="bg-white">
        <div class="footer max-w-7xl mx-auto text-white mt-12">
            <!-- Conteúdo do rodapé -->
            <div class="footer-content">
                <div class="footer-section">
                    <h4>MT Foods</h4>
                    <p>Comida rápida com qualidade, entregue à sua porta.</p>
                </div>
                <div class="footer-section">
                    <h4>Links Rápidos</h4>
                    <a href="cardapio.php">Cardápio</a>
                    <a href="acerca_de_nos.php">Acerca de Nós</a>
                    <a href="ajuda.php">Ajuda</a>
                </div>
                <div class="footer-section">
                    <h4>Contacto</h4>
                    <p>Email: ola@mtfoods.pt</p>
                    <p>Telefone: 21 123 45 67</p>
                </div>
            </div>
            <div class="copy">
                &copy; 2024 MT Foods. Todos os direitos reservados.
            </div>
        </div>
    </footer>

    <script>
        /**
         * Lógica para expandir e colapsar o acordeão (FAQ)
         * Usa scrollHeight para uma transição suave e dinâmica.
         * @param {HTMLElement} element O elemento da pergunta clicado
         */
        function toggleFAQ(element) {
            const item = element.parentElement;
            const answer = element.nextElementSibling;
            
            // Colapsa todos os outros itens
            document.querySelectorAll('.faq-item').forEach(otherItem => {
                if (otherItem !== item && otherItem.classList.contains('active')) {
                    otherItem.classList.remove('active');
                    otherItem.querySelector('.faq-answer').style.maxHeight = 0;
                }
            });

            // Alterna o estado do item clicado
            item.classList.toggle('active');

            if (item.classList.contains('active')) {
                // Expande o painel, definindo max-height para o scrollHeight
                // Adicionamos 2px de margem de segurança
                answer.style.maxHeight = answer.scrollHeight + 2 + "px";
            } else {
                // Colapsa o painel
                answer.style.maxHeight = 0;
            }
        }

           // ===========================================
    // LÓGICA DE NAVEGAÇÃO MOBILE (NOVO HEADER)
    // ===========================================
    const menuBtnMobile = document.getElementById("menuBtnMobile");
    const mobileMenu = document.getElementById("mobileMenu");

    if (menuBtnMobile && mobileMenu) {
        menuBtnMobile.addEventListener("click", () => {
            mobileMenu.classList.toggle("hidden");
        });
    }
    </script>
</body>
</html>