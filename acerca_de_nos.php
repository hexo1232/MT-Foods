<?php
// Inclua os arquivos necessários
session_start();
include "conexao.php";
// Opcional, dependendo se esta página precisa de login, mas mantido para consistência
include "verifica_login_opcional.php"; 

// =========================================================================
// LÓGICA DO BANNER HERO (ADAPTADA DO INDEX.PHP)
// 1. Consulta SQL para buscar o banner mais recente com destino 'Acerca de Nós'
// =========================================================================

$sql_banner_acerca = "
    SELECT id_banner, titulo, descricao
    FROM banner_site 
    WHERE 
        posicao = 'hero' OR posicao = 'carrossel' 
        AND destino = 'Acerca de Nós' 
        AND ativo = 1 
        AND (data_inicio IS NULL OR data_inicio <= CURDATE()) 
        AND (data_fim IS NULL OR data_fim >= CURDATE())
    ORDER BY 
        id_banner DESC 
    LIMIT 1
";
$result_banner = $conexao->query($sql_banner_acerca);
$banner_ativo = $result_banner->fetch_assoc();

$imagens_banner = [];
if ($banner_ativo) {
    $banner_id = $banner_ativo['id_banner'];

    // 2. Consulta SQL para buscar as IMAGENS DESTE BANNER/CARROSSEL
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
        $imagens_banner[] = $img;
    }
    $stmt_imagens->close();
}

// =========================================================================
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acerca da MT Foods | A Nossa História</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/index.css">

    <style>
        /* Paleta de Cores: Preto (#2b2b2b), Laranja (#ffb300) */
        
        /* A altura do Hero será definida pelo CSS do index.css, mas podemos garantir um mínimo */
        .hero-container, .hero {
            height: 50vh !important; /* Força uma altura mínima, mas o CSS do index.php deve prevalecer */
            min-height: 400px;
        }

        /* Estilos adicionais específicos (os demais do index.css já devem aplicar) */
        .hero-content h1 {
            font-size: 3rem; /* 48px */
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
            color: #ffb300; /* Aplica a cor laranja ao título do About Us */
        }
        
        .section-padding {
            padding: 60px 20px;
        }

        /* Estilo para os blocos de Missão/Visão */
        .value-card {
            background-color: #f7f7f7;
            padding: 30px;
            border-radius: 12px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }

        .value-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .value-card h3 {
            color: #ffb300;
            font-weight: 700;
            margin-bottom: 15px;
            border-bottom: 3px solid #ffb300;
            display: inline-block;
            padding-bottom: 5px;
        }

        /* Responsividade para o Hero */
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2.5rem;
            }
        }
        @media (max-width: 640px) {
            .hero-container, .hero {
                height: 35vh !important;
                min-height: 250px;
            }
            .hero-content h1 {
                font-size: 2rem;
            }
            .section-padding {
                padding: 40px 15px;
            }
        }
    </style>
</head>
<body style="font-family: 'Inter', sans-serif;">

    <header class="topbar">
    <div class="container">

        <div class="logo">
            <a href="index.php">
                <img class="logo" src="icones/logo.png" alt="Logo do Restaurante">
            </a>
        </div>

        <nav class="nav-desktop">
            <a href="index.php">Início</a>
            <a href="cardapio.php">Cardápio</a>
            <a href="acerca_de_nos.php" class="active">Acerca de Nós</a>
            <a href="ajuda.php">Ajuda</a>
        </nav>

        <button class="menu-btn" id="menuBtnMobile">&#9776;</button>
    </div>

    <nav id="mobileMenu" class="nav-mobile hidden">
        <a href="index.php">Início</a>
        <a href="cardapio.php">Cardápio</a>
        <a href="acerca_de_nos.php" class="active">Acerca de Nós</a>
        <a href="ajuda.php">Ajuda</a>
    </nav>
</header>


    <section class="hero-container fade-in">
        <?php if ($banner_ativo && !empty($imagens_banner)): ?>
            
            <div class="carrossel-wrapper">
                <button class="carrossel-btn prev" aria-label="Anterior">&#10094;</button>
                <div class="banner-carrossel">
                    <?php foreach ($imagens_banner as $i => $img): ?>
                    <div class="carrossel-slide" style="background-image: url('<?= htmlspecialchars($img['caminho_imagem']) ?>');">
                        <div class="hero-content">
                            <h1><?= htmlspecialchars($banner_ativo['titulo']) ?></h1>
                            <p class="text-xl md:text-2xl font-medium"><?= htmlspecialchars($banner_ativo['descricao']) ?></p>
                            <a href="cardapio.php" class="btn">Ver Cardápio</a> 
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="carrossel-btn next" aria-label="Próximo">&#10095;</button>
            </div>
            
        <?php else: ?>
            <div class="hero" style="background-image: url('https://placehold.co/1200x600/2b2b2b/ffb300?text=A+Nossa+História');">
                   <div class="hero-content">
                        <h1 class="text-[#ffb300]">A História por Trás da MT Foods</h1>
                        <p class="text-xl md:text-2xl font-medium">Desde 2018, entregando qualidade, sabor e conveniência.</p>
                        <a href="cardapio.php" class="btn">Ver Cardápio</a>
                   </div>
            </div>
        <?php endif; ?>
    </section>

    <main class="max-w-7xl mx-auto">
        
        <section class="section-padding bg-white">
            <h2 class="text-3xl md:text-4xl font-bold text-center text-[#2b2b2b] mb-4">Quem Somos?</h2>
            <p class="text-center text-lg text-gray-700 max-w-4xl mx-auto mb-12">
                A MT Foods nasceu de uma simples paixão: elevar a experiência de *fast food* a um novo nível. Não somos apenas mais uma plataforma de entrega; somos uma equipa dedicada a cozinhar com ingredientes frescos, qualidade inegável e conveniência máxima, tudo a pensar em si.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
                <div class="p-4">
                    <svg class="w-10 h-10 mx-auto text-[#ffb300] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="font-semibold text-[#2b2b2b]">Entrega Rápida</p>
                    <p class="text-gray-600 text-sm">A sua comida, sempre quente e a tempo.</p>
                </div>
                <div class="p-4">
                    <svg class="w-10 h-10 mx-auto text-[#ffb300] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.008 12.008 0 002.944 12c.007 2.15.545 4.225 1.579 6.082L12 21.05l7.477-7.477a12 12 0 001.579-6.082A11.955 11.955 0 0112 2.944z"></path></svg>
                    <p class="font-semibold text-[#2b2b2b]">Ingredientes de Qualidade</p>
                    <p class="text-gray-600 text-sm">Focamos na frescura e no sabor em cada refeição.</p>
                </div>
                <div class="p-4">
                    <svg class="w-10 h-10 mx-auto text-[#ffb300] mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c-2 2-2 4-2 4.5l-2.5 2.5m1.144-1.144l.856.856m0 0l-1.144 1.144m1.144-1.144A7.989 7.989 0 0112 5m0 0a8 8 0 018 8"></path></svg>
                    <p class="font-semibold text-[#2b2b2b]">Fácil de Encomendar</p>
                    <p class="text-gray-600 text-sm">A nossa app e website tornam o processo simples e intuitivo.</p>
                </div>
            </div>
        </section>

        <section class="section-padding bg-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <div class="value-card">
                    <h3 class="text-2xl">A Nossa Missão</h3>
                    <p class="text-gray-700">A Missão da MT Foods é fornecer refeições rápidas e de alta qualidade que superam as expectativas dos nossos clientes, combinando conveniência, frescura e sabor. Esforçamo-nos para ser a escolha principal para quem procura uma refeição deliciosa sem comprometer a qualidade.</p>
                </div>
                
                <div class="value-card">
                    <h3 class="text-2xl">A Nossa Visão</h3>
                    <p class="text-gray-700">Tornar-nos a marca de referência no segmento de entrega de comida rápida em Portugal, reconhecidos pela inovação constante no cardápio, pela excelência no serviço ao cliente e pelo compromisso com práticas sustentáveis. Queremos levar o melhor da comida rápida a todos os lares.</p>
                </div>
                
                <div class="value-card">
                    <h3 class="text-2xl">Os Nossos Valores</h3>
                    <ul class="list-disc list-inside text-gray-700 space-y-2">
                        <li>**Qualidade:** Nunca comprometemos os nossos ingredientes.</li>
                        <li>**Agilidade:** Valorizamos o seu tempo e a rapidez da entrega.</li>
                        <li>**Integridade:** Somos transparentes nas nossas práticas.</li>
                        <li>**Inovação:** Procuramos constantemente novos sabores e tecnologias.</li>
                    </ul>
                </div>

            </div>
        </section>

        <section class="section-padding text-center bg-[#2b2b2b] text-white">
            <h2 class="text-3xl font-bold text-[#ffb300] mb-4">Pronto para Saborear?</h2>
            <p class="text-lg mb-6">Explore o nosso menu completo e descubra o seu próximo prato favorito da MT Foods.</p>
            <a href="cardapio.php" class="inline-block bg-[#ffb300] text-[#2b2b2b] font-bold py-3 px-8 rounded-full shadow-lg hover:bg-[#ff9900] transition duration-300 transform hover:scale-105">
                Ver Cardápio Agora
            </a>
        </section>

    </main>
    
    <footer class="bg-white">
        <div class="footer max-w-7xl mx-auto text-white mt-0">
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
    // LÓGICA DO CARROSSEL HERO (COPIADA DO INDEX.PHP)
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