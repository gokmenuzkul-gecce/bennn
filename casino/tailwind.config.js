/** @type {import('tailwindcss').Config} */
module.exports = {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './app/**/*.php',
    ],
    theme: {
        extend: {
            colors: {
                background: '#0b0e14',
                surface: '#121622',
                'surface-card': '#161c2b',
                'surface-elevated': '#1c2438',
                'surface-border': 'rgba(255, 255, 255, 0.08)',
                primary: {
                    DEFAULT: '#10b981',
                    dark: '#059669',
                    light: '#34d399',
                    50: '#ecfdf5',
                    100: '#d1fae5',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                },
                secondary: {
                    DEFAULT: '#06b6d4',
                    dark: '#0891b2',
                    light: '#22d3ee',
                },
                accent: {
                    gold: '#f59e0b',
                    amber: '#d97706',
                    rose: '#f43f5e',
                    purple: '#a855f7',
                },
                'on-surface': '#f8fafc',
                'on-surface-muted': '#94a3b8',
                'on-surface-subtle': '#64748b',
            },
            spacing: {
                gutter: '16px',
                margin: '24px',
                'sidebar-width': '260px',
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
        require('@tailwindcss/container-queries'),
    ],
};
