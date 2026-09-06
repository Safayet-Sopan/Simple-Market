<?php
?>
<?php if (isset($_SESSION['user_id'])): ?>
    <script src="<?php echo BASE_URL; ?>assets/js/main.js"
            data-poll-url="<?php echo e(url('ajax', 'poll_notifications')); ?>"></script>
<?php endif; ?>
<script src="<?php echo BASE_URL; ?>assets/js/validation.js"></script>
</body>

</html>
