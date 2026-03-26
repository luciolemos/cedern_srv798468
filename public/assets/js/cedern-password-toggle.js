(() => {
  const toggleButtons = document.querySelectorAll('[data-password-toggle]');

  if (!toggleButtons.length) {
    return;
  }

  const applyState = (button, input, visible) => {
    input.type = visible ? 'text' : 'password';
    button.textContent = visible ? 'Ocultar' : 'Mostrar';
    button.setAttribute('aria-label', visible ? 'Ocultar senha' : 'Mostrar senha');
    button.setAttribute('aria-pressed', visible ? 'true' : 'false');
  };

  toggleButtons.forEach((button) => {
    if (!(button instanceof HTMLButtonElement)) {
      return;
    }

    const targetId = button.getAttribute('aria-controls');
    if (!targetId) {
      return;
    }

    const input = document.getElementById(targetId);
    if (!(input instanceof HTMLInputElement)) {
      return;
    }

    applyState(button, input, input.type === 'text');

    button.addEventListener('click', () => {
      applyState(button, input, input.type !== 'text');
      input.focus({ preventScroll: true });
      input.setSelectionRange(input.value.length, input.value.length);
    });
  });
})();
