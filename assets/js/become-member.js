
document.addEventListener('DOMContentLoaded', function() {
    // Debug - log the available plans
    console.log('Available plans:', document.querySelectorAll('.plan-card').length);
    
    // Initialize AOS animations if available
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
    }
    
    // Modal functionality
    const modal = document.getElementById('planSelectionModal');
    const closeButtons = document.querySelectorAll('[data-dismiss="modal"]');
    const selectPlanButtons = document.querySelectorAll('.select-plan-btn');
    
    if (modal && selectPlanButtons.length > 0) {
        // Open modal when clicking "Select Plan" button
        selectPlanButtons.forEach(button => {
            button.addEventListener('click', function() {
                const planId = this.getAttribute('data-plan-id');
                const planName = this.getAttribute('data-plan-name');
                const planPrice = this.getAttribute('data-plan-price');
                const planMembers = this.getAttribute('data-plan-members');
                
                // Set modal values
                document.getElementById('selected-plan-name').textContent = planName;
                document.getElementById('selected-plan-price').textContent = planPrice;
                document.getElementById('selected-plan-members').textContent = planMembers;
                document.getElementById('modal_membership_plan_id').value = planId;
                
                // Show modal
                modal.style.display = 'block';
            });
        });
        
        // Close modal when clicking close button or outside the modal
        if (closeButtons.length > 0) {
            closeButtons.forEach(button => {
                button.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            });
        }
        
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    }
    
    // Form validation
    const passwordField = document.getElementById('modal_password');
    const confirmPasswordField = document.getElementById('modal_confirm_password');
    
    if (passwordField && confirmPasswordField) {
        // Check password match on input
        confirmPasswordField.addEventListener('input', function() {
            if (passwordField.value !== confirmPasswordField.value) {
                confirmPasswordField.setCustomValidity("Passwords don't match");
            } else {
                confirmPasswordField.setCustomValidity('');
            }
        });
        
        // Check password strength
        passwordField.addEventListener('input', function() {
            const password = passwordField.value;
            
            // Basic validation
            if (password.length < 8) {
                passwordField.setCustomValidity("Password must be at least 8 characters long");
            } else {
                passwordField.setCustomValidity('');
            }
            
            // If confirm password is already filled, check match
            if (confirmPasswordField.value) {
                if (password !== confirmPasswordField.value) {
                    confirmPasswordField.setCustomValidity("Passwords don't match");
                } else {
                    confirmPasswordField.setCustomValidity('');
                }
            }
        });
    }
});

