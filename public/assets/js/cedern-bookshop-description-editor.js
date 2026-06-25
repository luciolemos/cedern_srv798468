(() => {
  "use strict";

  const MAX_CHARS = 5000;
  const EMPTY_BLOCK_HTML = "<p><br></p>";

  const commands = [
    { command: "bold", label: "B", title: "Negrito" },
    { command: "italic", label: "I", title: "Italico" },
    { command: "underline", label: "U", title: "Sublinhado" },
    { command: "insertUnorderedList", label: "-", title: "Lista" },
    { command: "insertOrderedList", label: "1.", title: "Lista numerada" },
    { command: "formatBlock", label: ">", title: "Citacao", value: "blockquote" },
    { command: "createLink", label: "Link", title: "Link", needsUrl: true },
    { command: "removeFormat", label: "Tx", title: "Limpar formatacao" },
  ];

  const allowedTags = new Set([
    "A",
    "B",
    "BLOCKQUOTE",
    "BR",
    "DIV",
    "EM",
    "I",
    "LI",
    "OL",
    "P",
    "SPAN",
    "STRONG",
    "U",
    "UL",
  ]);

  const allowedInlineTags = new Set(["A", "B", "BR", "EM", "I", "SPAN", "STRONG", "U"]);
  const removeWithContentTags = new Set([
    "BASE",
    "BUTTON",
    "EMBED",
    "FORM",
    "IFRAME",
    "INPUT",
    "LINK",
    "MATH",
    "META",
    "OBJECT",
    "SCRIPT",
    "SELECT",
    "STYLE",
    "SVG",
    "TEXTAREA",
  ]);

  const normalizeHref = (href) => {
    const value = String(href || "").trim();

    if (!value || value.startsWith("//")) {
      return "";
    }

    const compact = value.replace(/[\u0000-\u0020\u007f]+/g, "").toLowerCase();
    const schemeMatch = compact.match(/^([a-z][a-z0-9+.-]*):/);

    if (!schemeMatch) {
      return value;
    }

    return ["http", "https", "mailto", "tel"].includes(schemeMatch[1]) ? value : "";
  };

  const sanitizeNode = (node) => {
    Array.from(node.childNodes).forEach((child) => {
      if (child.nodeType === Node.TEXT_NODE) {
        return;
      }

      if (child.nodeType !== Node.ELEMENT_NODE) {
        child.remove();
        return;
      }

      const tagName = child.tagName;

      if (removeWithContentTags.has(tagName)) {
        child.remove();
        return;
      }

      if (!allowedTags.has(tagName)) {
        const fragment = document.createDocumentFragment();
        while (child.firstChild) {
          fragment.appendChild(child.firstChild);
        }
        child.replaceWith(fragment);
        sanitizeNode(node);
        return;
      }

      const href = tagName === "A" ? normalizeHref(child.getAttribute("href")) : "";

      Array.from(child.attributes).forEach((attribute) => {
        child.removeAttribute(attribute.name);
      });

      if (tagName === "A" && href) {
        child.setAttribute("href", href);
        child.setAttribute("rel", "nofollow noopener noreferrer");
      }

      if (tagName === "DIV") {
        const paragraph = document.createElement("p");
        while (child.firstChild) {
          paragraph.appendChild(child.firstChild);
        }
        child.replaceWith(paragraph);
        sanitizeNode(paragraph);
        return;
      }

      if (tagName === "SPAN" && child.attributes.length === 0) {
        const fragment = document.createDocumentFragment();
        while (child.firstChild) {
          fragment.appendChild(child.firstChild);
        }
        child.replaceWith(fragment);
        return;
      }

      sanitizeNode(child);
    });
  };

  const sanitizeHtml = (html) => {
    const template = document.createElement("template");
    template.innerHTML = html || "";
    sanitizeNode(template.content);

    return template.innerHTML.trim();
  };

  const normalizeEditorHtml = (html) => {
    const sanitized = sanitizeHtml(html);

    if (!sanitized || sanitized === EMPTY_BLOCK_HTML) {
      return "";
    }

    return sanitized;
  };

  const createButton = ({ command, label, title, value, needsUrl }, editor, sync) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "nc-admin-form-wysiwyg-button";
    button.textContent = label;
    button.title = title;
    button.setAttribute("aria-label", title);

    button.addEventListener("mousedown", (event) => {
      event.preventDefault();
    });

    button.addEventListener("click", () => {
      editor.focus();
      ensureSelectionInside(editor);

      if (needsUrl) {
        const href = normalizeHref(window.prompt("URL do link", "https://") || "");
        if (!href) {
          return;
        }

        document.execCommand(command, false, href);
      } else {
        document.execCommand(command, false, value || null);
      }

      editor.focus();
      ensureSelectionInside(editor);
      sync();
    });

    return button;
  };

  const ensureSelectionInside = (editor) => {
    const selection = window.getSelection();

    if (
      selection &&
      selection.rangeCount > 0 &&
      editor.contains(selection.getRangeAt(0).commonAncestorContainer)
    ) {
      return;
    }

    const range = document.createRange();
    range.selectNodeContents(editor);
    range.collapse(false);

    if (selection) {
      selection.removeAllRanges();
      selection.addRange(range);
    }
  };

  const wrapInlineContent = (editor) => {
    const hasBlock = Array.from(editor.childNodes).some((child) => {
      return child.nodeType === Node.ELEMENT_NODE && !allowedInlineTags.has(child.tagName);
    });

    if (hasBlock || !editor.innerHTML.trim()) {
      return;
    }

    editor.innerHTML = `<p>${editor.innerHTML}</p>`;
  };

  const initializeDescriptionEditor = () => {
    const editor = document.getElementById("description-editor");
    const hiddenInput = document.getElementById("description");
    const charCountDisplay = document.querySelector(".nc-char-count-display");

    if (!editor || !hiddenInput) {
      return;
    }

    const form = hiddenInput.closest("form");
    const toolbar = document.createElement("div");
    toolbar.className = "nc-admin-form-wysiwyg-toolbar";
    toolbar.setAttribute("aria-label", "Ferramentas da sinopse");

    commands.forEach((commandConfig) => {
      toolbar.appendChild(createButton(commandConfig, editor, sync));
    });

    editor.parentNode.insertBefore(toolbar, editor);
    editor.contentEditable = "true";
    editor.setAttribute("role", "textbox");
    editor.setAttribute("aria-multiline", "true");
    editor.setAttribute("data-placeholder", editor.dataset.placeholder || "");
    editor.innerHTML = normalizeEditorHtml(editor.dataset.initialContent || hiddenInput.value || "");
    wrapInlineContent(editor);

    function sync() {
      const html = normalizeEditorHtml(editor.innerHTML);
      const text = editor.textContent || "";
      const charCount = text.length;

      hiddenInput.value = html;

      if (charCountDisplay) {
        charCountDisplay.textContent = String(Math.min(charCount, MAX_CHARS));
      }

      editor.classList.toggle("nc-admin-form-wysiwyg-editor-error", charCount > MAX_CHARS);
      if (charCountDisplay) {
        charCountDisplay.classList.toggle("is-error", charCount > MAX_CHARS);
      }
    }

    editor.addEventListener("input", sync);
    editor.addEventListener("blur", sync);
    editor.addEventListener("paste", (event) => {
      event.preventDefault();

      const clipboard = event.clipboardData || window.clipboardData;
      const html = clipboard ? clipboard.getData("text/html") : "";
      const text = clipboard ? clipboard.getData("text/plain") : "";
      const content = html ? sanitizeHtml(html) : text.replace(/\n{2,}/g, "</p><p>").replace(/\n/g, "<br>");

      document.execCommand("insertHTML", false, content);
      sync();
    });

    if (form) {
      form.addEventListener("submit", sync);
    }

    sync();
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeDescriptionEditor);
  } else {
    initializeDescriptionEditor();
  }
})();
