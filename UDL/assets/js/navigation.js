/**
 * Sistema de Navegación Interna UDL
 * Maneja la navegación personalizada y controla el historial del navegador
 */

class UDLNavigation {
    constructor() {
        this.navigationHistory = [];
        this.currentIndex = -1;
        this.isNavigating = false;
        this.sessionActive = true;
        
        this.init();
    }

    init() {
        // Registrar la página actual al cargar
        this.addToHistory(window.location.href, document.title);
        
        // Interceptar navegación del navegador
        this.interceptBrowserNavigation();
        
        // Configurar botones de navegación
        this.setupNavigationButtons();
        
        // Manejar cambios de página
        this.handlePageChanges();
        
        console.log('UDL Navigation System initialized');
    }

    /**
     * Intercepta la navegación del navegador para controlar el comportamiento
     */
    interceptBrowserNavigation() {
        // Prevenir el comportamiento por defecto del botón atrás del navegador
        window.addEventListener('popstate', (event) => {
            if (!this.isNavigating) {
                // Si no es navegación interna, cerrar sesión
                this.handleBrowserBackButton();
            }
        });

        // Agregar entrada al historial para interceptar el botón atrás
        history.pushState({udlNavigation: true, timestamp: Date.now()}, '', window.location.href);
    }

    /**
     * Maneja el botón atrás del navegador cerrando la sesión
     */
    handleBrowserBackButton() {
        if (this.sessionActive) {
            // Mostrar confirmación antes de cerrar sesión
            if (confirm('¿Estás seguro de que deseas cerrar la sesión?')) {
                this.closeSession();
            } else {
                // Restaurar el estado si el usuario cancela
                history.pushState({udlNavigation: true, timestamp: Date.now()}, '', window.location.href);
            }
        }
    }

    /**
     * Cierra la sesión del usuario
     */
    closeSession() {
        this.sessionActive = false;
        
        // Mostrar mensaje de cierre de sesión
        this.showNotification('Cerrando sesión...', 'info');
        
        // Redirigir al logout después de un breve delay
        setTimeout(() => {
            window.location.href = '/UDL/php/logout.php';
        }, 1000);
    }

    /**
     * Agrega una página al historial de navegación interna
     */
    addToHistory(url, title) {
        // Remover entradas futuras si estamos en el medio del historial
        if (this.currentIndex < this.navigationHistory.length - 1) {
            this.navigationHistory = this.navigationHistory.slice(0, this.currentIndex + 1);
        }
        
        // Agregar nueva entrada
        this.navigationHistory.push({
            url: url,
            title: title,
            timestamp: Date.now(),
            scrollPosition: window.pageYOffset
        });
        
        this.currentIndex = this.navigationHistory.length - 1;
        this.updateNavigationButtons();
        
        console.log('Added to navigation history:', url);
    }

    /**
     * Navega hacia atrás en el historial interno
     */
    goBack() {
        if (this.canGoBack()) {
            this.currentIndex--;
            this.navigateToHistoryItem(this.navigationHistory[this.currentIndex]);
            this.updateNavigationButtons();
        }
    }

    /**
     * Navega hacia adelante en el historial interno
     */
    goForward() {
        if (this.canGoForward()) {
            this.currentIndex++;
            this.navigateToHistoryItem(this.navigationHistory[this.currentIndex]);
            this.updateNavigationButtons();
        }
    }

    /**
     * Verifica si se puede navegar hacia atrás
     */
    canGoBack() {
        return this.currentIndex > 0;
    }

    /**
     * Verifica si se puede navegar hacia adelante
     */
    canGoForward() {
        return this.currentIndex < this.navigationHistory.length - 1;
    }

    /**
     * Navega a un elemento específico del historial
     */
    navigateToHistoryItem(historyItem) {
        this.isNavigating = true;
        
        // Mostrar indicador de carga
        this.showLoadingIndicator();
        
        // Navegar a la URL
        window.location.href = historyItem.url;
    }

    /**
     * Configura los botones de navegación
     */
    setupNavigationButtons() {
        document.addEventListener('DOMContentLoaded', () => {
            const backButton = document.getElementById('udl-nav-back');
            const forwardButton = document.getElementById('udl-nav-forward');
            
            if (backButton) {
                backButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.goBack();
                });
            }
            
            if (forwardButton) {
                forwardButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.goForward();
                });
            }
            
            this.updateNavigationButtons();
        });
    }

    /**
     * Actualiza el estado de los botones de navegación
     */
    updateNavigationButtons() {
        const backButton = document.getElementById('udl-nav-back');
        const forwardButton = document.getElementById('udl-nav-forward');
        
        if (backButton) {
            backButton.disabled = !this.canGoBack();
            backButton.classList.toggle('disabled', !this.canGoBack());
        }
        
        if (forwardButton) {
            forwardButton.disabled = !this.canGoForward();
            forwardButton.classList.toggle('disabled', !this.canGoForward());
        }
    }

    /**
     * Maneja cambios de página para actualizar el historial
     */
    handlePageChanges() {
        // Interceptar enlaces internos
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link && this.isInternalLink(link.href)) {
                // Guardar posición de scroll actual
                if (this.navigationHistory[this.currentIndex]) {
                    this.navigationHistory[this.currentIndex].scrollPosition = window.pageYOffset;
                }
            }
        });
    }

    /**
     * Verifica si un enlace es interno a la aplicación
     */
    isInternalLink(url) {
        try {
            const linkUrl = new URL(url);
            const currentUrl = new URL(window.location.href);
            return linkUrl.origin === currentUrl.origin && linkUrl.pathname.includes('/UDL/');
        } catch (e) {
            return false;
        }
    }

    /**
     * Muestra un indicador de carga
     */
    showLoadingIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'udl-loading-indicator';
        indicator.innerHTML = `
            <div class="loading-spinner">
                <div class="spinner"></div>
                <span>Navegando...</span>
            </div>
        `;
        document.body.appendChild(indicator);
        
        // Remover después de 3 segundos como máximo
        setTimeout(() => {
            const loadingIndicator = document.getElementById('udl-loading-indicator');
            if (loadingIndicator) {
                loadingIndicator.remove();
            }
        }, 3000);
    }

    /**
     * Muestra una notificación al usuario
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `udl-notification udl-notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${this.getNotificationIcon(type)}</span>
                <span class="notification-message">${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Mostrar con animación
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Remover después de 3 segundos
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Obtiene el icono para las notificaciones
     */
    getNotificationIcon(type) {
        const icons = {
            info: 'ℹ️',
            success: '✅',
            warning: '⚠️',
            error: '❌'
        };
        return icons[type] || icons.info;
    }

    /**
     * Obtiene información del historial de navegación
     */
    getNavigationInfo() {
        return {
            history: this.navigationHistory,
            currentIndex: this.currentIndex,
            canGoBack: this.canGoBack(),
            canGoForward: this.canGoForward()
        };
    }
}

// Inicializar el sistema de navegación cuando se carga la página
let udlNavigation;

document.addEventListener('DOMContentLoaded', () => {
    udlNavigation = new UDLNavigation();
});

// Exponer funciones globales para uso en otras partes de la aplicación
window.UDLNavigation = {
    goBack: () => udlNavigation?.goBack(),
    goForward: () => udlNavigation?.goForward(),
    getInfo: () => udlNavigation?.getNavigationInfo(),
    addToHistory: (url, title) => udlNavigation?.addToHistory(url, title)
};