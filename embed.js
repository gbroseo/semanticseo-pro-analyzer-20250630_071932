var embeds = [];
  var iframeMap = new WeakMap();
  var originMap = new WeakMap();
  var isResizeListenerAttached = false;
  var isMessageListenerAttached = false;

  function isElement(obj) {
    return obj instanceof Element || (obj && obj.nodeType === 1 && typeof obj.nodeName === 'string');
  }

  function isNodeList(obj) {
    var toString = Object.prototype.toString;
    return toString.call(obj) === '[object NodeList]' || toString.call(obj) === '[object HTMLCollection]';
  }

  function isArrayLike(obj) {
    return obj && typeof obj === 'object' && 'length' in obj && obj.length >= 0 && (obj.length === 0 || isElement(obj[0]));
  }

  function initEmbed(selector) {
    if (!selector) {
      console.error('[SemanticSEO Embed] initEmbed: selector is required.');
      return;
    }
    var containers = [];
    if (typeof selector === 'string') {
      containers = Array.prototype.slice.call(document.querySelectorAll(selector));
    } else if (isElement(selector)) {
      containers = [selector];
    } else if (isNodeList(selector) || Array.isArray(selector) || isArrayLike(selector)) {
      containers = Array.prototype.slice.call(selector);
    } else {
      console.error('[SemanticSEO Embed] initEmbed: Invalid selector:', selector);
      return;
    }
    containers.forEach(function(container) {
      if (!container) return;
      if (embeds.some(function(e) { return e.container === container; })) return;
      var iframe = loadDashboard(container);
      if (iframe) {
        embeds.push({ container: container, iframe: iframe });
      }
    });
    if (!isResizeListenerAttached) {
      attachResizeListener();
      isResizeListenerAttached = true;
    }
    handleResize();
  }

  function loadDashboard(container) {
    var embedUrl = container.getAttribute('data-embed-url');
    if (!embedUrl) {
      console.error('[SemanticSEO Embed] loadDashboard: data-embed-url attribute is required on container.');
      return null;
    }
    var existingIframes = container.querySelectorAll('iframe');
    Array.prototype.forEach.call(existingIframes, function(el) {
      if (el && el.parentNode === container) {
        container.removeChild(el);
      }
    });
    var style = window.getComputedStyle(container);
    if (style.position === 'static' || !style.position) {
      container.style.position = 'relative';
    }
    container.style.overflow = 'hidden';
    var iframe = document.createElement('iframe');
    iframe.src = embedUrl;
    iframe.style.border = '0';
    iframe.style.width = '100%';
    iframe.style.display = 'block';
    iframe.setAttribute('scrolling', 'no');
    container.appendChild(iframe);
    iframeMap.set(iframe.contentWindow, iframe);
    try {
      var parsedUrl = new URL(embedUrl, window.location.href);
      originMap.set(iframe.contentWindow, parsedUrl.origin);
    } catch (e) {
      console.error('[SemanticSEO Embed] loadDashboard: Invalid URL:', embedUrl);
    }
    if (!isMessageListenerAttached) {
      window.addEventListener('message', messageHandler, false);
      isMessageListenerAttached = true;
    }
    return iframe;
  }

  function messageHandler(event) {
    var iframe = iframeMap.get(event.source);
    var expectedOrigin = originMap.get(event.source);
    if (!iframe || !expectedOrigin || event.origin !== expectedOrigin) {
      return;
    }
    var data = event.data;
    if (data && typeof data.height === 'number') {
      iframe.style.height = data.height + 'px';
    }
  }

  function handleResize() {
    embeds.forEach(function(item) {
      var container = item.container;
      var iframe = item.iframe;
      var width = container.clientWidth;
      if (width) {
        var height = Math.round(width * 0.75);
        iframe.style.height = height + 'px';
      }
    });
  }

  function attachResizeListener() {
    var timeoutId;
    function onResize() {
      clearTimeout(timeoutId);
      timeoutId = setTimeout(handleResize, 150);
    }
    window.addEventListener('resize', onResize);
    window.addEventListener('orientationchange', onResize);
  }

  window.SemanticSEOProEmbed = {
    initEmbed: initEmbed
  };
})(window, document);