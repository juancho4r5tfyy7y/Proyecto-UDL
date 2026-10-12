document.addEventListener('DOMContentLoaded', function() {
    // Toggle sidebar en móvil
    const mobileToggle = document.getElementById('mobileToggle');
    if (mobileToggle) {
        mobileToggle.addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        });
    }

    // Cerrar sidebar al hacer clic fuera en móvil
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const mobileToggle = document.getElementById('mobileToggle');
        
        if (window.innerWidth <= 768 && sidebar && mobileToggle &&
            !sidebar.contains(event.target) && 
            !mobileToggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    });

    // Animaciones de entrada para tarjetas
    const cards = document.querySelectorAll('.stat-card, .activity-item, .action-card');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    cards.forEach(card => {
        observer.observe(card);
    });

    // Animación de números en estadísticas
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(number => {
        const target = parseFloat(number.textContent);
        if (isNaN(target)) return;

        let current = 0;
        const increment = target / 100;
        
        const updateNumber = () => {
            current += increment;
            if (current >= target) {
                // Formatear para que coincida con el original (ej. 0.0 o entero)
                number.textContent = number.textContent.includes('.') ? target.toFixed(1) : Math.floor(target);
            } else {
                number.textContent = number.textContent.includes('.') ? current.toFixed(1) : Math.floor(current);
                requestAnimationFrame(updateNumber);
            }
        };
        requestAnimationFrame(updateNumber);
    });

    // Lógica para mantener el estado activo del menú
    const currentLocation = window.location.href;
    const menuLinks = document.querySelectorAll('.nav-menu a');

    menuLinks.forEach(link => {
        if (link.href === currentLocation) {
            // Quitar 'active' de todos los items
            menuLinks.forEach(l => l.parentElement.classList.remove('active'));
            // Añadir 'active' al item actual
            link.parentElement.classList.add('active');
        }
    });
});
