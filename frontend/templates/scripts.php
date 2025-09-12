<script src="frontend/design/js/jquery-3.6.4.min.js"></script>
<script src="frontend/design/js/fontawesome.js"></script>
<script src="frontend/design/js/bootstrap.bundle.min.js"></script>
<script src="frontend/design/js/custom.js"></script>
<script src="frontend/design/js/big.min.js"> </script>
<script src="frontend/design/js/resources.js"></script>
<!-- SweetAlert2 library -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Custom game alerts -->
<script src="frontend/design/js/game-alerts.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.30.1/moment.min.js"></script>
<!-- Mail notification system -->
<?php if ($g->isUserLoggedIn()) : ?>
<script>
window.mailConfig = {
    userId: <?php echo $g->getCurrentUser('id'); ?>,
    username: '<?php echo htmlspecialchars($g->getCurrentUser('uname')); ?>',
    apiBase: 'backend/scripts/'
};
</script>
<script src="frontend/design/js/mail-notifications.js"></script>
<?php endif; ?>
</body>

</html>