(() => {
  const uppercaseInputs = document.querySelectorAll('[data-uppercase-input]');

  if (!uppercaseInputs.length) {
    return;
  }

  const normalizeValue = (value) => value.toUpperCase();

  uppercaseInputs.forEach((input) => {
    if (!(input instanceof HTMLInputElement || input instanceof HTMLTextAreaElement)) {
      return;
    }

    input.addEventListener('input', () => {
      const { selectionStart, selectionEnd } = input;
      const normalizedValue = normalizeValue(input.value);

      if (input.value === normalizedValue) {
        return;
      }

      input.value = normalizedValue;

      if (selectionStart !== null && selectionEnd !== null) {
        input.setSelectionRange(selectionStart, selectionEnd);
      }
    });

    input.value = normalizeValue(input.value);
  });
})();
