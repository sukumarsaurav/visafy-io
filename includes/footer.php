<footer class="footer bg-dark-blue">
    <div class="container">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3>Visafy</h3>
                    <p>Visafy is a global SaaS platform connecting immigration applicants with trusted consultants.
                        Access our comprehensive digital solution from anywhere in the world to streamline your
                        immigration journey.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>

                <div class="footer-col">
                    <h3>Platform</h3>
                    <ul class="footer-links">
                        <li><a href="/about-us.php">About Us</a></li>
                        <li><a href="/become-member.php">Become Member</a></li>
                        <li><a href="/eligibility-test.php">Eligibility test</a></li>
                        <li><a href="/book-service.php">Find Consultant</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h3>Resources</h3>
                    <ul class="footer-links">
                        <li><a href="/guides.php">Immigration Guides</a></li>
                        <li><a href="/features.php">Features</a></li>
                        <li><a href="/faq.php">FAQ</a></li>
                        <li><a href="/support.php">Help Center</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Legal</h3>
                    <ul class="footer-links">
                        <li><a href="/terms.php">Terms of Service</a></li>
                        <li><a href="/privacy.php">Privacy Policy</a></li>
                        <li><a href="/cookies.php">Cookie Policy</a></li>
                        <li><a href="/sitemap.php">Sitemap</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <p>&copy; <span id="current-year"></span> Visafy. All Rights Reserved.</p>
                    <div class="footer-bottom-links">
                        <a href="/status">Status</a>
                        <span class="separator">|</span>
                        <a href="/security">Security</a>
                        <span class="separator">|</span>
                        <a href="/trust">Trust Center</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; <span id="current-year"></span> Visafy Immigration Consultancy. All Rights Reserved.</p>
        </div>
    </div>
</footer>
<style>
.footer-links {
    list-style: none;
    padding: 0;
    margin: 0;
}

a {
    color: var(--color-cream);
    transition: all 0.3s ease;
}
</style>
<!-- JavaScript Libraries -->
<script src="https://unpkg.com/swiper@8/swiper-bundle.min.js"></script>
<script src="https://unpkg.com/aos@next/dist/aos.js"></script>

<!-- Custom JavaScript -->
<script src="/assets/js/main.js"></script>
<script src="/assets/js/header.js"></script>
<script src="/assets/js/resources.js"></script>




<!-- JavaScript initialization -->
<script>
AOS.init({
    duration: 800,
    easing: 'ease-in-out',
    once: true
});
</script>

<!-- If you have footer-specific JS files -->
<script src="<?php echo isset($base_path) ? $base_path : ''; ?>/assets/js/footer.js"></script>

<!-- Add animated text scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const animatedTextWrapper = document.querySelector('.animated-text-wrapper');
    if (animatedTextWrapper) {
        const phrases = ['Students', 'Workers', 'Families', 'Businesses', 'Entrepreneurs'];
        let currentPhraseIndex = 0;

        function updateAnimatedText() {
            animatedTextWrapper.textContent = phrases[currentPhraseIndex];
            currentPhraseIndex = (currentPhraseIndex + 1) % phrases.length;
        }

        // Set initial text
        updateAnimatedText();

        // Update text every 3 seconds
        setInterval(updateAnimatedText, 3000);
    }
});
</script>
</body>

</html>