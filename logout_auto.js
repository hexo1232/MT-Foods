// (function () {
//     const host = window.location.hostname;
//     let isNavigatingInternally = false;

//     document.addEventListener("click", function (e) {
//         const link = e.target.closest("a");
//         if (link && link.href && link.origin === window.location.origin) {
//             isNavigatingInternally = true;
//         }
//     });

//     document.addEventListener("submit", function () {
//         isNavigatingInternally = true;
//     });

//     function logoutSilencioso() {
//         try {
//             navigator.sendBeacon("/logout_silencioso.php");
//         } catch (err) {
//             console.error("Erro ao tentar logout automático:", err);
//         }
//     }

//     if (host === "localhost" || host === "127.0.0.1") {
//         // No localhost, só logout se o navegador for fechado
//         window.addEventListener("beforeunload", function (e) {
//             if (!isNavigatingInternally) {
//                 // Verifica se é fechamento total (não troca de aba interna)
//                 if (!document.hasFocus()) {
//                     logoutSilencioso();
//                 }
//             }
//         });
//     } else {
//         // Produção: logout se sair para outro domínio ou fechar aba
//         window.addEventListener("beforeunload", function () {
//             if (!isNavigatingInternally) {
//                 logoutSilencioso();
//             }
//         });

//         window.addEventListener("offline", function () {
//             logoutSilencioso();
//         });
//     }
// })();

(function () {
    const host = window.location.hostname;
    let isNavigatingInternally = false;
    let inatividadeTimer;
    const TEMPO_INATIVIDADE = 15 * 60 * 1000; // 15 minutos em ms

    // Detecta navegação interna
    document.addEventListener("click", function (e) {
        const link = e.target.closest("a");
        if (link && link.href && link.origin === window.location.origin) {
            isNavigatingInternally = true;
        }
        resetarTimerInatividade();
    });

    document.addEventListener("submit", function () {
        isNavigatingInternally = true;
        resetarTimerInatividade();
    });

    // Função de logout silencioso
    function logoutSilencioso() {
        try {
            navigator.sendBeacon("/logout_silencioso.php");
            // Opcional: redirecionar para login após logout
            window.location.href = "login.php";
        } catch (err) {
            console.error("Erro ao tentar logout automático:", err);
        }
    }

    // Timer de inatividade
    function iniciarTimerInatividade() {
        limparTimerInatividade();
        inatividadeTimer = setTimeout(logoutSilencioso, TEMPO_INATIVIDADE);
    }

    function resetarTimerInatividade() {
        iniciarTimerInatividade();
    }

    function limparTimerInatividade() {
        if (inatividadeTimer) {
            clearTimeout(inatividadeTimer);
        }
    }

    // Eventos que reiniciam o timer
    ["mousemove", "keydown", "scroll", "touchstart"].forEach(evento => {
        document.addEventListener(evento, resetarTimerInatividade);
    });

    // Inicia o timer ao carregar a página
    iniciarTimerInatividade();

    // Regras originais de beforeunload
    if (host === "localhost" || host === "127.0.0.1") {
        window.addEventListener("beforeunload", function () {
            if (!isNavigatingInternally && !document.hasFocus()) {
                logoutSilencioso();
            }
        });
    } else {
        window.addEventListener("beforeunload", function () {
            if (!isNavigatingInternally) {
                logoutSilencioso();
            }
        });

        window.addEventListener("offline", function () {
            logoutSilencioso();
        });
    }
})();

