<?php
include_once __DIR__ . '/../' . 'templates/header.php';
if ($user_logged_in === false) :
?>
    <div class="main">
        <section class="content py-5 form-content" style="background-image: srs;">
            <div class="container login-container">
                <div class="card w-50 bg-dark text-white login-wrapper">
                    <div class="card-header text-white text-center">
                        <h4 class="mb-0">Welcome back to the battle!</h4>
                        <p class="mt-2 mb-0">Log in to your gaming account</p>
                    </div>
                    <div class="card-body">
                        <hr class="hr-primary hr-thin">
                        <form action="" method="post">
                            <div class="row w-100">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="" class="form-label">Email Address / Username</label>
                                        <input type="text" name="username" id="username" class="form-control bg-dark text-white" placeholder="Enter your email or username">
                                        <small class="form-text text-white">We'll never share your email with anyone
                                            else.</small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="" class="form-label">Password</label>
                                        <input type="password" name="password" id="password" class="form-control bg-dark text-white" placeholder="Enter your password">
                                        <small class="form-text"><a href="#">Forgot password?</a></small>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="" id="remember-me">
                                            <label class="form-check-label" for="remember-me">
                                                Remember Me
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3">
                                        <input type="submit" name="login" class="btn btn-info float-end" value="Log In">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center">
                        <p class="mb-0">Don't have an account yet?</p>
                        <a href="index.php?page=register" class="text-white">Join the fight!</a>
                    </div>
                </div>
            </div>
        </section>
    </div>

<?php
else :
    $msg = 'Your are already logged in';
    header('Location: index.php?page=home&msg=' . urlencode($msg));
// header('Location: index.php?page=home&msg=' . $msg . '&redirect');
endif;
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>