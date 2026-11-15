const navPanel = document.querySelector('[data-nav-panel]');
const navToggles = document.querySelectorAll('[data-nav-toggle]');
const navClosers = document.querySelectorAll('[data-nav-close]');

const setNavOpen = (open) => {
    if (!navPanel) return;
    navPanel.classList.toggle('hidden', !open);
    document.body.classList.toggle('overflow-hidden', open);
    navToggles.forEach((button) => button.setAttribute('aria-expanded', String(open)));
    if (open) {
        navPanel.querySelector('button, a')?.focus();
    }
};

navToggles.forEach((button) => {
    button.addEventListener('click', () => setNavOpen(navPanel?.classList.contains('hidden') ?? false));
});

navClosers.forEach((button) => {
    button.addEventListener('click', () => setNavOpen(false));
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && navPanel && !navPanel.classList.contains('hidden')) {
        setNavOpen(false);
    }
});
