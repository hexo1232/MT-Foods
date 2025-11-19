


document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.getElementById("darkToggle");
  const carrinho = document.getElementById("iconeCarrinho");
  const logout = document.getElementById("iconelogout");

  // Funções de fallback (para navegadores sem localStorage)
  let suporteLocalStorage = true;
  try {
    localStorage.setItem("__teste__", "ok");
    localStorage.removeItem("__teste__");
  } catch (e) {
    suporteLocalStorage = false;
  }

  function getStorage(key) {
    if (suporteLocalStorage) {
      return localStorage.getItem(key);
    } else {
      return document.body.dataset[key] || null;
    }
  }

  function setStorage(key, value) {
    if (suporteLocalStorage) {
      localStorage.setItem(key, value);
    } else {
      document.body.dataset[key] = value; // guarda apenas na memória da sessão
    }
  }

  // Função principal de aplicar dark mode
  function aplicarDarkMode(estado) {
    if (estado === "enabled") {
      document.body.classList.add("dark-mode");
      if (toggle) toggle.src = "icones/sol.png"; 
      // if (carrinho) carrinho.src = "icones/carrinho2.png"; 
      if (logout) logout.src = "icones/logout2.png"; 
    } else {
      document.body.classList.remove("dark-mode");
      if (toggle) toggle.src = "icones/lua.png"; 
      // if (carrinho) carrinho.src = "icones/carrinho1.png"; 
      if (logout) logout.src = "icones/logout1.png"; 
    }
  }

  // Estado inicial (padrão claro)
  const darkMode = getStorage("darkMode") || "disabled";
  aplicarDarkMode(darkMode);

  // Alternar dark mode
  if (toggle) {
    toggle.addEventListener("click", () => {
      if (document.body.classList.contains("dark-mode")) {
        setStorage("darkMode", "disabled");
        aplicarDarkMode("disabled");
      } else {
        setStorage("darkMode", "enabled");
        aplicarDarkMode("enabled");
      }
    });
  }
});
