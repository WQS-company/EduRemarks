/**
 * EduRemarks Main JS Polish
 * Handles Scroll Reveals and Micro-interactions
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Scroll Reveal Observer
    const revealCallback = (entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('active');
                // Optional: stop observing once revealed
                // observer.unobserve(entry.target);
            }
        });
    };

    const revealObserver = new IntersectionObserver(revealCallback, {
        threshold: 0.15,
        rootMargin: '0px 0px -50px 0px'
    });

    document.querySelectorAll('.reveal').forEach(el => {
        revealObserver.observe(el);
    });

    // 2. Button Micro-interactions (Organic scale)
    const btns = document.querySelectorAll('.btn');
    btns.forEach(btn => {
        btn.addEventListener('mousedown', () => {
            btn.style.transform = 'scale(0.96)';
        });
        btn.addEventListener('mouseup', () => {
            btn.style.transform = '';
        });
    });

    // 3. Dynamic Navbar Transparency
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('navbar-scrolled');
            navbar.style.padding = '0.8rem 0';
        } else {
            navbar.classList.remove('navbar-scrolled');
            navbar.style.padding = '1.2rem 0';
        }
    });

    // 4. Mobile Navbar Auto-Close on Outside Click
    document.addEventListener('click', (event) => {
        const navbarCollapse = document.getElementById('navbarNav');
        const navbarToggler = document.querySelector('.navbar-toggler');
        const isNavbarOpen = navbarCollapse.classList.contains('show');
        const clickedInsideNavbar = navbarCollapse.contains(event.target) || navbarToggler.contains(event.target);

        if (isNavbarOpen && !clickedInsideNavbar) {
            // Trigger Bootstrap's collapse method via the toggler's click or native BS API
            // Using toggler click for simplest cross-version compatibility
            navbarToggler.click();
        }
    });
});
