<?php
// Inclua os arquivos necessários
session_start();
include "conexao.php";
include "verifica_login_opcional.php"; 

// Lógica do Banner Hero
$sql_banner_contactos = "
    SELECT id_banner, titulo, descricao
    FROM banner_site 
    WHERE 
        posicao = 'hero' OR posicao = 'carrossel' 
        AND destino = 'Contactos' 
        AND ativo = 1 
        AND (data_inicio IS NULL OR data_inicio <= CURDATE()) 
        AND (data_fim IS NULL OR data_fim >= CURDATE())
    ORDER BY 
        id_banner DESC 
    LIMIT 1
";
$result_banner = $conexao->query($sql_banner_contactos);
$banner_ativo = $result_banner->fetch_assoc();

$imagens_banner = [];
if ($banner_ativo) {
    $banner_id = $banner_ativo['id_banner'];
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
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contactos | Instituto Superior Politécnico de Tete</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/contactos.css">
     <link rel="stylesheet" href="css/creditos.css">
</head>
<body>

   <header class="topbar">
  <div class="container">
    <div class="logo">
      <a href="index.php">
        <img class="logo" src="icones/logo.png" alt="Logo do ISPT">
      </a>
    </div>

    <nav class="nav-desktop">
      <a href="index.php">Início</a>
<a href="cardapio.php">Cardápio</a>
      <a href="acerca_de_nos.php">Acerca de nós</a>
       <a href="ajuda.php">Ajuda</a>
      <a href="contactos.php" class="active">Contactos</a>
    
    </nav>

    <button class="menu-btn" id="menuBtnMobile">&#9776;</button>
  </div>

  <nav id="mobileMenu" class="nav-mobile hidden">
    <a href="index.php">Início</a>
   <a href="cardapio.php">Cardápio</a>
    <a href="acerca_de_nos.php">Acerca de nós</a>
     <a href="ajuda.php">Ajuda</a>
    <a href="contactos.php" class="active">Contactos</a>

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
                            <p class="hero-subtitle"><?= htmlspecialchars($banner_ativo['descricao']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button class="carrossel-btn next" aria-label="Próximo">&#10095;</button>
            </div>
        <?php else: ?>
            <div class="hero" style="background-image: linear-gradient(rgba(0,0,0,0.5), rgba(0,0,0,0.5)), url('https://placehold.co/1200x600/2b2b2b/89b67f?text=Contacte-nos');">
                   <div class="hero-content">
                        <h1>Entre em Contacto</h1>
                        <p class="hero-subtitle">Estamos aqui para responder às suas questões e ajudá-lo no que precisar</p>
                   </div>
            </div>
        <?php endif; ?>
    </section>

    <main class="main-container">
        
        <section class="section-padding">
            <div class="section-header">
                <span class="section-badge">Fale Connosco</span>
                <h2 class="section-title">Informações de Contacto</h2>
                <p class="section-subtitle">Escolha a forma mais conveniente para entrar em contacto connosco</p>
            </div>

            <div class="contact-grid">
                <!-- Cartão de Telefone -->
                <div class="contact-card">
                    <div class="contact-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                    </div>
                    <h3>Telefone</h3>
                    <div class="contact-details">
                        <a href="tel:+258252220454" class="contact-link">
                            <span class="contact-value">(+258) 252 20454</span>
                        </a>
                        <a href="tel:+258252220454" class="contact-link">
                            <span class="contact-value">(+258) 252 20454</span>
                        </a>
                    </div>
                    <p class="contact-note">Segunda a Sexta: 08:00 - 17:00</p>
                </div>

                <!-- Cartão de Email -->
                <div class="contact-card">
                    <div class="contact-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3>Email</h3>
                    <div class="contact-details">
                        <a href="mailto:info@ispt.ac.mz" class="contact-link">
                            <span class="contact-value">info@ispt.ac.mz</span>
                        </a>
                    </div>
                    <p class="contact-note">Resposta em até 24 horas</p>
                </div>

                <!-- Cartão de Localização -->
                <div class="contact-card contact-card-wide">
                    <div class="contact-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3>Localização</h3>
                    <div class="contact-details">
                        <p class="contact-address">
                            <strong>Cidadela Académica</strong><br>
                            Estrada Nacional nº 7, Km 1<br>
                            Bairro Matundo, Tete
                        </p>
                        <a href="https://maps.google.com/?q=Cidadela+Académica+ISPT+Tete" target="_blank" class="directions-btn">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                            </svg>
                            Obter Direcções
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Mapa -->
        <section class="map-section">
            <div class="map-container">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3826.8!2d33.5869!3d-16.1562!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTbCsDA5JzIyLjMiUyAzM8KwMzUnMTIuOCJF!5e0!3m2!1spt-PT!2smz!4v1234567890"
                    width="100%" 
                    height="450" 
                    style="border:0;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </section>

      

        <!-- Links Rápidos -->
        <section class="section-padding section-links">
            <div class="section-header">
                <span class="section-badge">Acesso Rápido</span>
                <h2 class="section-title">Links Úteis</h2>
            </div>

            <div class="links-grid">
                <a href="#" class="link-card">
                    <div class="link-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h4>Propinas</h4>
                    <p>Informações sobre pagamentos</p>
                </a>

                <a href="#" class="link-card">
                    <div class="link-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h4>Formulários</h4>
                    <p>Downloads e documentação</p>
                </a>

                <a href="#" class="link-card">
                    <div class="link-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <h4>Cursos</h4>
                    <p>Programas académicos</p>
                </a>

                <a href="#" class="link-card">
                    <div class="link-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                        </svg>
                    </div>
                    <h4>Notícias</h4>
                    <p>Últimas actualizações</p>
                </a>

                <a href="#" class="link-card">
                    <div class="link-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h4>E-SURA</h4>
                    <p>Sistema académico</p>
                </a>

                <a href="#" class="link-card">
                    <div class="link-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <h4>Moodle ISPT</h4>
                    <p>Plataforma de e-learning</p>
                </a>
            </div>
        </section>

    </main>
    
    <footer class="footer-main">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4>ISPT</h4>
                    <p>Instituto Superior Politécnico de Tete - Formando o futuro de Moçambique</p>
                    <div class="social-links">
                        <a href="https://facebook.com/ispttete" target="_blank" aria-label="Facebook" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </a>
                        <a href="https://twitter.com/ispt" target="_blank" aria-label="Twitter" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                        </a>
                        <a href="https://instagram.com/ispttete" target="_blank" aria-label="Instagram" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z"/>
                            </svg>
                        </a>
                        <a href="https://youtube.com/ispttete" target="_blank" aria-label="YouTube" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                            </svg>
                        </a>
                        <a href="https://linkedin.com/company/ispt" target="_blank" aria-label="LinkedIn" class="social-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>Links Rápidos</h4>
                    <a href="index.php">Início</a>
                    <a href="ver_monografias.php">Ver Monografias</a>
                    <a href="acerca_de_nos.php">Acerca de Nós</a>
                    <a href="contactos.php">Contactos</a>
                    <a href="ajuda.php">Ajuda</a>
                </div>
                <div class="footer-section">
                    <h4>Recursos</h4>
                        <a href="https://propinas.ispt.ac.mz/login.php">Propinas</a>
                    <a href="https://esura.ispt.ac.mz/esura_ispt/">E-SURA</a>
                    <a href="https://elearning.ispt.ac.mz/">Moodle ISPT</a>
                </div>
                <div class="footer-section">
                    <h4>Contacto</h4>
                    <p>Email: info@ispt.ac.mz</p>
                    <p>Tel: (+258) 252 20454</p>
                    <p>Estrada Nacional nº 7, Km 1<br>Bairro Matundo, Tete</p>
                </div>
            </div>
               <div class="footer-bottom">
                <p>&copy; 2025 Instituto Superior Politécnico de Tete. All Rights Reserved.</p>
                
                <div class="developer-credit">
                    Desenvolvido por 
                    <a href="https://wa.me/25887682594?text=Olá,%20Matias!%20Vi%20o%20teu%20trabalho%20no%20site%20do%20ISPT." target="_blank" class="developer-link">
                        Matias Alberto Matavel
                    </a>
                </div>
                </div>
        </div>
    </footer>

       <a href="https://wa.me/258876821594" target="_blank" class="floating-credit-fab" title="Desenvolvido por Matias Alberto Matavel (Contacte via WhatsApp)">
    Desenvolvido por 
    <span class="developer-name">Matias Alberto Matavel</span>
</a>


    <script>
    // Navegação Mobile
    const menuBtnMobile = document.getElementById("menuBtnMobile");
    const mobileMenu = document.getElementById("mobileMenu");

    if (menuBtnMobile && mobileMenu) {
        menuBtnMobile.addEventListener("click", () => {
            mobileMenu.classList.toggle("hidden");
        });
    }

    // Carrossel Hero
    const bannerCarrossel = document.querySelector('.banner-carrossel');
    const carrosselSlides = document.querySelectorAll('.carrossel-slide');
    const prevBtn = document.querySelector('.carrossel-btn.prev');
    const nextBtn = document.querySelector('.carrossel-btn.next');

    let currentSlide = 0;

    function showSlide(index) {
        if (!bannerCarrossel || !carrosselSlides.length) return;

        carrosselSlides.forEach(slide => {
            const content = slide.querySelector('.hero-content');
            if (content) {
                content.style.animation = 'none';
                content.offsetHeight;
                content.style.animation = '';
            }
        });

        bannerCarrossel.scrollTo({
            left: carrosselSlides[index].offsetLeft,
            behavior: 'smooth'
        });
        currentSlide = index;
    }

    function nextSlide() {
        currentSlide = (currentSlide + 1) % carrosselSlides.length;
        showSlide(currentSlide);
    }

    function prevSlide() {
        currentSlide = (currentSlide - 1 + carrosselSlides.length) % carrosselSlides.length;
        showSlide(currentSlide);
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', prevSlide);
        nextBtn.addEventListener('click', nextSlide);
    }

    if (bannerCarrossel) {
        bannerCarrossel.addEventListener('scroll', () => {
            const scrollLeft = bannerCarrossel.scrollLeft;
            const slideWidth = carrosselSlides.length > 0 ? carrosselSlides[0].offsetWidth : 0;
            if (slideWidth > 0) {
                currentSlide = Math.round(scrollLeft / slideWidth);
            }
        });
    }

    if (carrosselSlides.length > 0) {
        showSlide(0);
    }

    // Formulário de Contacto
    const contactForm = document.getElementById('contactForm');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Aqui você pode adicionar a lógica para enviar o formulário
            // Por exemplo, usando fetch para enviar para um endpoint PHP
            
            alert('Mensagem enviada com sucesso! Entraremos em contacto em breve.');
            contactForm.reset();
        });
    }

    // Animação de Scroll
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.contact-card, .link-card, .form-container').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = 'all 0.6s ease-out';
        observer.observe(el);
    });
    </script>
</body>
</html>