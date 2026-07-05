import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Collapsible sidebar nav groups. Each group remembers its own open/closed
// state in localStorage; untouched groups default to "open only if active".
Alpine.data('navGroup', (key, activeKey = '') => ({
    key,
    open: (() => {
        try {
            const saved = JSON.parse(localStorage.getItem('navGroups') || '{}');
            return key in saved ? !!saved[key] : key === activeKey;
        } catch (e) {
            return key === activeKey;
        }
    })(),
    toggle() {
        this.open = !this.open;
        try {
            const saved = JSON.parse(localStorage.getItem('navGroups') || '{}');
            saved[this.key] = this.open;
            localStorage.setItem('navGroups', JSON.stringify(saved));
        } catch (e) {}
    },
}));

Alpine.start();
