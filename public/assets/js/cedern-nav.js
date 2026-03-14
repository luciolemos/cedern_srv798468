(() => {
  const header = document.querySelector(".nc-header");
  const toggle = document.querySelector(".nc-nav-toggle");
  const nav = document.getElementById("nc-nav-menu");
  if (!header || !toggle || !nav) {
    return;
  }

  const desktopQuery = window.matchMedia("(min-width: 801px)");

  const isMenuOpen = () => toggle.getAttribute("aria-expanded") === "true";

  const setState = (open) => {
    const isDesktop = desktopQuery.matches;
    const expanded = isDesktop ? true : open;

    toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    toggle.setAttribute("aria-label", expanded ? "Fechar menu" : "Abrir menu");
    
    // Toggle hidden attribute
    nav.hidden = isDesktop ? false : !expanded;
    
    // Toggle class for CSS display
    if (expanded && !isDesktop) {
      nav.classList.add("is-open");
    } else {
      nav.classList.remove("is-open");
    }
  };

  const closeMenu = () => {
    if (!desktopQuery.matches) {
      // Small delay to allow click event to propagate
      setTimeout(() => setState(false), 50);
    }
  };

  const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  const getAnchorOffset = () => {
    const cssOffset = Number.parseFloat(
      window.getComputedStyle(document.documentElement).getPropertyValue("--anchor-offset")
    );
    const fallbackOffset = header.getBoundingClientRect().height + 16;

    if (Number.isFinite(cssOffset) && cssOffset >= 0) {
      return cssOffset;
    }

    return fallbackOffset;
  };

  const getHashTarget = (hash) => {
    if (!hash || hash === "#" || !hash.startsWith("#")) {
      return null;
    }

    const encodedId = hash.slice(1);
    if (!encodedId) {
      return null;
    }

    const decodedId = (() => {
      try {
        return window.decodeURIComponent(encodedId);
      } catch (error) {
        return encodedId;
      }
    })();

    return document.getElementById(decodedId);
  };

  const getScrollTopForTarget = (target) => {
    let targetTop = 0;
    let current = target;

    while (current) {
      targetTop += current.offsetTop;
      current = current.offsetParent;
    }

    return Math.max(0, targetTop - getAnchorOffset());
  };

  const scrollToHash = (hash, smooth = true) => {
    const target = getHashTarget(hash);
    if (!target) {
      return;
    }

    const useSmooth = smooth && !prefersReducedMotion.matches;
    window.scrollTo({
      top: getScrollTopForTarget(target),
      behavior: useSmooth ? "smooth" : "auto",
    });
  };

  toggle.addEventListener("click", () => {
    setState(!isMenuOpen());
  });

  nav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", (event) => {
      const href = link.getAttribute("href") || "";
      const isHashLink = href.startsWith("#") || href.startsWith("/#");
      const hash = href.startsWith("/#") ? href.slice(1) : href;

      if (isHashLink && window.location.pathname === "/") {
        event.preventDefault();
        if (window.location.hash !== hash) {
          window.history.pushState(null, "", hash);
        }
        closeMenu();
        window.setTimeout(() => scrollToHash(hash, true), 0);
        return;
      }

      closeMenu();
    });
  });

  window.addEventListener("hashchange", () => {
    if (window.location.pathname === "/" && window.location.hash) {
      window.setTimeout(() => scrollToHash(window.location.hash, false), 0);
    }
  });

  window.addEventListener(
    "resize",
    () => {
      if (window.location.pathname === "/" && window.location.hash) {
        window.setTimeout(() => scrollToHash(window.location.hash, false), 0);
      }
    },
    { passive: true }
  );

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeMenu();
    }
  });

  document.addEventListener("pointerdown", (event) => {
    if (desktopQuery.matches || !isMenuOpen()) {
      return;
    }

    const target = event.target;
    if (target instanceof Node && !header.contains(target)) {
      closeMenu();
    }
  });

  window.addEventListener(
    "scroll",
    () => {
      if (!desktopQuery.matches && isMenuOpen()) {
        closeMenu();
      }
    },
    { passive: true }
  );

  const syncState = () => setState(false);
  if (typeof desktopQuery.addEventListener === "function") {
    desktopQuery.addEventListener("change", syncState);
  } else if (typeof desktopQuery.addListener === "function") {
    desktopQuery.addListener(syncState);
  }

  syncState();

  if (window.location.pathname === "/" && window.location.hash) {
    window.setTimeout(() => scrollToHash(window.location.hash, false), 0);
  }
})();
