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
            case 'world_map':
                include_once 'frontend/pages/world_map.php';
                break;
            case 'battle':
                include_once 'frontend/pages/battle.php';
                break;
            case 'training':
                include_once 'frontend/pages/training.php';
                break;
            case 'battle_report':
                include_once 'frontend/pages/battle_report.php';
                break;
            case 'gather':
                include_once 'frontend/pages/gather.php';
                break;
            case 'initialize_world':
                include_once 'frontend/pages/initialize_world.php';
                break;            case 'process_ai_turn':
                include_once 'frontend/pages/process_ai_turn.php';
                break;
            case 'ai_opponents':
                include_once 'frontend/pages/ai_opponents.php';
                break;
            case 'battle_history':
                include_once 'frontend/pages/battle_history.php';
                break;
            default:
                include_once 'frontend/pages/404.php';
                break;
        }

    } else {
        require 'frontend/pages/home.php';
    }
}
