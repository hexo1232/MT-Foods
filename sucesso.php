<?php
$redirecionar = true; // Mantém a lógica de redirecionamento

echo "
    <div id='popup' class='popup'> 
        <div class='popup-content'>
            <h3 style='color: #28a745; margin-bottom: 10px;'>Sucesso! O seu pedido foi confirmado!</h3>
            <p style='color: #343a40;'>Tudo pronto! O seu pedido está a ser processado.</p>
            <p style='color: #6c757d; font-size: 0.9em; margin-top: 15px;'>Irá ser automaticamente redirecionado(a) para o cardápio em <span id='countdown'>5</span> segundos...</p>
        </div>
    </div>
    
    <script>
        const REDIRECT_TIME = 5000; // 5 segundos
        const FADE_TIME = 1000;     // 1 segundo para o fade-out
        const popup = document.getElementById('popup');
        const countdownElement = document.getElementById('countdown');

        // 1. Mostrar o popup (fade-in)
        function mostrarPopup() {
            // Garante que o popup está visível para começar o fade-in
            popup.style.opacity = '1';
        }

        // 2. Contagem regressiva e fade-out antes de redirecionar
        function iniciarRedirecionamento() {
            let tempoRestante = REDIRECT_TIME / 1000;
            countdownElement.textContent = tempoRestante;

            const intervalo = setInterval(() => {
                tempoRestante--;
                countdownElement.textContent = tempoRestante;

                // 1 segundo antes do fim, inicia o fade-out
                if (tempoRestante === 1) {
                    popup.style.opacity = '0'; 
                }
                
                // Redireciona no tempo total
                if (tempoRestante === 0) {
                    clearInterval(intervalo);
                    window.location.href = 'cardapio.php';
                }
            }, 1000); // 1 segundo
        }

        // Inicia o processo
        mostrarPopup();
        iniciarRedirecionamento();
    </script>
    
    <style>
        /* Animações CSS para um Fade-in/Fade-out suave */
        .popup {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0; /* Começa invisível para o fade-in */
            transition: opacity 1s ease-in-out; /* Transição suave */
        }
        .popup-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.15); /* Sombra mais suave */
            max-width: 80%;
            transform: scale(1);
            transition: transform 0.5s ease;
        }
        /* Efeito de zoom no popup para um toque amigável */
        .popup:hover .popup-content {
            transform: scale(1.03); 
        }
        #countdown {
            font-weight: bold;
            color: #dc3545; /* Cor de destaque para o tempo */
            font-size: 1.1em;
        }
    </style>";
?>