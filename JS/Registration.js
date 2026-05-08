document.getElementById('registrationForm').addEventListener('submit', function(event) {
        function isAtLeast13YearsOld(dateString) {
            const birthDate = new Date(dateString + 'T00:00:00');
            if (Number.isNaN(birthDate.getTime())) {
                return false;
            }

            const today = new Date();
            const threshold = new Date(today.getFullYear() - 13, today.getMonth(), today.getDate());

            return birthDate <= threshold;
        }

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
        } else if (Number.isNaN(new Date(dob).getTime())) {
            document.getElementById('dobError').textContent = 'Please choose a valid date of birth.';
            isValid = false;
        } else if (!isAtLeast13YearsOld(dob)) {
            document.getElementById('dobError').textContent = 'You must be at least 13 years old to register.';
            isValid = false;
        }

    if (!isValid) {
        event.preventDefault(); // stop if invalid
    }
});