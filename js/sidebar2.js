// ===========================================
// SIDEBAR2.JS - MENU MOBILE CARDÁPIO
// ===========================================

(function() {
    'use strict';

    // Aguardar o carregamento completo do DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inicializarMenuMobile);
    } else {
        inicializarMenuMobile();
    }

    function inicializarMenuMobile() {
        console.log('Inicializando menu mobile...');

        const menuBtnMobile = document.getElementById("menuBtnMobile");
        const mobileMenu = document.getElementById("mobileMenu");
        const menuOverlay = document.getElementById("menuOverlay");
        const closeMobileMenu = document.getElementById("closeMobileMenu");

        // Debug: verificar se os elementos foram encontrados
        console.log('Elementos encontrados:', {
            menuBtnMobile: !!menuBtnMobile,
            mobileMenu: !!mobileMenu,
            menuOverlay: !!menuOverlay,
            closeMobileMenu: !!closeMobileMenu
        });

        if (!menuBtnMobile || !mobileMenu || !menuOverlay) {
            console.error('Erro: Elementos do menu não encontrados!');
            return;
        }

        // ===========================================
        // FUNÇÃO PARA ABRIR O MENU
        // ===========================================
        function abrirMenu() {
            console.log('Abrindo menu...');
            mobileMenu.classList.remove("hidden");
            mobileMenu.classList.add("active");
            menuOverlay.classList.remove("hidden");
            menuOverlay.classList.add("active");
            document.body.style.overflow = "hidden";
        }

        // ===========================================
        // FUNÇÃO PARA FECHAR O MENU
        // ===========================================
        function fecharMenu() {
            console.log('Fechando menu...');
            mobileMenu.classList.remove("active");
            mobileMenu.classList.add("hidden");
            menuOverlay.classList.remove("active");
            menuOverlay.classList.add("hidden");
            document.body.style.overflow = "";
        }

        // ===========================================
        // EVENT LISTENERS
        // ===========================================

        // Abrir menu ao clicar no botão hamburger
        menuBtnMobile.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Botão hamburger clicado');
            abrirMenu();
        });

        // Fechar menu ao clicar no botão X
        if (closeMobileMenu) {
            closeMobileMenu.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Botão fechar clicado');
                fecharMenu();
            });
        }

        // Fechar menu ao clicar no overlay
        menuOverlay.addEventListener("click", function(e) {
            console.log('Overlay clicado');
            fecharMenu();
        });

        // Fechar menu ao clicar em qualquer link da sidebar
        const sidebarLinks = document.querySelectorAll(".sidebar-links a");
        console.log('Links encontrados na sidebar:', sidebarLinks.length);
        
        sidebarLinks.forEach(function(link) {
            link.addEventListener("click", function() {
                console.log('Link clicado, fechando menu...');
                setTimeout(function() {
                    fecharMenu();
                }, 150);
            });
        });

        // Fechar menu com tecla ESC
        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape" && mobileMenu.classList.contains("active")) {
                console.log('ESC pressionado, fechando menu...');
                fecharMenu();
            }
        });

        // ===========================================
        // DROPDOWN DO PERFIL NA SIDEBAR
        // ===========================================
        const sidebarUserDropdown = document.getElementById('sidebarUserDropdown');
        const sidebarProfileDropdown = document.getElementById('sidebarProfileDropdown');

        if (sidebarUserDropdown && sidebarProfileDropdown) {
            sidebarUserDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
                console.log('Dropdown do perfil clicado');
                
                // Toggle das classes
                sidebarUserDropdown.classList.toggle('active');
                sidebarProfileDropdown.classList.toggle('active');
            });

            // Fechar dropdown ao clicar em um link dentro dele
            const dropdownLinks = sidebarProfileDropdown.querySelectorAll('a');
            dropdownLinks.forEach(function(link) {
                link.addEventListener('click', function() {
                    console.log('Link do dropdown clicado, fechando sidebar...');
                    setTimeout(function() {
                        fecharMenu();
                    }, 150);
                });
            });
        }

        // ===========================================
        // PREVENIR SCROLL HORIZONTAL
        // ===========================================
        if (window.innerWidth <= 768) {
            document.body.style.overflowX = "hidden";
        }

        // Ajustar ao redimensionar a janela
        let timeoutResize;
        window.addEventListener('resize', function() {
            clearTimeout(timeoutResize);
            timeoutResize = setTimeout(function() {
                if (window.innerWidth > 768) {
                    fecharMenu();
                    document.body.style.overflowX = "";
                } else {
                    document.body.style.overflowX = "hidden";
                }
            }, 250);
        });

        console.log('Menu mobile inicializado com sucesso!');
    }


})();