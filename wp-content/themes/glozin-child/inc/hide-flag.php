<?php
add_action('admin_menu', 'rename_telsender_menu', 999);
function rename_telsender_menu() {
    global $menu, $submenu;

    // Главное меню
    foreach ($menu as $key => $item) {
        if (isset($item[2]) && $item[2] === 'telsender') {
            $menu[$key][0] = 'TelSender';
            break;
        }
    }

    // Подменю
    if (isset($submenu['telsender'][0])) {
        $submenu['telsender'][0][0] = 'TelSender';
    }
}