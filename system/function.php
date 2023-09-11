<?php

function getPage()
{
    if (isset($_GET['page'])) {
        $page = $_GET['page'];
        switch ($page) {
            case 'home':
                include_once 'frontend/pages/home.php';
                break;
            case 'contact':
                include_once 'frontend/pages/contact.php';
                break;
            case 'login':
                include_once 'frontend/pages/login.php';
                break;
            case 'register':
                include_once 'frontend/pages/register.php';
                break;
            case 'community':
                include_once 'frontend/pages/community.php';
                break;
            case 'support':
                include_once 'frontend/pages/support.php';
                break;
            case 'about':
                include_once 'frontend/pages/about.php';
                break;
            case 'logout':
                include_once 'frontend/pages/logout.php';
                break;
            default:
                include_once 'frontend/pages/404.php';
                break;
        }

    } else {
        require 'frontend/pages/home.php';
    }
}
