function initCedernTheme() {
  var root = document.documentElement;
  var body = document.body;
  var themeStorageKey = 'natalcode_theme';
  var modeStorageKey = 'natalcode_mode';
  var darkIntensityStorageKey = 'natalcode_dark_intensity';
  var allowedThemes = ['blue', 'red', 'green', 'violet', 'amber'];
  var allowedModes = ['light', 'dark'];
  var allowedDarkIntensities = ['neutral', 'vivid'];
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
