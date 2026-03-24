(function () {
  const form = document.querySelector('[data-bookshop-cover-preview-form]');
  if (!form) {
    return;
  }

  const previewWrap = form.querySelector('[data-bookshop-cover-preview-wrap]');
  const coverInput = form.querySelector('[data-bookshop-cover-input]');
  const removeInput = form.querySelector('[data-bookshop-cover-remove]');
  const fallbackTemplate = form.querySelector('[data-bookshop-cover-fallback-template]');

  if (!previewWrap || !coverInput) {
    return;
  }

  const titleInput = form.querySelector('#title');
  const initialPreviewMarkup = previewWrap.innerHTML;
  const fallbackPreviewMarkup = fallbackTemplate ? fallbackTemplate.innerHTML : initialPreviewMarkup;
  let currentObjectUrl = null;

  const releaseCurrentObjectUrl = () => {
    if (!currentObjectUrl) {
      return;
    }

    URL.revokeObjectURL(currentObjectUrl);
    currentObjectUrl = null;
  };

  const renderMarkup = (markup) => {
    releaseCurrentObjectUrl();
    previewWrap.innerHTML = markup;
  };

  const renderSelectedFile = (file) => {
    releaseCurrentObjectUrl();
    currentObjectUrl = URL.createObjectURL(file);

    previewWrap.innerHTML = '';

    const image = document.createElement('img');
    image.className = 'nc-library-admin-cover-preview';
    image.src = currentObjectUrl;
    image.alt = 'Pré-visualização da capa de ' + ((titleInput && titleInput.value.trim()) || 'livro');
    previewWrap.appendChild(image);
  };

  const syncPreview = () => {
    const file = coverInput.files && coverInput.files[0] ? coverInput.files[0] : null;

    if (file && file.type && file.type.indexOf('image/') === 0) {
      if (removeInput) {
        removeInput.checked = false;
      }

      renderSelectedFile(file);
      return;
    }

    if (removeInput && removeInput.checked) {
      renderMarkup(fallbackPreviewMarkup);
      return;
    }

    renderMarkup(initialPreviewMarkup);
  };

  coverInput.addEventListener('change', syncPreview);

  if (removeInput) {
    removeInput.addEventListener('change', syncPreview);
  }

  window.addEventListener('beforeunload', releaseCurrentObjectUrl);
})();
