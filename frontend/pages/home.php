<?php
include_once __DIR__ . '/../' . 'templates/header.php';
include_once __DIR__ . '/../' . 'templates/topnav.php';

require __DIR__ . '/../../backend/scripts/home_scripts.php';
$armies = [
    'fighters' => 90,
    'shooters' => 150,
    'vehicles' => 50,
    'skmisher' => 90,
    'rides' => 150,
    'canons' => 50,
    'jets' => 90,
    'achers' => 150,
    'maluders' => 50,
];


?>
<div class="main">
    <section class="content py-3">
        <div class="main-container">
            <div class="side-box-left">
                <div class="card left-panel">
                    <div class="side-title">
                        Resources
                    </div>
                    <div class="card-body resource-wrapper">
                        <div class="d-block resource-list justify-content-center">
                            <div class="resource-container">
                                <?php
                                foreach ($productions as $prodName => $production) :
                                ?>
                                <a href="#" class="resource-link">
                                    <div class="col-12 m-1 lr-border">
                                        <div class="resource-item">
                                            <div class="resource-name"><?= $prodName ?></div>
                                            <div class="resource-level"><?= rand(0, 50) ?></div>
                                        </div>
                                        <div class="resource-rate"><?= $production ?>/hour</div>
                                    </div>
                                </a>
                                <?php
                                endforeach
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="center-box">
                <div class="card center-panel">
                    <div class="d-flex flex-wrap ">
                        <div class="flex-fill me-2 cr-border">
                            <div class="cr-title">
                                Profile
                            </div>
                            <div class="d-flex justify-content-center align-items-center">
                                <div class="crp-value">
                                    <?= $username ?>
                                </div>
                            </div>
                        </div>
                        <div class="flex-fill me-2 cr-border">
                            <div class="cr-title">
                                Power
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="cr-value">20000000000</div>
                            </div>
                        </div>
                        <div class="flex-fill me-2 cr-border">
                            <div class="cr-title">
                                Exp+
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="cr-value">1500</div>
                            </div>
                        </div>
                        <?php
                        foreach ($resources as $resName => $resourceValue) :
                        ?>
                        <div class="flex-fill me-2 cr-border">
                            <div class="cr-title">
                                <?= $resName ?>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="cr-value"><?= (int) $resourceValue ?></div>
                                <a href="#" class="add-btn res-btn">+</a>
                            </div>
                        </div>
                        <?php
                        endforeach
                        ?>
                    </div>
                    <div class="card-body village">
                        <div class="village-wrapper d-flex justify-content-between align-items-center">

                            <div id="vilage-buffs">
                                <div class="village-buff">
                                    <div class="bimg">
                                        BF
                                    </div>
                                </div>
                                <div class="village-buff">
                                    <svg viewBox="0 0 100 100">
                                        <circle cx="50" cy="50" r="25"></circle>
                                    </svg>
                                    <div class="bimg">
                                        BF
                                    </div>
                                </div>
                            </div>

                            <div id="village-building">
                                <div class="building" title="Level 10">
                                    <div class="level">Lv 10</div>
                                    <div class="building-inner">
                                        <a href="#!">
                                            <p>
                                                <span class="bp-icon">⬆️</span>
                                                <span class="building-text">infirmary</span>
                                            </p>
                                        </a>
                                    </div>
                                </div>
                                <div class="building" title="Level 5">
                                    <div class="level">Lv 5</div>
                                    <div class="building-inner">
                                        <a href="#!">
                                            <p>
                                                <span class="bp-icon">⬆️</span>
                                                <span class="building-text">Herohall</span>
                                            </p>
                                        </a>
                                    </div>
                                </div>
                                <div class="building" title="Level 8">
                                    <div class="level">Lv 8</div>
                                    <div class="building-inner">
                                        <a href="#!">
                                            <p>
                                                <span class="bp-icon">⬆️</span>
                                                <span class="building-text">Hospital</span>
                                            </p>
                                        </a>
                                    </div>
                                </div>
                                <div class="building" title="Level 15">
                                    <div class="level">Lv 15</div>
                                    <div class="building-inner">
                                        <a href="#!">
                                            <p>
                                                <span class="bp-icon">⬆️</span>
                                                <span class="building-text">Bank</span>
                                            </p>
                                        </a>
                                    </div>
                                </div>
                                <div class="building" title="Level 13">
                                    <div class="level">Lv 13</div>
                                    <div class="building-inner">
                                        <a href="#!">
                                            <p>
                                                <span class="bp-icon">⬆️</span>
                                                <span class="building-text">Villagehall</span>
                                            </p>
                                        </a>
                                    </div>
                                </div>
                                <div class="building" title="Level 23">
                                    <div class="level">Lv 23</div>
                                    <div class="building-inner">
                                        <a href="#!">
                                            <p>
                                                <span class="bp-icon">⬆️</span>
                                                <span class="building-text">Storage</span>
                                            </p>
                                        </a>
                                    </div>
                                </div>
                                <div class="building" title="Level 15">
                                    <div class="level">Lv 15</div>
                                    <div class="building-inner">
                                        <a href="#!">
                                            <p>
                                                <span class="bp-icon">⬆️</span>
                                                <span class="building-text">Houses</span>
                                            </p>
                                        </a>
                                    </div>
                                </div>
                                <div class="building" title="Level 5">
                                    <div class="level">Lv 5</div>
                                    <div class="building-inner">
                                        <a href="#!">
                                            <p>
                                                <span class="bp-icon">⬆️</span>
                                                <span class="building-text">Blacksmith</span>
                                            </p>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="content-wrapper align-self-center">
                                <div class="mini-frame">
                                    <div class="mf-header">
                                        <div class="mf-header-title">
                                            <p>
                                                Food
                                            </p>
                                        </div>
                                        <div class="mf-header-content-container">
                                            <div class="mf-h-content-item">
                                                <div class="mf-header-final-title"> Production</div>
                                                <div>5000/min</div>
                                            </div>
                                            <div class="mf-h-content-item">
                                                <div class="mf-header-final-title">Store</div>
                                                <div>40000</div>
                                            </div>
                                            <div class="mf-h-content-item">
                                                <div class="mf-header-final-title">Farms</div>
                                                <div>5</div>
                                            </div>
                                            <div class="mf-h-content-item">
                                                <div class="mf-header-final-title">Store Cap</div>
                                                <div>3000000</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mf-body">
                                        <div class="mf-body-resource-container">
                                            <div class="mf-resource-cards">
                                                <div class="mf-resource-title">
                                                    Farm 1
                                                </div>
                                                <div class="mf-resource-operation">
                                                    <div class="mf-resource-level">Lv 6</div>
                                                    <div class="mf-resource-btn">Upgrade</div>
                                                </div>
                                            </div>
                                            <div class="mf-resource-cards">
                                                <div class="mf-resource-title">
                                                    Farm 2
                                                </div>
                                                <div class="mf-resource-operation">
                                                    <div class="mf-resource-level">Lv 3</div>
                                                    <div class="mf-resource-btn">Upgrade</div>
                                                </div>
                                            </div>
                                            <div class="mf-resource-cards">
                                                <div class="mf-resource-title">
                                                    Farm 3
                                                </div>
                                                <div class="mf-resource-operation">
                                                    <div class="mf-resource-level">Lv 4</div>
                                                    <div class="mf-resource-btn">Upgrade</div>
                                                </div>
                                            </div>
                                            <div class="mf-resource-cards">
                                                <div class="mf-resource-title">
                                                    Farm 4
                                                </div>
                                                <div class="mf-resource-operation">
                                                    <div class="mf-resource-level">Lv 2</div>
                                                    <div class="mf-resource-btn">Upgrade</div>
                                                </div>
                                            </div>
                                            <div class="mf-resource-cards">
                                                <div class="mf-resource-title">
                                                    Farm 5
                                                </div>
                                                <div class="mf-resource-operation">
                                                    <div class="mf-resource-level">Lv 6</div>
                                                    <div class="mf-resource-btn">Upgrade</div>
                                                </div>
                                            </div>
                                            <div class="mf-resource-cards">
                                                <div class="mf-resource-title">
                                                    Farm 6
                                                </div>
                                                <div class="mf-resource-operation">
                                                    <div class="mf-resource-level">Lv 6</div>
                                                    <div class="mf-resource-btn">Upgrade</div>
                                                </div>
                                            </div>
                                            <div class="mf-resource-cards">
                                                <div class="mf-resource-title">
                                                    Farm 7
                                                </div>
                                                <div class="mf-resource-operation">
                                                    <div class="mf-resource-level">Lv 6</div>
                                                    <div class="mf-resource-btn">Upgrade</div>
                                                </div>
                                            </div>
                                            <div class="mf-resource-cards">
                                                <div class="mf-resource-title">
                                                    Farm 8
                                                </div>
                                                <div class="mf-resource-operation">
                                                    <div class="mf-resource-level">Lv 6</div>
                                                    <div class="mf-resource-btn">Upgrade</div>
                                                </div>
                                            </div>
                                            <div class="mf-resource-cards">
                                                <div class="mf-resource-title">
                                                    Farm 8
                                                </div>
                                                <div class="mf-resource-operation">
                                                    <div class="mf-resource-level">Lv 6</div>
                                                    <div class="mf-resource-btn">Upgrade</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mf-footer">
                                        <div>
                                            This is the footer for the mini frame
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="village-management">

                                <div id="vm-skill-timer">
                                    <div class="skill-timer"></div>
                                    <div class="img">
                                        time
                                    </div>
                                    <p class="vm-skill-time">NAN</p>
                                </div>

                                <div id="manage">
                                    <div class="manage-circle">
                                        <svg viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="41"></circle>
                                            <circle cx="50" cy="50" r="41"></circle>
                                        </svg>
                                        <h3 class="manage-text">45</h3>
                                        <p class="manage-lable">Tech</p>
                                    </div>
                                    <div class="manage-circle">
                                        <svg viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="41"></circle>
                                            <circle cx="50" cy="50" r="41"></circle>
                                        </svg>
                                        <h3 class="manage-text">67</h3>
                                        <p class="manage-lable">Heros</p>
                                    </div>
                                    <div class="manage-circle">
                                        <svg viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="41"></circle>
                                            <circle cx="50" cy="50" r="41"></circle>
                                        </svg>
                                        <h3 class="manage-text">78</h3>
                                        <p class="manage-lable">Forging</p>
                                    </div>
                                    <div class="manage-circle">
                                        <svg viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="41"></circle>
                                            <circle cx="50" cy="50" r="41"></circle>
                                        </svg>
                                        <h3 class="manage-text">25</h3>
                                        <p class="manage-lable">Clan</p>
                                    </div>
                                    <div class="manage-circle">
                                        <svg viewBox="0 0 100 100">
                                            <circle cx="50" cy="50" r="41"></circle>
                                            <circle cx="50" cy="50" r="41"></circle>
                                        </svg>
                                        <h3 class="manage-text">6</h3>
                                        <p class="manage-lable">Quest</p>
                                    </div>
                                </div>

                                <div id="vm-queue-timer">
                                    <div class="queue-timer"></div>
                                    <div class="img">Train</div>
                                    <p class="vm-queue-time">01m : 34s</p>
                                    <div class="vm-queue-check">
                                        <p>0</p>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>
                    <div class="card-footer">
                        <div class="d-flex flex-row-reverse pf-right">
                            <div class="p-2 pf-right-option">
                                <a href="index.php?page=logout">
                                    <i class="fa-solid fa-person-running" style="color: #ffffff;"></i>
                                    <p>logout</p>
                                </a>
                            </div>
                            <div class="p-2 pf-right-option">
                                <i class="fa-solid fa-user-gear" style="color: #ffffff;"></i>
                                <p>settings</p>
                            </div>
                            <div class="p-2 pf-right-option">
                                <i class="fa-solid fa-trophy" style="color: #ffffff;"></i>
                                <p class="achive">Achivement</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="side-box-right">
                <div class="card right-panel">
                    <div class="side-title">
                        Armies
                        <div class="notification">
                            <?= $cityName ?>
                        </div>
                    </div>
                    <div class="card-body army-wrapper">
                        <div class="d-block army-list justify-content-center">
                            <div class="army-container">
                                <?php
                                foreach ($armies as $amryName => $amry) :
                                ?>
                                <a href="#" class="army-link">
                                    <div class="col-12 m-1 rr-border">
                                        <div class="army-item">
                                            <div class="army-name"><?= $amryName ?></div>
                                            <div class="army-level"><?= rand(0, 50) ?></div>
                                        </div>
                                        <div class="army-rate"><?= $amry ?>/train</div>
                                    </div>
                                </a>
                                <?php
                                endforeach
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<?php
include_once __DIR__ . '/../' . 'templates/footer.php';
include_once __DIR__ . '/../' . 'templates/scripts.php';
?>