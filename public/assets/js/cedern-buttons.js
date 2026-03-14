(() => {
  const interactiveButtons = document.querySelectorAll(".nc-btn[data-loading-on-click], .nc-link[data-loading-on-click]");

  if (!interactiveButtons.length) {
    return;
  }

  interactiveButtons.forEach((button) => {
    button.addEventListener("click", () => {
      if (button.getAttribute("aria-disabled") === "true") {
        return;
      }

      button.classList.add("is-loading");
    });
  });
})();
