<?php
include_once __DIR__ . '/../' . 'templates/header.php';
if ($user_logged_in === false) :
?>
<section class="content py-5 form-content">
    <div class="container register-container">
        <div class="card w-50 bg-dark text-white">
            <div class="card-header text-white text-center">
                <h4 class="mb-0">Join the Battle!</h4>
                <p class="mt-2 mb-0">Enter your details below and join the gaming community</p>
            </div>
            <div class="card-body">
                <hr class="hr-primary hr-thin">
                <p class="card-text">Create your account to join the fight and compete with players from around the
                    world.</p>
                <form action="" method="post">
                    <div class="row w-100">
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="" class="form-label">Player Name</label>
                                <input type="text" name="name" id="" class="form-control bg-dark text-white"
                                    placeholder="Enter your name">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="" class="form-label">Email Address</label>
                                <input type="text" name="email" id="" class="form-control bg-dark text-white"
                                    placeholder="Enter your email">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="" class="form-label">Gamer Tag</label>
                                <input type="text" name="username" id="" class="form-control bg-dark text-white"
                                    placeholder="Enter your tag">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="" class="form-label">Password</label>
                                <input type="password" name="password" id="" class="form-control bg-dark text-white"
                                    placeholder="Enter a password">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <label for="" class="form-label">Confirm Password</label>
                                <input type="password" name="confirmedpassword" id=""
                                    class="form-control bg-dark text-white" placeholder="Confirm your password">
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="mb-3">
                                <input type="submit" name="register" class="btn btn-info float-end" value="Join">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Already have an account?</p>
                <a href="index.php?page=login" class="text-white">Log in here</a>
            </div>
        </div>
    </div>
</section>
<?php
else:
    $msg = 'Your  already have an account';
    header('Location: index.php?page=home' . urlencode($msg));
    // header('Location: index.php?page=home&msg=' . urlencode($msg));
endif;
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>