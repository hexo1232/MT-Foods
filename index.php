<?php
session_start();
include "conexao.php";
include "verifica_login_opcional.php";

$ids_categorias_destaque = [3, 5, 10]; // Exemplo: [ID_HAMBURGUERES, ID_BEBIDAS, ID_ACOMPANHAMENTOS]

// 2. BUSCAR AS CATEGORIAS E UM PRODUTO DE CADA
$destaques = [];
if (!empty($ids_categorias_destaque) && $conexao) {
    
    // Converte o array de IDs para uma string para usar na query SQL (como placeholder)
    $ids_placeholders = implode(',', array_fill(0, count($ids_categorias_destaque), '?'));

    // ... (sua query SQL aqui) ...
    $sql_destaques = "
        SELECT 
            c.id_categoria, 
            c.nome_categoria,
            pi.caminho_imagem
        FROM categoria c
        JOIN produto_categoria pc ON c.id_categoria = pc.id_categoria
        JOIN produto p ON pc.id_produto = p.id_produto
        LEFT JOIN produto_imagem pi ON p.id_produto = pi.id_produto AND pi.imagem_principal = 1
        WHERE c.id_categoria IN ($ids_placeholders)
        GROUP BY c.id_categoria
        LIMIT 3
    ";
    
    // Preparar a instrução
    $stmt = $conexao->prepare($sql_destaques);
    
    // Montar a string de tipos (apenas inteiros 'i' para os IDs)
    $tipos = str_repeat('i', count($ids_categorias_destaque));
    
    // 1. Criar o array de argumentos, começando pela string de tipos
    $args = [$tipos];
    
    // 🚨 CORREÇÃO PRINCIPAL: Converter os valores dos IDs em referências
    // O '&' é crucial para passar por referência.
    foreach ($ids_categorias_destaque as $key => $value) {
        $args[] = &$ids_categorias_destaque[$key];
    }

    // 2. Executar o bind_param com a lista de referências
    call_user_func_array([$stmt, 'bind_param'], $args);
    
    $stmt->execute();

    // Obter os resultados e buscar linha por linha
    $result_destaques = $stmt->get_result();
    
    if ($result_destaques) {
        while ($d = $result_destaques->fetch_assoc()) {
            $destaques[] = $d;
        }
    }

    $stmt->close();
}
// ... (restante do código) ...
$sql_carrossel = "
    SELECT id_banner, titulo, descricao
    FROM banner_site 
    WHERE 
      posicao = 'hero' OR posicao = 'carrossel'
       AND destino = 'Início' 
        AND ativo = 1 
        AND (data_inicio IS NULL OR data_inicio <= CURDATE()) 
        AND (data_fim IS NULL OR data_fim >= CURDATE())
    ORDER BY 
        id_banner DESC 
    LIMIT 1
";
$result_carrossel = $conexao->query($sql_carrossel);
$carrossel_ativo = $result_carrossel->fetch_assoc();

$imagens_carrossel = [];
if ($carrossel_ativo) {
    $banner_id = $carrossel_ativo['id_banner'];

    // 2. Consulta SQL para buscar as IMAGENS DESTE CARROSSEL
    $sql_imagens = "
        SELECT caminho_imagem, ordem 
        FROM banner_imagens 
        WHERE id_banner = ? 
        ORDER BY ordem ASC
    ";
    $stmt_imagens = $conexao->prepare($sql_imagens);
    $stmt_imagens->bind_param("i", $banner_id);
    $stmt_imagens->execute();
    $result_imagens = $stmt_imagens->get_result();
    
    while ($img = $result_imagens->fetch_assoc()) {
        $imagens_carrossel[] = $img;
    }
}

// Consulta produtos da categoria "Promoções da Semana"
$sql = "
    SELECT
        p.*, p.preco, p.preco_promocional,
        GROUP_CONCAT(c.nome_categoria SEPARATOR ', ') AS categorias_nomes,
        img.caminho_imagem AS imagem_principal
    FROM
        produto p
    LEFT JOIN
        produto_categoria pc ON p.id_produto = pc.id_produto
    LEFT JOIN
        categoria c ON pc.id_categoria = c.id_categoria
    LEFT JOIN
        produto_imagem img ON img.id_produto = p.id_produto AND img.imagem_principal = 1
    WHERE
        c.nome_categoria = 'Promoções da Semana'
    GROUP BY
        p.id_produto
";
$result = $conexao->query($sql);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurante Virtual | Início</title>
    <link rel="stylesheet" href="css/index.css">

   
    <script src="js/darkmode1.js"></script>
    <!-- Adicionando o Tailwind CDN temporariamente para simular a estética moderna do cabeçalho -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-white">

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
      <a href="index.php" class="active">Início</a>
      <a href="cardapio.php">Cardápio</a>
      <a href="acerca_de_nos.php">Acerca de nós</a>
      <a href="ajuda.php">Ajuda</a>
    </nav>

    <!-- 🔹 BOTÃO MOBILE -->
    <button class="menu-btn" id="menuBtnMobile">&#9776;</button>
  </div>

  <!-- 🔹 MENU MOBILE (ESCONDIDO POR PADRÃO) -->
  <nav id="mobileMenu" class="nav-mobile hidden">
    <a href="index.php" class="active">Início</a>
    <a href="cardapio.php">Cardápio</a>
    <a href="acerca_de_nos.php">Acerca de nós</a>
    <a href="ajuda.php">Ajuda</a>
  </nav>
</header>


    <!-- FIM DO NOVO CABEÇALHO -->

    <!-- A secção Hero agora deve seguir o cabeçalho diretamente -->
    <section class="hero-container fade-in">
        <?php if ($carrossel_ativo && !empty($imagens_carrossel)): ?>
            
            <div class="carrossel-wrapper">
                <button class="carrossel-btn prev" aria-label="Anterior">&#10094;</button>
                <div class="banner-carrossel">
                    <?php foreach ($imagens_carrossel as $i => $img): ?>
                    <div class="carrossel-slide" style="background-image: url('<?= htmlspecialchars($img['caminho_imagem']) ?>');">
                        <div class="hero-content">
                            <h2><?= htmlspecialchars($carrossel_ativo['titulo']) ?></h2>
                            <p><?= htmlspecialchars($carrossel_ativo['descricao']) ?></p>
                            <a href="cardapio.php" class="btn">Pedir Agora</a> 
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="carrossel-btn next" aria-label="Próximo">&#10095;</button>
            </div>
            
        <?php else: ?>
            <div class="hero" style="background-image: url('https://i.imgur.com/8R2u6rj.jpg');">
                   <div class="hero-content">
                       <h2>Bem-vindo ao sabor!</h2>
                       <p>Peça os seus hambúrgueres favoritos com apenas um clique.</p>
                       <a href="cardapio.php" class="btn">Pedir Agora</a>
                   </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="quick-nav fade-in">
        <div class="container">
            <h2>Explore as Nossas Categorias Populares </h2>
            <div class="category-cards">
                
                <?php if (!empty($destaques)): ?>
                    <?php foreach ($destaques as $destaque): ?>
                        <a href="cardapio.php?categoria=<?= htmlspecialchars($destaque['id_categoria']) ?>" class="category-card slide-up">
                            <div class="card-image" style="background-image: url('<?= htmlspecialchars($destaque['caminho_imagem'] ?? 'caminho/para/imagem_default.jpg') ?>');"></div>
                            
                            <div class="card-content-wrap">
                                <h3 class="card-title"><?= htmlspecialchars($destaque['nome_categoria']) ?></h3>
                                <span class="btn-explorar">
                                    Explorar no Menu 
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="category-card fallback">
                        <div class="card-content-wrap">
                            <h3 class="card-title">Hambúrgueres</h3>
                            <span class="btn-explorar">Explorar Menu </span>
                        </div>
                    </div>
                    <div class="category-card fallback">
                        <div class="card-content-wrap">
                            <h3 class="card-title">Promoções</h3>
                            <span class="btn-explorar">Explorar Menu </span>
                        </div>
                    </div>
                    <div class="category-card fallback">
                        <div class="card-content-wrap">
                            <h3 class="card-title">Bebidas</h3>
                            <span class="btn-explorar">Explorar Menu &gt;</span>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </section>

    <!-- Promoções Dinâmicas -->
    <section class="promos fade-in">
    <h2>Promoções da Semana</h2>
    <div class="promo-grid">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($p = $result->fetch_assoc()): ?>
                <?php
                // Verifica disponibilidade (mesma lógica do promocoes.php)
                $sql_check = "
                    SELECT 
                        pi.quantidade_ingrediente AS quantidade_necessaria,
                        i.quantidade_estoque AS quantidade_disponivel
                    FROM 
                        produto_ingrediente pi
                    JOIN 
                        ingrediente i ON pi.id_ingrediente = i.id_ingrediente
                    WHERE 
                        pi.id_produto = ?
                ";
                $stmt_check = $conexao->prepare($sql_check);
                $stmt_check->bind_param("i", $p['id_produto']);
                $stmt_check->execute();
                $res_check = $stmt_check->get_result();

                $disponivel = true;
                while ($row = $res_check->fetch_assoc()) {
                    if ($row['quantidade_disponivel'] < $row['quantidade_necessaria']) {
                        $disponivel = false;
                        break;
                    }
                }
                ?>

                <!-- ===== CARD PROMOCIONAL ===== -->
                <div class="promo-card slide-up">
                    <img src="<?= $p['imagem_principal'] ?: 'imagens/sem_imagem.jpg' ?>" alt="Imagem do produto">

                    <div class="card-content">
                        <h3><?= htmlspecialchars($p['nome_produto']) ?></h3>

                        <?php if (!empty($p['preco_promocional']) && $p['preco_promocional'] < $p['preco']): ?>
                            <p>
                                <span class="preco-original">
                                    <?= number_format($p['preco'], 2, ',', '.') ?> MZN
                                </span><br>
                                <span class="preco-promocional">
                                    <?= number_format($p['preco_promocional'], 2, ',', '.') ?> MZN
                                </span>
                            </p>
                        <?php else: ?>
                            <p class="price">
                                <?= number_format($p['preco'], 2, ',', '.') ?> MZN
                            </p>
                        <?php endif; ?>

                        <p class="disponibilidade" style="color:<?= $disponivel ? 'lightgreen' : '#ff8080' ?>;">
                            <?= $disponivel ? 'Disponível' : 'Indisponível' ?>
                        </p>

                        <a href="detalhesproduto.php?id_produto=<?= $p['id_produto'] ?>" class="btn">Pede já!</a>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="message-empty">No momento não temos promoções disponíveis. Volte em breve!</p>
        <?php endif; ?>
    </div>
</section>

    <!-- Rodapé -->
    <footer class="footer fade-in">
        <div class="footer-content">
            <div>
                <h4>Contactos</h4>
                <p>📞 300 808 600</p>
                <p>📧 contacto@restaurantevirtual.pt</p>
            </div>
            <div>
                <h4>Informações</h4>
                <p><a href="#">Termos e Condições</a></p>
                <p><a href="#">Política de Privacidade</a></p>
            </div>
            <div>
                <h4>Redes Sociais</h4>
                <p>
                    <a href="#">Facebook</a> | 
                    <a href="#">Instagram</a> | 
                    <a href="#">TikTok</a>
                </p>
            </div>
        </div>
        <p class="copy">© 2025 Restaurante Virtual - Todos os direitos reservados.</p>
    </footer>
<script>
    
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

    // ===========================================
    // LÓGICA DO CARROSSEL HERO (EXISTENTE)
    // ===========================================
    const bannerCarrossel = document.querySelector('.banner-carrossel');
    const carrosselSlides = document.querySelectorAll('.carrossel-slide');
    const prevBtn = document.querySelector('.carrossel-btn.prev');
    const nextBtn = document.querySelector('.carrossel-btn.next');

    let currentSlide = 0;

    // Função para mostrar um slide específico
    function showSlide(index) {
        if (!bannerCarrossel || !carrosselSlides.length) return; // Garante que o carrossel existe

        // Redefine a animação para todos os conteúdos para que ela possa ser acionada novamente
        carrosselSlides.forEach(slide => {
            const content = slide.querySelector('.hero-content');
            if (content) {
                content.style.animation = 'none';
                content.offsetHeight; // Força o reflow para reiniciar a animação
                content.style.animation = '';
            }
        });

        // Calcula o scroll para o slide desejado
        bannerCarrossel.scrollTo({
            left: carrosselSlides[index].offsetLeft,
            behavior: 'smooth' // Anima o scroll
        });
        currentSlide = index;
    }

    // Navegação para o próximo slide
    function nextSlide() {
        currentSlide = (currentSlide + 1) % carrosselSlides.length;
        showSlide(currentSlide);
    }

    // Navegação para o slide anterior
    function prevSlide() {
        currentSlide = (currentSlide - 1 + carrosselSlides.length) % carrosselSlides.length;
        showSlide(currentSlide);
    }

    // Adiciona event listeners para as setas
    if (prevBtn) { // Verifica se os botões existem (só existem se houver carrossel)
        prevBtn.addEventListener('click', prevSlide);
        nextBtn.addEventListener('click', nextSlide);
    }

    // Opcional: Observar o scroll para atualizar o slide atual se o usuário rolar manualmente
    if (bannerCarrossel) {
        bannerCarrossel.addEventListener('scroll', () => {
            const scrollLeft = bannerCarrossel.scrollLeft;
            const slideWidth = carrosselSlides.length > 0 ? carrosselSlides[0].offsetWidth : 0;
            if (slideWidth > 0) {
                currentSlide = Math.round(scrollLeft / slideWidth);
            }
        });
    }

    // Inicializa o carrossel na ordem 1 (primeiro slide)
    if (carrosselSlides.length > 0) {
        showSlide(0); // Garante que o primeiro slide (ordem 1) seja visível ao carregar
    }

</script>
</body>
</html>