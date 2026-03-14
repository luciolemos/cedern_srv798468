function initCedernTheme() {
  var root = document.documentElement;
  var body = document.body;
  var themeStorageKey = 'natalcode_theme';
  var modeStorageKey = 'natalcode_mode';
  var darkIntensityStorageKey = 'natalcode_dark_intensity';
  var allowedThemes = ['blue', 'red', 'green', 'violet', 'amber'];
  var allowedModes = ['light', 'dark'];
  var allowedDarkIntensities = ['neutral', 'vivid'];

  function ensurePaletteMarkup() {
    if (document.querySelector('[data-utility-stack]')) {
      return;
    }

    var shell = document.querySelector('.nc-shell') || document.body;
    if (!shell) {
      return;
    }

    shell.insertAdjacentHTML('beforeend',
      '<aside class="nc-utility-stack" data-utility-stack data-scroll-threshold-mobile="110" data-scroll-threshold-desktop="260" aria-label="Ferramentas de interface">'
      + '<button type="button" class="nc-scroll-top" data-scroll-top aria-label="Voltar ao topo" hidden>↑</button>'
      + '<section class="nc-palette" aria-label="Paleta de cores do site">'
      + '<button type="button" class="nc-palette-toggle" data-palette-toggle aria-expanded="false" aria-controls="nc-palette-panel">Personalizar cores</button>'
      + '<div class="nc-palette-panel" id="nc-palette-panel" data-palette-panel hidden>'
      + '<p class="nc-palette-title">Modo</p>'
      + '<div class="nc-mode-group" role="group" aria-label="Alternar modo claro e escuro">'
      + '<button type="button" class="nc-mode-btn" data-mode-value="light" aria-pressed="false" aria-label="Ativar modo claro">Light</button>'
      + '<button type="button" class="nc-mode-btn" data-mode-value="dark" aria-pressed="false" aria-label="Ativar modo escuro">Dark</button>'
      + '</div>'
      + '<p class="nc-palette-title">Paleta de cores</p>'
      + '<div class="nc-palette-grid">'
      + '<button type="button" class="nc-swatch" data-theme-value="blue" aria-pressed="false" aria-label="Ativar tema azul"><span class="nc-swatch-dot nc-dot-blue"></span><span class="nc-swatch-label">Blue</span></button>'
      + '<button type="button" class="nc-swatch" data-theme-value="red" aria-pressed="false" aria-label="Ativar tema vermelho"><span class="nc-swatch-dot nc-dot-red"></span><span class="nc-swatch-label">Red</span></button>'
      + '<button type="button" class="nc-swatch" data-theme-value="green" aria-pressed="false" aria-label="Ativar tema verde"><span class="nc-swatch-dot nc-dot-green"></span><span class="nc-swatch-label">Green</span></button>'
      + '<button type="button" class="nc-swatch" data-theme-value="violet" aria-pressed="false" aria-label="Ativar tema violeta"><span class="nc-swatch-dot nc-dot-violet"></span><span class="nc-swatch-label">Violet</span></button>'
      + '<button type="button" class="nc-swatch" data-theme-value="amber" aria-pressed="false" aria-label="Ativar tema ambar"><span class="nc-swatch-dot nc-dot-amber"></span><span class="nc-swatch-label">Amber</span></button>'
      + '</div>'
      + '</div>'
      + '</section>'
      + '</aside>'
    );
  }

  ensurePaletteMarkup();

  var paletteToggle = document.querySelector('[data-palette-toggle]');
  var palettePanel = document.querySelector('[data-palette-panel]');
  var darkIntensityWrap = document.querySelector('[data-dark-intensity-wrap]');
  var utilityStack = document.querySelector('[data-utility-stack]');
  var scrollTopButton = document.querySelector('[data-scroll-top]');
  var footer = document.querySelector('.nc-footer');

  function isDesktop() {
    return window.matchMedia('(min-width: 801px)').matches;
  }

  function getBodyDefault(attrName, allowed, fallback) {
    var value = (body && body.getAttribute(attrName) ? body.getAttribute(attrName) : '').toLowerCase();
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
  var savedTheme = localStorage.getItem(themeStorageKey);
  var initialTheme = allowedThemes.indexOf(savedTheme) !== -1 ? savedTheme : defaultTheme;
  applyTheme(initialTheme);

  var defaultMode = getBodyDefault('data-default-mode', allowedModes, 'light');
  var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  var fallbackMode = defaultMode || (prefersDark ? 'dark' : 'light');
  var savedMode = localStorage.getItem(modeStorageKey);
  var initialMode = allowedModes.indexOf(savedMode) !== -1 ? savedMode : fallbackMode;
  applyMode(initialMode);

  var defaultDarkIntensity = getBodyDefault('data-default-dark-intensity', allowedDarkIntensities, 'neutral');
  var savedIntensity = localStorage.getItem(darkIntensityStorageKey);
  var initialIntensity = allowedDarkIntensities.indexOf(savedIntensity) !== -1 ? savedIntensity : defaultDarkIntensity;
  applyDarkIntensity(initialIntensity);
  setPanelState(false);

  if (paletteToggle) {
    paletteToggle.addEventListener('click', function () {
      var expanded = paletteToggle.getAttribute('aria-expanded') === 'true';
      setPanelState(!expanded);
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

  window.addEventListener('resize', function () {
    setPanelState(false);
    updateUtilityLift();
    updateScrollTopVisibility();
  });

  window.addEventListener('scroll', function () {
    updateUtilityLift();
    updateScrollTopVisibility();
  }, { passive: true });

  updateUtilityLift();
  updateScrollTopVisibility();
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
