document.addEventListener('DOMContentLoaded', () => {
    // 1. Current Date in Header
    const dateElement = document.getElementById('header-time-string');
    if (dateElement) {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        dateElement.textContent = new Date().toLocaleDateString('en-US', options);
    }

    // 2. Theme Toggling
    const themeToggleBtn = document.getElementById('theme-toggle');
    const body = document.body;
    
    // Check saved theme
    const savedTheme = localStorage.getItem('saaes_theme');
    if (savedTheme === 'dark') {
        body.classList.remove('theme-light');
        body.classList.add('theme-dark');
        if(themeToggleBtn) themeToggleBtn.innerHTML = '<i class="fa-solid fa-sun"></i>';
    }

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            if (body.classList.contains('theme-light')) {
                body.classList.replace('theme-light', 'theme-dark');
                themeToggleBtn.innerHTML = '<i class="fa-solid fa-sun"></i>';
                localStorage.setItem('saaes_theme', 'dark');
            } else {
                body.classList.replace('theme-dark', 'theme-light');
                themeToggleBtn.innerHTML = '<i class="fa-solid fa-moon"></i>';
                localStorage.setItem('saaes_theme', 'light');
            }
        });
    }

    // 3. Profile Dropdown
    const profileBtn = document.getElementById('profile-btn');
    const profileDropdown = document.getElementById('profile-dropdown-menu');
    
    if (profileBtn && profileDropdown) {
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        // Close when clicking outside
        document.addEventListener('click', (e) => {
            if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }

    // 4. Tab Navigation
    const navItems = document.querySelectorAll('.nav-item');
    const tabContents = document.querySelectorAll('.tab-content');

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            // Check if it's a link to logout or similar that shouldn't act as a tab
            if (item.id === 'sidebar-logout-btn') return;

            e.preventDefault();
            const targetTab = item.getAttribute('data-tab');
            
            if (!targetTab) return;

            // Remove active from all nav items
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');

            // Hide all tab contents
            tabContents.forEach(content => {
                content.classList.remove('active');
            });

            // Show target tab
            const targetContent = document.getElementById(`tab-${targetTab}`);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });

    // Handle Summary Card clicks to redirect to tabs
    const summaryCards = document.querySelectorAll('.summary-card');
    summaryCards.forEach(card => {
        card.addEventListener('click', () => {
            const targetTab = card.getAttribute('data-target-tab');
            if(targetTab) {
                const navItem = document.querySelector(`.nav-item[data-tab="${targetTab}"]`);
                if(navItem) navItem.click();
            }
        });
    });

    // 5. Modals
    const modals = {
        submit: document.getElementById('modal-submit-activity'),
        contact: document.getElementById('modal-contact-faculty')
    };

    // Example button triggers (if they exist)
    const btnSubmit = document.getElementById('btn-open-submit');
    const btnContact = document.getElementById('btn-open-contact');

    if (btnSubmit) btnSubmit.addEventListener('click', () => openModal('submit'));
    if (btnContact) btnContact.addEventListener('click', () => openModal('contact'));

    // Close buttons
    const closeBtns = document.querySelectorAll('.close-modal-btn');
    closeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal-overlay');
            if(modal) modal.classList.remove('show');
        });
    });

    // Cancel buttons
    const cancelBtns = document.querySelectorAll('.btn-secondary-outline');
    cancelBtns.forEach(btn => {
        if(btn.textContent.includes('Cancel')) {
            btn.addEventListener('click', function() {
                const modal = this.closest('.modal-overlay');
                if(modal) modal.classList.remove('show');
            });
        }
    });

    function openModal(modalId) {
        if(modals[modalId]) {
            modals[modalId].classList.add('show');
        }
    }

    // 6. Toast System
    window.showToast = function(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.className = 'toast';
        
        let icon = 'fa-info-circle text-info';
        if(type === 'success') icon = 'fa-check-circle text-success';
        if(type === 'warning') icon = 'fa-exclamation-triangle text-warning';
        if(type === 'error') icon = 'fa-times-circle text-danger';

        toast.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${message}</span>`;
        
        container.appendChild(toast);

        // Remove after 3 seconds
        setTimeout(() => {
            toast.style.animation = 'slideInRight 0.3s ease-out reverse forwards';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    };

    // Populate data if window.PHP_DATA exists
    if (window.PHP_DATA) {
        console.log("PHP Data loaded successfully:", window.PHP_DATA);
        // Additional chart initialization can be added here if canvas elements exist
    }
});
