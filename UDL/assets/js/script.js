let lastScrollTop = 0;
const header = document.querySelector('header');
const minScroll = 100; 
let isThrottled = false;

window.addEventListener('scroll', () => {
    if (isThrottled) return;
    isThrottled = true;

    setTimeout(() => {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollDelta = Math.abs(scrollTop - lastScrollTop);
        
       
        if (scrollDelta > 15) {
            if (scrollTop > minScroll) {
               
                if (scrollTop > lastScrollTop) {
                    header.classList.add('header-hidden');
                } 
              
                else {
                    header.classList.remove('header-hidden');
                }
            } else {
             
                header.classList.remove('header-hidden');
            }
            
            lastScrollTop = scrollTop;
        }
        
        isThrottled = false;
    }, 100); 
});
/* Script para el menú de usuario (toggle por click) */
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.user-menu-container');
    const icon = document.querySelector('.user-icon');
    const menu = document.querySelector('.user-menu');
    // No guardamos referencias únicas; seleccionamos en tiempo real para soportar re-render
    const getDropdownMenu = () => document.querySelector('.nav-dropdown .dropdown-menu');
    const accountBtn = document.querySelector('.user-avatar');
    const accountMenu = document.querySelector('.account-menu');
    const welcomeToast = document.getElementById('welcome-toast');
    if (container && icon && menu) {
        icon.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.toggle('open');
        });
        document.addEventListener('click', function() {
            menu.classList.remove('open');
        });
        // Evitar cierre cuando se hace click dentro del menú
        menu.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Delegación global para manejar ambos menús sin interferencias
    document.addEventListener('click', function(e) {
        const clickedDropdownToggle = e.target.closest('.nav-dropdown .dropdown-toggle');
        const clickedAccountBtn = e.target.closest('.user-avatar');

        if (clickedDropdownToggle) {
            e.preventDefault();
            e.stopPropagation();
            if (accountMenu) accountMenu.classList.remove('open');
            const dropdownMenu = getDropdownMenu();
            if (dropdownMenu) dropdownMenu.classList.toggle('open');
            return;
        }

        if (clickedAccountBtn) {
            e.preventDefault();
            e.stopPropagation();
            const dropdownMenu = getDropdownMenu();
            if (dropdownMenu) dropdownMenu.classList.remove('open');
            if (accountMenu) accountMenu.classList.toggle('open');
            return;
        }

        // Cerrar ambos si se hace click fuera
        if (!e.target.closest('.nav-dropdown')) {
            const dropdownMenu = getDropdownMenu();
            if (dropdownMenu) dropdownMenu.classList.remove('open');
        }
        if (!e.target.closest('.account-menu-container')) {
            if (accountMenu) accountMenu.classList.remove('open');
        }
    });
    // Delegar click dentro del menú para no cerrar
    document.addEventListener('click', function(e) {
        if (e.target.closest('.nav-dropdown .dropdown-menu')) {
            e.stopPropagation();
        }
    });

    if (accountBtn && accountMenu) {
        accountBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            accountMenu.classList.toggle('open');
        });
        document.addEventListener('click', function() {
            accountMenu.classList.remove('open');
        });
        accountMenu.addEventListener('click', function(e) { e.stopPropagation(); });
    }

    if (welcomeToast) {
        // Mostrar y ocultar automáticamente
        requestAnimationFrame(() => welcomeToast.classList.add('show'));
        setTimeout(() => {
            welcomeToast.classList.remove('show');
            // Eliminar del DOM después de la animación
            setTimeout(() => welcomeToast.remove(), 300);
        }, 2500);
    }
});

document.addEventListener('DOMContentLoaded', function() {
  var rolEl = document.getElementById('rol');
  var warning = document.getElementById('warning-icon-container');
  if (rolEl && warning) {
    var toggleWarning = function() {
      if (rolEl.value === 'superadmin') {
        warning.style.display = 'inline-block';
      } else {
        warning.style.display = 'none';
      }
    };
    rolEl.addEventListener('change', toggleWarning);
    // Estado inicial
    toggleWarning();
  }
});
