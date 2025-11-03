// script.js - BrewFinder JavaScript functionality

class BrewFinderApp {
    constructor() {
        this.init();
    }

    init() {
        this.setupNavigation();
        this.setupEventListeners();
        this.setupSmoothScrolling();
        this.setupFormHandling();
        this.setupAnimations();
        console.log('🍺 BrewFinder initialized!');
    }

    setupNavigation() {
        this.setupMobileMenu();
        this.setupScrollEffects();
        this.setupThemeToggle();
    }

    setupMobileMenu() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const navMenu = document.querySelector('.nav-menu');
        const mobileNav = document.querySelector('.mobile-nav');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                mobileMenuBtn.classList.toggle('active');
                
                if (mobileNav) {
                    mobileNav.classList.toggle('active');
                } else if (navMenu) {
                    navMenu.classList.toggle('active');
                }
            });
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', (e) => {
            if (mobileNav && !mobileNav.contains(e.target) && !mobileMenuBtn.contains(e.target)) {
                mobileNav.classList.remove('active');
                mobileMenuBtn.classList.remove('active');
            }
        });

        // Close mobile menu when clicking on a link
        document.querySelectorAll('.mobile-nav-link, .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (mobileNav) {
                    mobileNav.classList.remove('active');
                }
                if (mobileMenuBtn) {
                    mobileMenuBtn.classList.remove('active');
                }
            });
        });
    }

    setupScrollEffects() {
        const header = document.querySelector('.header');
        
        window.addEventListener('scroll', this.throttle(() => {
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        }, 10));
    }

    setupThemeToggle() {
        const themeToggle = document.getElementById('themeToggle');
        const themeOptions = document.getElementById('themeOptions');
        const currentTheme = localStorage.getItem('theme') || 'auto';

        // Apply saved theme
        this.applyTheme(currentTheme);

        if (themeToggle) {
            themeToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                themeOptions.classList.toggle('show');
            });
        }

        // Close theme options when clicking outside
        document.addEventListener('click', () => {
            if (themeOptions) {
                themeOptions.classList.remove('show');
            }
        });

        // Theme option selection
        document.querySelectorAll('.theme-option').forEach(option => {
            option.addEventListener('click', () => {
                const theme = option.dataset.theme;
                this.applyTheme(theme);
                if (themeOptions) {
                    themeOptions.classList.remove('show');
                }
                
                // Show theme change notification
                this.showThemeChangeNotification(theme);
            });
        });

        // Listen for system theme changes
        this.setupSystemThemeListener();
    }

    setupSystemThemeListener() {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        mediaQuery.addEventListener('change', (e) => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'auto') {
                this.applyAutoTheme(e.matches);
            }
        });
    }

    applyTheme(theme) {
        // Remove all theme classes
        document.documentElement.classList.remove('theme-light', 'theme-dark', 'theme-night');
        
        // Apply selected theme
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);

        // Update UI based on theme
        this.updateThemeUI(theme);
        
        // If auto theme, apply system preference
        if (theme === 'auto') {
            const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            this.applyAutoTheme(isDark);
        }
    }

    applyAutoTheme(isDark) {
        const systemTheme = isDark ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', 'auto');
        this.updateThemeUI('auto', systemTheme);
    }

    updateThemeUI(selectedTheme, systemTheme = null) {
        const themeToggle = document.getElementById('themeToggle');
        const themeText = themeToggle?.querySelector('.theme-text');
        const themeIcon = themeToggle?.querySelector('i');
        
        if (!themeToggle || !themeText || !themeIcon) return;

        // Update active state in theme options
        document.querySelectorAll('.theme-option').forEach(option => {
            option.classList.remove('active');
            if (option.dataset.theme === selectedTheme) {
                option.classList.add('active');
            }
        });

        // Update toggle button text and icon
        let displayTheme = selectedTheme;
        if (selectedTheme === 'auto') {
            const isDark = systemTheme === 'dark' || window.matchMedia('(prefers-color-scheme: dark)').matches;
            displayTheme = isDark ? 'dark' : 'light';
        }

        switch(displayTheme) {
            case 'light':
                themeText.textContent = 'Light Mode';
                themeIcon.className = 'fas fa-sun';
                break;
            case 'dark':
                themeText.textContent = 'Dark Mode';
                themeIcon.className = 'fas fa-moon';
                break;
            case 'night':
                themeText.textContent = 'Night Mode';
                themeIcon.className = 'fas fa-star';
                break;
        }
    }

    showThemeChangeNotification(theme) {
        // Remove existing notification
        const existingNotification = document.querySelector('.theme-notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        const themeNames = {
            'light': 'Light Mode',
            'dark': 'Dark Mode',
            'night': 'Night Mode',
            'auto': 'Auto Mode'
        };

        const notification = document.createElement('div');
        notification.className = 'theme-notification';
        notification.innerHTML = `
            <i class="fas fa-${this.getThemeIcon(theme)}"></i>
            <span>Theme changed to ${themeNames[theme]}</span>
        `;

        // Add notification styles if not already added
        if (!document.querySelector('#theme-notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'theme-notification-styles';
            styles.textContent = `
                .theme-notification {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: var(--card-bg);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    color: var(--text);
                    padding: 1rem 1.5rem;
                    border-radius: var(--radius);
                    box-shadow: var(--shadow-lg);
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                    z-index: 10000;
                    animation: slideInUp 0.3s ease;
                    font-weight: 600;
                }
                [data-theme="light"] .theme-notification {
                    border: 1px solid rgba(0, 0, 0, 0.1);
                }
                @keyframes slideInUp {
                    from {
                        transform: translateY(100%);
                        opacity: 0;
                    }
                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }
                @keyframes slideOutDown {
                    from {
                        transform: translateY(0);
                        opacity: 1;
                    }
                    to {
                        transform: translateY(100%);
                        opacity: 0;
                    }
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(notification);

        // Auto-remove after 3 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutDown 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }
        }, 3000);
    }

    getThemeIcon(theme) {
        const icons = {
            'light': 'sun',
            'dark': 'moon',
            'night': 'star',
            'auto': 'robot'
        };
        return icons[theme] || 'palette';
    }

    setupEventListeners() {
        // Search form handling
        const searchForm = document.getElementById('searchForm');
        const clearButton = document.getElementById('clearButton');
        const loadingIndicator = document.getElementById('loadingIndicator');

        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                this.handleSearchSubmit(e, searchForm, loadingIndicator);
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', () => {
                this.clearSearchForm();
            });
        }

        // Add loading state to all forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    
                    // Re-enable after 30 seconds (fallback)
                    setTimeout(() => {
                        if (submitBtn.disabled) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    }, 30000);
                }
            });
        });

        // Card hover effects
        this.setupCardInteractions();
    }

    handleSearchSubmit(e, form, loadingIndicator) {
        e.preventDefault();
        
        // Show loading state
        if (loadingIndicator) {
            loadingIndicator.style.display = 'block';
        }

        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
        }

        // Scroll to results after a short delay
        setTimeout(() => {
            const resultsContainer = document.getElementById('resultsContainer');
            if (resultsContainer) {
                resultsContainer.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
            }
        }, 500);

        // Submit the form
        form.submit();
    }

    clearSearchForm() {
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.reset();
            
            // Clear URL parameters and reload
            const url = new URL(window.location);
            url.search = '';
            window.history.replaceState({}, '', url);
            
            // Reload to show initial state
            window.location.reload();
        }
    }

    setupSmoothScrolling() {
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const headerHeight = document.querySelector('.header').offsetHeight;
                    const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                    
                    window.scrollTo({
                        top: targetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add scroll effect to header
        window.addEventListener('scroll', this.throttle(() => {
            const header = document.querySelector('.header');
            if (header) {
                if (window.scrollY > 100) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            }
        }, 10));
    }

    setupFormHandling() {
        // Add character counters to text inputs
        const textInputs = document.querySelectorAll('input[type="text"]');
        textInputs.forEach(input => {
            input.addEventListener('input', this.debounce(() => {
                // You can add character counting logic here
            }, 300));
        });

        // Auto-format phone numbers
        const phoneInputs = document.querySelectorAll('input[type="tel"]');
        phoneInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 10) value = value.slice(0, 10);
                
                if (value.length >= 6) {
                    value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
                } else if (value.length >= 3) {
                    value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
                }
                
                e.target.value = value;
            });
        });
    }

    setupAnimations() {
        // Animate cards on scroll
        this.setupScrollAnimations();
        
        // Add hover effects to buttons
        this.setupButtonAnimations();
    }

    setupCardInteractions() {
        // Add click effects to cards
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
                
                // Add a subtle click effect
                card.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    card.style.transform = '';
                }, 150);
            });
        });
    }

    setupScrollAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe cards and feature items
        document.querySelectorAll('.card, .feature-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });
    }

    setupButtonAnimations() {
        // Add ripple effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    }

    // Utility functions
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    // API-related functions
    async fetchBreweries(params = {}) {
        try {
            const queryParams = new URLSearchParams(params);
            const response = await fetch(`/api/breweries?${queryParams}`);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error fetching breweries:', error);
            this.showError('Failed to fetch breweries. Please try again.');
            return [];
        }
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showNotification(message, type = 'info') {
        // Remove existing notification
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;

        // Add notification styles if not already added
        if (!document.querySelector('#notification-styles')) {
            const styles = document.createElement('style');
            styles.id = 'notification-styles';
            styles.textContent = `
                .notification {
                    position: fixed;
                    top: 100px;
                    right: 20px;
                    background: var(--card-bg);
                    backdrop-filter: blur(20px);
                    border: 1px solid rgba(255, 255, 255, 0.1);
                    color: var(--text);
                    padding: 1rem 1.5rem;
                    border-radius: var(--radius);
                    box-shadow: var(--shadow-lg);
                    z-index: 10000;
                    animation: slideInRight 0.3s ease;
                    max-width: 400px;
                }
                [data-theme="light"] .notification {
                    border: 1px solid rgba(0, 0, 0, 0.1);
                }
                .notification-error { border-left: 4px solid #EF4444; }
                .notification-success { border-left: 4px solid var(--accent); }
                .notification-content {
                    display: flex;
                    align-items: center;
                    gap: 0.75rem;
                }
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(styles);
        }

        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentElement) {
                notification.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }
}

// Add ripple effect styles
const rippleStyles = document.createElement('style');
rippleStyles.textContent = `
    .btn {
        position: relative;
        overflow: hidden;
    }
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.6);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(rippleStyles);

// Initialize the application when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.brewFinderApp = new BrewFinderApp();
});

// Utility functions for global use
window.BrewFinderUtils = {
    formatPhoneNumber(phone) {
        if (!phone) return '';
        const cleaned = phone.replace(/\D/g, '');
        
        if (cleaned.length === 10) {
            return `(${cleaned.slice(0,3)}) ${cleaned.slice(3,6)}-${cleaned.slice(6)}`;
        } else if (cleaned.length === 11 && cleaned[0] === '1') {
            return `+1 (${cleaned.slice(1,4)}) ${cleaned.slice(4,7)}-${cleaned.slice(7)}`;
        }
        
        return phone;
    },

    formatAddress(brewery) {
        const parts = [];
        if (brewery.street) parts.push(brewery.street);
        if (brewery.city) parts.push(brewery.city);
        if (brewery.state) parts.push(brewery.state);
        if (brewery.postal_code) parts.push(brewery.postal_code);
        
        return parts.join(', ');
    },

    getMapUrl(brewery) {
        const query = [brewery.name, brewery.city, brewery.state, brewery.country]
            .filter(Boolean)
            .join(', ');
        
        return `https://maps.google.com/maps?q=${encodeURIComponent(query)}`;
    }
};

// Error boundary for the application
window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
    
    if (window.brewFinderApp) {
        window.brewFinderApp.showError('Something went wrong. Please refresh the page.');
    }
});

// Handle page unload to clean up
window.addEventListener('beforeunload', () => {
    // Clean up any ongoing processes if needed
});
