document.addEventListener("DOMContentLoaded", () => {
    if (!localStorage.getItem("cookiesLGPD")) {
        document.getElementById("cookieBanner").style.display = "flex";
    }
});

async function aceitarCookies() {
    localStorage.setItem("cookiesLGPD", "aceito");

    await fetch("../api/aceitar-cookies.php", {
        method: "POST",
        credentials: "same-origin"
    }).catch(() => {});

    document.getElementById("cookieBanner").style.display = "none";
}