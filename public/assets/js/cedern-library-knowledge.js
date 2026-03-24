const initCedernLibraryKnowledge = () => {
  const toggles = Array.from(document.querySelectorAll("[data-knowledge-toggle]"));

  toggles.forEach((toggle) => {
    if (!(toggle instanceof HTMLButtonElement)) {
      return;
    }

    const panelId = toggle.getAttribute("aria-controls") || "";
    const panel = panelId ? document.getElementById(panelId) : null;
    const icon = toggle.querySelector(".nc-knowledge-toggle-icon");
    const label = toggle.querySelector(".nc-knowledge-toggle-label");

    if (!(panel instanceof HTMLElement) || !icon || !label) {
      return;
    }

    const setExpanded = (expanded) => {
      toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
      toggle.classList.toggle("is-open", expanded);
      panel.hidden = !expanded;
      icon.textContent = expanded ? "−" : "+";
      label.textContent = expanded ? "Ocultar ficha" : "Ficha técnica";
    };

    setExpanded(toggle.getAttribute("aria-expanded") === "true");

    toggle.addEventListener("click", () => {
      const expanded = toggle.getAttribute("aria-expanded") === "true";
      setExpanded(!expanded);
    });
  });
};

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initCedernLibraryKnowledge);
} else {
  initCedernLibraryKnowledge();
}
