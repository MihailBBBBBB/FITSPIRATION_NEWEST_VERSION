document.getElementById('loginForm').addEventListener('submit', function(event) {
    
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value.trim();
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');
    
    // Reset error messages
    emailError.textContent = '';
    passwordError.textContent = '';
    
    if (!email) {
        emailError.textContent = 'Email is required.';
        return;
    } else if (!/\S+@\S+\.\S+/.test(email)) {
        emailError.textContent = 'Please enter a valid email address.';
        return;
    }
    
    if (!password) {
        passwordError.textContent = 'Password is required.';
        return;
    } else if (password.length < 8) {
        passwordError.textContent = 'Password must be at least 8 characters long.';
        return;
    }
    
    if (!isValid) {
        event.preventDefault(); // stop if invalid
    }
});