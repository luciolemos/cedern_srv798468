function initCedernTheme() {
  var root = document.documentElement;
  var body = document.body;
  var themeStorageKey = 'natalcode_theme';
  var modeStorageKey = 'natalcode_mode';
  var darkIntensityStorageKey = 'natalcode_dark_intensity';
  var desktopPalettePositionStorageKey = 'natalcode_desktop_palette_position';
  var mobilePalettePositionStorageKey = 'natalcode_mobile_palette_position';
  var themeDefinitions = {
    amber: { label: 'Amber', ariaLabel: 'Ativar tema âmbar', dotClass: 'nc-dot-amber' },
    blue: { label: 'Blue', ariaLabel: 'Ativar tema azul', dotClass: 'nc-dot-blue' },
    green: { label: 'Green', ariaLabel: 'Ativar tema verde', dotClass: 'nc-dot-green' },
    red: { label: 'Red', ariaLabel: 'Ativar tema vermelho', dotClass: 'nc-dot-red' },
    violet: { label: 'Violet', ariaLabel: 'Ativar tema violeta', dotClass: 'nc-dot-violet' }
  };
  var modeDefinitions = {
    light: { label: 'Light', ariaLabel: 'Ativar modo claro' },
    dark: { label: 'Dark', ariaLabel: 'Ativar modo escuro' }
  };
  var darkIntensityDefinitions = {
    neutral: { label: 'Neutral', ariaLabel: 'Ativar escuro neutro' },
    vivid: { label: 'Vivid', ariaLabel: 'Ativar escuro vívido' }
  };
  var runtimeThemeConfig = window.cedernThemeConfig || {};
  var allowedThemes = resolveAllowedValues(runtimeThemeConfig.allowedThemes, themeDefinitions);
  var allowedModes = resolveAllowedValues(runtimeThemeConfig.allowedModes, modeDefinitions);
  var allowedDarkIntensities = resolveAllowedValues(
    runtimeThemeConfig.allowedDarkIntensities,
    darkIntensityDefinitions
  );
  var themePreferencesEnabled = isThemePaletteEnabled();

  function isThemePaletteEnabled() {
    return body && body.getAttribute('data-theme-palette') === 'enabled';
  }

  function resolveAllowedValues(values, definitions) {
    var definitionKeys = Object.keys(definitions);
    if (!Array.isArray(values)) {
      return definitionKeys;
    }

    var allowedValues = [];

    values.forEach(function (value) {
      var normalized = String(value || '').toLowerCase();
      if (!Object.prototype.hasOwnProperty.call(definitions, normalized)) {
        return;
      }

      if (allowedValues.indexOf(normalized) !== -1) {
        return;
      }

      allowedValues.push(normalized);
    });

    return allowedValues.length > 0 ? allowedValues : definitionKeys;
  }

  function hasPaletteControls() {
    if (allowedThemes.length > 1 || allowedModes.length > 1) {
      return true;
    }

    return allowedModes.indexOf('dark') !== -1 && allowedDarkIntensities.length > 1;
  }

  function buildModeButtons(values, definitions, dataAttribute) {
    return values.map(function (value) {
      var definition = definitions[value];
      if (!definition) {
        return '';
      }

      return '<button type="button" class="nc-mode-btn" '
        + dataAttribute + '="' + value + '" aria-pressed="false" aria-label="'
        + definition.ariaLabel + '">' + definition.label + '</button>';
    }).join('');
  }

  function buildThemeSwatches() {
    return allowedThemes.map(function (value) {
      var definition = themeDefinitions[value];
      if (!definition) {
        return '';
      }

      return '<button type="button" class="nc-swatch" data-theme-value="' + value
        + '" aria-pressed="false" aria-label="' + definition.ariaLabel + '"><span class="nc-swatch-dot '
        + definition.dotClass + '"></span><span class="nc-swatch-label">' + definition.label + '</span></button>';
    }).join('');
  }

  function ensurePaletteMarkup() {
    if (document.querySelector('[data-utility-stack]')) {
      return;
    }

    var shell = document.querySelector('.nc-shell') || document.body;
    if (!shell) {
      return;
    }

    var paletteMarkup = '';
    if (isThemePaletteEnabled() && hasPaletteControls()) {
      var paletteSections = '';

      if (allowedModes.length > 1) {
        paletteSections += '<p class="nc-palette-title">Modo</p>'
          + '<div class="nc-mode-group" role="group" aria-label="Alternar modo claro e escuro">'
          + buildModeButtons(allowedModes, modeDefinitions, 'data-mode-value')
          + '</div>';
      }

      if (allowedModes.indexOf('dark') !== -1 && allowedDarkIntensities.length > 1) {
        paletteSections += '<div class="nc-intensity-wrap" data-dark-intensity-wrap hidden>'
          + '<p class="nc-palette-title">Intensidade (modo escuro)</p>'
          + '<div class="nc-mode-group" role="group" aria-label="Alternar intensidade do modo escuro">'
          + buildModeButtons(allowedDarkIntensities, darkIntensityDefinitions, 'data-dark-intensity-value')
          + '</div>'
          + '</div>';
      }

      if (allowedThemes.length > 1) {
        paletteSections += '<p class="nc-palette-title">Paleta de cores</p>'
          + '<div class="nc-palette-grid">'
          + buildThemeSwatches()
          + '</div>';
      }

      if (paletteSections !== '') {
        paletteMarkup = '<section class="nc-palette" aria-label="Paleta de cores do site">'
          + '<button type="button" class="nc-palette-toggle" data-palette-toggle aria-expanded="false" aria-controls="nc-palette-panel"><span class="nc-palette-toggle-label-full">Personalizar cores</span><span class="nc-palette-toggle-label-mobile">Cores</span></button>'
          + '<div class="nc-palette-panel" id="nc-palette-panel" data-palette-panel hidden>'
          + paletteSections
          + '</div>'
          + '</section>';
      }
    }

    var utilityMarkup =
      '<aside class="nc-utility-stack" data-utility-stack data-scroll-threshold-mobile="110" data-scroll-threshold-desktop="260" aria-label="Ferramentas de interface">'
      + '<button type="button" class="nc-scroll-top" data-scroll-top aria-label="Voltar ao topo" hidden>↑</button>'
      + paletteMarkup
      + '</aside>';

    var footerNode = shell.querySelector('.nc-footer');
    if (footerNode) {
      footerNode.insertAdjacentHTML('beforebegin', utilityMarkup);
      return;
    }

    shell.insertAdjacentHTML('beforeend', utilityMarkup);
  }

  ensurePaletteMarkup();

  var paletteToggle = document.querySelector('[data-palette-toggle]');
  var palettePanel = document.querySelector('[data-palette-panel]');
  var darkIntensityWrap = document.querySelector('[data-dark-intensity-wrap]');
  var utilityStack = document.querySelector('[data-utility-stack]');
  var scrollTopButton = document.querySelector('[data-scroll-top]');
  var footer = document.querySelector('.nc-footer');
  var pointerDragActive = false;
  var pointerDragMoved = false;
  var pointerDragSuppressToggle = false;
  var pointerDragOffsetX = 0;
  var pointerDragOffsetY = 0;

  function getPalettePositionStorageKey() {
    return isDesktop()
      ? desktopPalettePositionStorageKey
      : mobilePalettePositionStorageKey;
  }

  function getStoredPalettePosition() {
    var rawValue = localStorage.getItem(getPalettePositionStorageKey());
    if (!rawValue) {
      return null;
    }

    try {
      var parsed = JSON.parse(rawValue);
      if (!parsed || typeof parsed.x !== 'number' || typeof parsed.y !== 'number') {
        return null;
      }

      return parsed;
    } catch (error) {
      return null;
    }
  }

  function saveStoredPalettePosition(x, y) {
    localStorage.setItem(getPalettePositionStorageKey(), JSON.stringify({ x: x, y: y }));
  }

  function clearInlinePalettePosition() {
    if (!utilityStack) {
      return;
    }

    utilityStack.style.left = '';
    utilityStack.style.top = '';
    utilityStack.style.right = '';
    utilityStack.style.bottom = '';
    utilityStack.style.transform = '';
  }

  function clampPalettePosition(x, y) {
    if (!utilityStack) {
      return { x: 0, y: 0 };
    }

    var viewportPadding = 8;
    var stackRect = utilityStack.getBoundingClientRect();
    var maxX = Math.max(viewportPadding, window.innerWidth - stackRect.width - viewportPadding);
    var maxY = Math.max(viewportPadding, window.innerHeight - stackRect.height - viewportPadding);

    return {
      x: Math.min(Math.max(x, viewportPadding), maxX),
      y: Math.min(Math.max(y, viewportPadding), maxY),
    };
  }

  function applyPalettePosition(position) {
    if (!utilityStack || !position) {
      return;
    }

    var clamped = clampPalettePosition(position.x, position.y);
    utilityStack.style.left = clamped.x + 'px';
    utilityStack.style.top = clamped.y + 'px';
    utilityStack.style.right = 'auto';
    utilityStack.style.bottom = 'auto';
    utilityStack.style.transform = 'none';
  }

  function syncPalettePosition() {
    if (!utilityStack || !paletteToggle) {
      return;
    }

    var storedPosition = getStoredPalettePosition();
    if (!storedPosition) {
      clearInlinePalettePosition();
      return;
    }

    applyPalettePosition(storedPosition);
  }

  function initPaletteDrag() {
    if (!utilityStack || !paletteToggle || !window.PointerEvent) {
      return;
    }

    paletteToggle.addEventListener('pointerdown', function (event) {
      if (event.pointerType === 'mouse' && event.button !== 0) {
        return;
      }

      var stackRect = utilityStack.getBoundingClientRect();
      pointerDragActive = true;
      pointerDragMoved = false;
      pointerDragOffsetX = event.clientX - stackRect.left;
      pointerDragOffsetY = event.clientY - stackRect.top;

      if (paletteToggle.setPointerCapture) {
        try {
          paletteToggle.setPointerCapture(event.pointerId);
        } catch (error) {
        }
      }
    });

    paletteToggle.addEventListener('pointermove', function (event) {
      if (!pointerDragActive) {
        return;
      }

      var nextPosition = clampPalettePosition(
        event.clientX - pointerDragOffsetX,
        event.clientY - pointerDragOffsetY
      );

      var leftValue = Number.parseFloat(utilityStack.style.left || '0');
      var topValue = Number.parseFloat(utilityStack.style.top || '0');
      if (Math.abs(nextPosition.x - leftValue) > 2 || Math.abs(nextPosition.y - topValue) > 2) {
        pointerDragMoved = true;
      }

      applyPalettePosition(nextPosition);
    });

    function endPointerDrag(event) {
      if (!pointerDragActive) {
        return;
      }

      pointerDragActive = false;

      if (pointerDragMoved) {
        var leftValue = Number.parseFloat(utilityStack.style.left || '0');
        var topValue = Number.parseFloat(utilityStack.style.top || '0');
        saveStoredPalettePosition(leftValue, topValue);
        pointerDragSuppressToggle = true;
        window.setTimeout(function () {
          pointerDragSuppressToggle = false;
        }, 180);
      }

      if (paletteToggle.releasePointerCapture) {
        try {
          paletteToggle.releasePointerCapture(event.pointerId);
        } catch (error) {
        }
      }
    }

    paletteToggle.addEventListener('pointerup', endPointerDrag);
    paletteToggle.addEventListener('pointercancel', endPointerDrag);
  }

  function isDesktop() {
    return window.matchMedia('(min-width: 801px)').matches;
  }

  function getBodyDefault(attrName, allowed, fallback) {
    var value = (body && body.getAttribute(attrName) ? body.getAttribute(attrName) : '').toLowerCase();
    return allowed.indexOf(value) !== -1 ? value : fallback;
  }

  function getRootValue(attrName, allowed, fallback) {
    var value = (root && root.getAttribute(attrName) ? root.getAttribute(attrName) : '').toLowerCase();
    return allowed.indexOf(value) !== -1 ? value : fallback;
  }

  function setPanelState(expanded) {
    if (!paletteToggle || !palettePanel) {
      return;
    }

    paletteToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    palettePanel.hidden = !expanded;
  }

  function updateUtilityLift() {
    if (!utilityStack || !footer || !isDesktop()) {
      if (utilityStack) {
        utilityStack.style.setProperty('--utility-lift', '0px');
      }
      return;
    }

    var footerRect = footer.getBoundingClientRect();
    var viewportHeight = window.innerHeight;
    var overlap = Math.max(0, viewportHeight - footerRect.top + 16);
    utilityStack.style.setProperty('--utility-lift', overlap + 'px');
  }

  function updateScrollTopVisibility() {
    var mobileThreshold = Number.parseInt(
      utilityStack && utilityStack.dataset.scrollThresholdMobile
        ? utilityStack.dataset.scrollThresholdMobile
        : '110',
      10
    );
    var desktopThreshold = Number.parseInt(
      utilityStack && utilityStack.dataset.scrollThresholdDesktop
        ? utilityStack.dataset.scrollThresholdDesktop
        : '260',
      10
    );

    if (Number.isNaN(mobileThreshold)) {
      mobileThreshold = 110;
    }

    if (Number.isNaN(desktopThreshold)) {
      desktopThreshold = 260;
    }

    var threshold = isDesktop() ? desktopThreshold : mobileThreshold;
    var isVisible = window.scrollY > threshold;

    if (!scrollTopButton) {
      return;
    }

    scrollTopButton.hidden = !isVisible;

    if (!isVisible) {
      setPanelState(false);
    }
  }

  function applyTheme(theme) {
    if (allowedThemes.indexOf(theme) === -1) {
      return;
    }

    root.setAttribute('data-theme', theme);

    var buttons = document.querySelectorAll('[data-theme-value]');
    buttons.forEach(function (button) {
      var isActive = button.getAttribute('data-theme-value') === theme;
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  function applyMode(mode) {
    if (allowedModes.indexOf(mode) === -1) {
      return;
    }

    root.setAttribute('data-mode', mode);

    var buttons = document.querySelectorAll('[data-mode-value]');
    buttons.forEach(function (button) {
      var isActive = button.getAttribute('data-mode-value') === mode;
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });

    if (darkIntensityWrap) {
      darkIntensityWrap.hidden = mode !== 'dark';
    }
  }

  function applyDarkIntensity(intensity) {
    if (allowedDarkIntensities.indexOf(intensity) === -1) {
      return;
    }

    root.setAttribute('data-dark-intensity', intensity);

    var buttons = document.querySelectorAll('[data-dark-intensity-value]');
    buttons.forEach(function (button) {
      var isActive = button.getAttribute('data-dark-intensity-value') === intensity;
      button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    });
  }

  var defaultTheme = getBodyDefault('data-default-theme', allowedThemes, 'amber');
  var rootTheme = getRootValue('data-theme', allowedThemes, '');
  var savedTheme = themePreferencesEnabled ? (localStorage.getItem(themeStorageKey) || '').toLowerCase() : '';
  var initialTheme = rootTheme || (allowedThemes.indexOf(savedTheme) !== -1 ? savedTheme : defaultTheme);
  applyTheme(initialTheme);

  var defaultMode = getBodyDefault('data-default-mode', allowedModes, 'light');
  var rootMode = getRootValue('data-mode', allowedModes, '');
  var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  var fallbackMode = defaultMode || (prefersDark ? 'dark' : 'light');
  var savedMode = themePreferencesEnabled ? (localStorage.getItem(modeStorageKey) || '').toLowerCase() : '';
  var initialMode = rootMode || (allowedModes.indexOf(savedMode) !== -1 ? savedMode : fallbackMode);
  applyMode(initialMode);

  var defaultDarkIntensity = getBodyDefault('data-default-dark-intensity', allowedDarkIntensities, 'neutral');
  var rootDarkIntensity = getRootValue('data-dark-intensity', allowedDarkIntensities, '');
  var savedIntensity = themePreferencesEnabled ? (localStorage.getItem(darkIntensityStorageKey) || '').toLowerCase() : '';
  var initialIntensity = rootDarkIntensity || (allowedDarkIntensities.indexOf(savedIntensity) !== -1 ? savedIntensity : defaultDarkIntensity);
  applyDarkIntensity(initialIntensity);
  setPanelState(false);
  syncPalettePosition();
  initPaletteDrag();

  if (paletteToggle) {
    paletteToggle.addEventListener('click', function () {
      if (pointerDragSuppressToggle) {
        return;
      }

      var expanded = paletteToggle.getAttribute('aria-expanded') === 'true';
      setPanelState(!expanded);
      syncPalettePosition();
    });
  }

  if (scrollTopButton) {
    scrollTopButton.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  document.addEventListener('click', function (event) {
    var modeTrigger = event.target.closest('[data-mode-value]');
    if (modeTrigger) {
      var selectedMode = modeTrigger.getAttribute('data-mode-value');
      applyMode(selectedMode);
      localStorage.setItem(modeStorageKey, selectedMode);
      return;
    }

    var darkIntensityTrigger = event.target.closest('[data-dark-intensity-value]');
    if (darkIntensityTrigger) {
      var selectedIntensity = darkIntensityTrigger.getAttribute('data-dark-intensity-value');
      applyDarkIntensity(selectedIntensity);
      localStorage.setItem(darkIntensityStorageKey, selectedIntensity);
      return;
    }

    var themeTrigger = event.target.closest('[data-theme-value]');
    if (!themeTrigger) {
      return;
    }

    var selectedTheme = themeTrigger.getAttribute('data-theme-value');
    applyTheme(selectedTheme);
    localStorage.setItem(themeStorageKey, selectedTheme);

    if (!isDesktop()) {
      setPanelState(false);
    }
  });

  document.addEventListener('click', function (event) {
    if (!paletteToggle || !palettePanel) {
      return;
    }

    var expanded = paletteToggle.getAttribute('aria-expanded') === 'true';
    if (!expanded) {
      return;
    }

    if (event.target.closest('.nc-palette')) {
      return;
    }

    setPanelState(false);
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape' || !paletteToggle || !palettePanel) {
      return;
    }

    var expanded = paletteToggle.getAttribute('aria-expanded') === 'true';
    if (!expanded) {
      return;
    }

    setPanelState(false);
    paletteToggle.focus();
  });

  window.addEventListener('resize', function () {
    setPanelState(false);
    updateUtilityLift();
    updateScrollTopVisibility();
    syncPalettePosition();
  });

  window.addEventListener('scroll', function () {
    updateUtilityLift();
    updateScrollTopVisibility();
  }, { passive: true });

  updateUtilityLift();
  updateScrollTopVisibility();
  syncPalettePosition();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () {
    try {
      initCedernTheme();
    } catch (error) {
      console.error('[CEDE] Falha ao iniciar seletor de tema:', error);
    }
  });
} else {
  try {
    initCedernTheme();
  } catch (error) {
    console.error('[CEDE] Falha ao iniciar seletor de tema:', error);
  }
}
