<?php
require __DIR__ . '/system/includes.php';

if ($maintainance == true) {
    echo 'We are under maintainance please come back later.';
} elseif ($maintainance == false) {
    if (isset($_GET['msg'])) {
        $msg = urldecode($_GET['msg']);
    }

    if (isset($msg)) : ?>

<div class="position-fixed top-0 start-50 translate-middle-x w-50 mt-3 z-3 opacity-50">
    <div class="alert alert-info alert-dismissible fade show rounded-3 p-3" role="alert">
        <p class="mb-0 text-center text-uppercase fw-bold text-gaming"> <?= $msg; ?> </p>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
</div>

<script>
setTimeout(function() {
    document.querySelector('.alert').classList.add('fade');
    setTimeout(function() {
        document.querySelector('.alert').remove();
    }, 1000);
}, 3000);
</script>


<?php
    endif;
    getPage();
}