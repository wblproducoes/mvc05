/**
 * Sistema Administrativo MVC - JavaScript Principal
 * 
 * @author Sistema Administrativo
 * @version 1.0.0
 */

// Configuração global
window.App = {
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
    baseUrl: window.location.origin,
    
    // Configurações
    config: {
        alertTimeout: 5000,
        ajaxTimeout: 30000
    }
};

// Utilitários
const Utils = {
    /**
     * Faz requisição AJAX
     */
    ajax: function(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            timeout: App.config.ajaxTimeout
        };

        // Adicionar token CSRF para requisições POST/PUT/DELETE
        if (['POST', 'PUT', 'DELETE'].includes(options.method?.toUpperCase())) {
            if (App.csrfToken) {
                defaults.headers['X-CSRF-Token'] = App.csrfToken;
            }
        }

        const config = Object.assign({}, defaults, options);
        
        return fetch(url, config)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            });
    },

    /**
     * Exibe notificação
     */
    notify: function(message, type = 'info', timeout = null) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(alertDiv);

        // Auto-remover após timeout
        const removeTimeout = timeout || App.config.alertTimeout;
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, removeTimeout);

        return alertDiv;
    },

    /**
     * Confirma ação
     */
    confirm: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },

    /**
     * Formata data
     */
    formatDate: function(date, format = 'dd/mm/yyyy') {
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        
        return format
            .replace('dd', day)
            .replace('mm', month)
            .replace('yyyy', year);
    },

    /**
     * Debounce function
     */
    debounce: function(func, wait, immediate) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                timeout = null;
                if (!immediate) func(...args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func(...args);
        };
    },

    /**
     * Valida email
     */
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    /**
     * Gera string aleatória
     */
    randomString: function(length = 10) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }
};

// Componentes
const Components = {
    /**
     * Inicializa sidebar
     */
    initSidebar: function() {
        const sidebarCollapse = document.getElementById('sidebarCollapse');
        const sidebar = document.getElementById('sidebar');
        const content = document.getElementById('content');
        
        if (sidebarCollapse && sidebar) {
            sidebarCollapse.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                if (content) {
                    content.classList.toggle('expanded');
                }
            });
        }

        // Marcar item ativo no menu
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });
    },

    /**
     * Inicializa tooltips
     */
    initTooltips: function() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    },

    /**
     * Inicializa popovers
     */
    initPopovers: function() {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    },

    /**
     * Inicializa confirmações de exclusão
     */
    initDeleteConfirmations: function() {
        document.addEventListener('click', function(e) {
            if (e.target.matches('.btn-delete, .delete-btn')) {
                e.preventDefault();
                
                const message = e.target.getAttribute('data-confirm') || 'Tem certeza que deseja excluir este item?';
                
                Utils.confirm(message, function() {
                    const form = e.target.closest('form');
                    if (form) {
                        form.submit();
                    } else {
                        const href = e.target.getAttribute('href');
                        if (href) {
                            window.location.href = href;
                        }
                    }
                });
            }
        });
    },

    /**
     * Inicializa busca em tempo real
     */
    initLiveSearch: function() {
        const searchInputs = document.querySelectorAll('.live-search');
        
        searchInputs.forEach(input => {
            const debouncedSearch = Utils.debounce(function() {
                const query = input.value.trim();
                const target = input.getAttribute('data-target');
                const url = input.getAttribute('data-url');
                
                if (query.length >= 2 && url && target) {
                    Components.performSearch(url, query, target);
                }
            }, 300);
            
            input.addEventListener('input', debouncedSearch);
        });
    },

    /**
     * Executa busca
     */
    performSearch: function(url, query, target) {
        const targetElement = document.querySelector(target);
        if (!targetElement) return;

        // Mostrar loading
        targetElement.innerHTML = '<div class="text-center p-3"><div class="spinner-border spinner-border-sm"></div></div>';

        Utils.ajax(`${url}?q=${encodeURIComponent(query)}`)
            .then(data => {
                if (data.success && data.data) {
                    let html = '';
                    data.data.forEach(item => {
                        html += `<div class="search-result-item p-2 border-bottom">
                            <strong>${item.name}</strong><br>
                            <small class="text-muted">${item.email}</small>
                        </div>`;
                    });
                    targetElement.innerHTML = html || '<div class="text-center p-3 text-muted">Nenhum resultado encontrado</div>';
                } else {
                    targetElement.innerHTML = '<div class="text-center p-3 text-danger">Erro na busca</div>';
                }
            })
            .catch(error => {
                console.error('Erro na busca:', error);
                targetElement.innerHTML = '<div class="text-center p-3 text-danger">Erro na busca</div>';
            });
    },

    /**
     * Inicializa upload de arquivos
     */
    initFileUpload: function() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const preview = this.getAttribute('data-preview');
                    if (preview) {
                        Components.previewFile(file, preview);
                    }
                }
            });
        });
    },

    /**
     * Preview de arquivo
     */
    previewFile: function(file, previewSelector) {
        const previewElement = document.querySelector(previewSelector);
        if (!previewElement) return;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewElement.innerHTML = `<img src="${e.target.result}" class="img-thumbnail" style="max-width: 200px;">`;
            };
            reader.readAsDataURL(file);
        } else {
            previewElement.innerHTML = `<div class="alert alert-info">Arquivo selecionado: ${file.name}</div>`;
        }
    },

    /**
     * Inicializa validação de formulários
     */
    initFormValidation: function() {
        const forms = document.querySelectorAll('.needs-validation');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    },

    /**
     * Inicializa máscaras de input
     */
    initInputMasks: function() {
        // Máscara de telefone
        const phoneInputs = document.querySelectorAll('input[data-mask="phone"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                    if (value.length < 14) {
                        value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                    }
                }
                this.value = value;
            });
        });

        // Máscara de CPF
        const cpfInputs = document.querySelectorAll('input[data-mask="cpf"]');
        cpfInputs.forEach(input => {
            input.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
                this.value = value;
            });
        });
    }
};

// Inicialização quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes
    Components.initSidebar();
    Components.initTooltips();
    Components.initPopovers();
    Components.initDeleteConfirmations();
    Components.initLiveSearch();
    Components.initFileUpload();
    Components.initFormValidation();
    Components.initInputMasks();

    // Auto-hide alerts após timeout
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert.parentNode) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }
        }, App.config.alertTimeout);
    });

    // Adicionar classe fade-in aos elementos principais
    const mainElements = document.querySelectorAll('.card, .alert');
    mainElements.forEach(element => {
        element.classList.add('fade-in');
    });
});

// Exportar para uso global
window.Utils = Utils;
window.Components = Components;

// Função para toggle de status (usado em várias páginas)
window.toggleStatus = function(url, id) {
    Utils.ajax(url, {
        method: 'POST',
        body: JSON.stringify({ id: id }),
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': App.csrfToken
        }
    })
    .then(data => {
        if (data.success) {
            Utils.notify(data.message, 'success');
            // Recarregar página após 1 segundo
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            Utils.notify(data.message || 'Erro ao alterar status', 'danger');
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        Utils.notify('Erro ao alterar status', 'danger');
    });
};

// Função para confirmar e executar ação
window.confirmAction = function(message, callback) {
    Utils.confirm(message, callback);
};