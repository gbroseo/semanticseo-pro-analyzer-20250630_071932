const App = {
    initApp() {
      this.rootElement = document.getElementById('app') || document.body;
      this.setupEventListeners();
    },

    setupEventListeners() {
      this.rootElement.addEventListener('submit', event => {
        const form = event.target.closest('.js-ajax-form');
        if (!form) return;
        event.preventDefault();
        this.handleFormSubmit(form);
      });

      this.rootElement.addEventListener('click', event => {
        const btn = event.target.closest('[data-action="export"]');
        if (!btn) return;
        event.preventDefault();
        this.handleExport(btn);
      });
    },

    async fetchData(endpoint, options = {}) {
      this.showLoader();
      try {
        const response = await fetch(endpoint, options);
        if (!response.ok) {
          const errorText = await response.text();
          throw new Error(`HTTP ${response.status} ${response.statusText}: ${errorText}`);
        }
        const contentType = response.headers.get('Content-Type') || '';
        if (contentType.includes('application/json')) {
          return await response.json();
        }
        return await response.text();
      } catch (error) {
        this.showError(error.message);
        throw error;
      } finally {
        this.hideLoader();
      }
    },

    handleFormSubmit(form) {
      const endpoint = form.dataset.endpoint || form.action;
      const targetSelector = form.dataset.target;
      if (!endpoint) {
        this.showError('Endpoint not specified');
        return;
      }
      const options = {
        method: (form.method || 'POST').toUpperCase(),
        credentials: 'same-origin',
        body: new FormData(form)
      };
      this.fetchData(endpoint, options)
        .then(data => this.render(targetSelector, data))
        .catch(error => {
          console.error(error);
          this.showError(error.message);
        });
    },

    handleExport(button) {
      const endpoint = button.dataset.endpoint;
      const filename = button.dataset.filename || 'export.csv';
      if (!endpoint) {
        this.showError('Export endpoint not specified');
        return;
      }
      this.fetchData(endpoint, { method: 'GET', credentials: 'same-origin' })
        .then(data => this.downloadFile(data, filename))
        .catch(error => {
          console.error(error);
          this.showError(error.message);
        });
    },

    render(selector, data) {
      if (!selector) return;
      const container = document.querySelector(selector);
      if (!container) return;
      if (typeof data === 'string') {
        container.innerHTML = data;
      } else if (data.html) {
        container.innerHTML = this.sanitizeHTML(data.html);
      } else if (Array.isArray(data)) {
        container.innerHTML = data
          .map(item => `<div>${this.escapeHtml(JSON.stringify(item))}</div>`)
          .join('');
      } else {
        container.textContent = JSON.stringify(data, null, 2);
      }
    },

    downloadFile(content, filename) {
      const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      const url = URL.createObjectURL(blob);
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    },

    showLoader() {
      document.body.classList.add('is-loading');
    },

    hideLoader() {
      document.body.classList.remove('is-loading');
    },

    showError(message) {
      let el = document.querySelector('.js-error-message');
      if (!el) {
        el = document.createElement('div');
        el.className = 'error-message js-error-message';
        document.body.prepend(el);
      }
      el.textContent = message;
      setTimeout(() => {
        if (el.textContent === message) {
          el.textContent = '';
        }
      }, 5000);
    },

    escapeHtml(str) {
      return str.replace(/[&<>"'`=\/]/g, s => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
        '/': '&#x2F;',
        '`': '&#x60;',
        '=': '&#x3D;'
      }[s]));
    },

    sanitizeHTML(html) {
      const template = document.createElement('template');
      template.innerHTML = html;
      const elements = template.content.querySelectorAll('*');
      elements.forEach(el => {
        Array.from(el.attributes).forEach(attr => {
          if (/^on/i.test(attr.name) || (['href', 'src'].includes(attr.name) && /javascript:/i.test(attr.value))) {
            el.removeAttribute(attr.name);
          }
        });
      });
      template.content.querySelectorAll('script,style').forEach(node => node.remove());
      return template.innerHTML;
    }
  };

  document.addEventListener('DOMContentLoaded', () => App.initApp());
})();