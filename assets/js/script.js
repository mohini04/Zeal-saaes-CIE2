// SAAES Common JavaScript File

document.addEventListener('DOMContentLoaded', () => {
    // 1. Live clock update in header
    const clockElement = document.getElementById('live-datetime');
    if (clockElement) {
        const updateClock = () => {
            const now = new Date();
            
            // Format options matching: 20 Jul 2026 04:58:12 PM
            const day = String(now.getDate()).padStart(2, '0');
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const month = months[now.getMonth()];
            const year = now.getFullYear();
            
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12; // the hour '0' should be '12'
            const formattedHours = String(hours).padStart(2, '0');
            
            clockElement.innerHTML = `${day} ${month} ${year} <span style="color: var(--primary); margin: 0 4px; font-weight: 700;">|</span> ${formattedHours}:${minutes}:${seconds} ${ampm}`;
        };
        
        updateClock();
        setInterval(updateClock, 1000);
    }

    // 2. Login Role Selection & Credential Autofill Logic
    const roleButtons = document.querySelectorAll('.role-select-btn');
    const roleInput = document.getElementById('selected-role');
    const emailInput = document.getElementById('login-email');
    const passwordInput = document.getElementById('login-password');
    const demoRoleName = document.getElementById('demo-role-name');
    const demoEmail = document.getElementById('demo-email');
    const demoPassword = document.getElementById('demo-password');

    const credentials = {
        'Admin': { email: 'admin@zcoer.in', pass: 'admin123' },
        'Faculty': { email: 'faculty@zcoer.in', pass: 'faculty123' },
        'Student': { email: 'student@zcoer.in', pass: 'student123' },
        'Parent': { email: 'parent@zcoer.in', pass: 'parent123' },
        'HOD': { email: 'hod@zcoer.in', pass: 'hod123' },
        'GFM': { email: 'gfm@zcoer.in', pass: 'gfm123' }
    };

    if (roleButtons.length > 0 && roleInput) {
        roleButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                roleButtons.forEach(b => b.classList.remove('active'));
                
                // Add active class to clicked button
                btn.classList.add('active');
                
                const selectedRole = btn.getAttribute('data-role');
                roleInput.value = selectedRole;

                // Update input values with demo credentials
                if (credentials[selectedRole]) {
                    emailInput.value = credentials[selectedRole].email;
                    passwordInput.value = credentials[selectedRole].pass;
                    
                    // Update sidebar demo text
                    if (demoRoleName) demoRoleName.textContent = selectedRole;
                    if (demoEmail) demoEmail.textContent = credentials[selectedRole].email;
                    if (demoPassword) demoPassword.textContent = credentials[selectedRole].pass;
                }
            });
        });

        // Trigger click on default active button (usually Student or Admin)
        const defaultActive = document.querySelector('.role-select-btn.active');
        if (defaultActive) {
            defaultActive.click();
        }
    }
});
