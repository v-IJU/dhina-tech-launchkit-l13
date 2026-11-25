// Public website scripts: mobile menu, FAQ accordion and home slider.

// Module scripts: every app/Modules/*/Resources/js/web/*.js is included automatically.
import.meta.glob('../../app/Modules/*/Resources/js/web/*.js', { eager: true });

document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-menu-toggle]');
    if (toggle) {
        const menu = document.getElementById(toggle.getAttribute('aria-controls'));
        const open = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!open));
        menu.classList.toggle('hidden', open);
    }
});

// Simple auto-advancing slider: <div data-slider> with children [data-slide].
document.querySelectorAll('[data-slider]').forEach((slider) => {
    const slides = [...slider.querySelectorAll('[data-slide]')];
    const dots = [...slider.querySelectorAll('[data-slide-dot]')];
    if (slides.length < 2) return;
    let index = 0;
    const show = (i) => {
        index = (i + slides.length) % slides.length;
        slides.forEach((s, n) => s.classList.toggle('hidden', n !== index));
        dots.forEach((d, n) => d.setAttribute('aria-current', String(n === index)));
    };
    dots.forEach((d, n) => d.addEventListener('click', () => show(n)));
    let timer = setInterval(() => show(index + 1), 6000);
    slider.addEventListener('mouseenter', () => clearInterval(timer));
    slider.addEventListener('mouseleave', () => { timer = setInterval(() => show(index + 1), 6000); });
    show(0);
});
