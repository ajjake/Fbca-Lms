    </main>
    <footer class="footer">
        <div class="footer-content">
            <img src="<?php echo BASE_URL; ?>assets/fbca_logo.png" alt="FBCA" class="footer-logo">
            <p>&copy; <?php echo date('Y'); ?> FBCA Learning Management System. All rights reserved.</p>
        </div>
    </footer>
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
    <?php if (isset($additionalScripts)): ?>
        <?php foreach ($additionalScripts as $script): ?>
            <script src="<?php echo BASE_URL . $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
