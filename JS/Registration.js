document.getElementById('registrationForm').addEventListener('submit', function(event) {
        // Clear previous error messages
        document.getElementById('emailError').textContent = '';
        document.getElementById('passwordError').textContent = '';
        document.getElementById('dobError').textContent = '';
    
        // Get form values
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value.trim();
        const dob = document.getElementById('dob').value.trim();
    
        let isValid = true;
    
        if (!email) {
            document.getElementById('emailError').textContent = 'Email is required.';
            isValid = false;
        } else if (!/\S+@\S+\.\S+/.test(email)) {
            document.getElementById('emailError').textContent = 'Email is invalid.';
            isValid = false;
        }
    
        if (!password) {
            document.getElementById('passwordError').textContent = 'Password is required.';
            isValid = false;
        } else if (password.length < 8) {
            document.getElementById('passwordError').textContent = 'Password must be at least 8 characters long.';
            isValid = false;
        }
    
        if (!dob) {
            document.getElementById('dobError').textContent = 'Date of birth is required.';
            isValid = false;
        } else if (!/^\d{4}-\d{2}-\d{2}$/.test(dob)) {
            document.getElementById('dobError').textContent = 'Date of birth must be in the format yyyy-mm-dd.';
            isValid = false;
        }

    if (!isValid) {
        event.preventDefault(); // stop if invalid
    }
});