<?php
/**
 *  A session-only administrator for the UI suites on the box, made and
 *  removed by the session that needs it (memory: hetzner-box-fixtures --
 *  CI's own user vanishes mid-run; a borrowed one is somebody else's).
 *
 *      node tools/box-eval.mjs tools/box-ui-admin.php --env VGML_ACTION=create --env VGML_USER=vgmls10 --env VGML_PASS=…
 *      node tools/box-eval.mjs tools/box-ui-admin.php --env VGML_ACTION=delete --env VGML_USER=vgmls10
 *      … --site shop: the same on the second network, as a super admin.
 *
 *  The password is never printed and never stored anywhere but the caller's
 *  own scratchpad.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$action = getenv( 'VGML_ACTION' );
$login  = sanitize_user( (string) getenv( 'VGML_USER' ), true );
$pass   = (string) getenv( 'VGML_PASS' );

if ( '' === $login ) {
    echo "VGML_USER is required\n";
    return;
}

$user = get_user_by( 'login', $login );

if ( 'create' === $action ) {
    if ( '' === $pass ) {
        echo "VGML_PASS is required\n";
        return;
    }
    if ( $user ) {
        wp_set_password( $pass, $user->ID );
        echo "user {$login} exists: password set\n";
    } else {
        $id = wp_insert_user( array( 'user_login' => $login, 'user_pass' => $pass, 'user_email' => $login . '@example.invalid', 'role' => 'administrator' ) );
        if ( is_wp_error( $id ) ) {
            echo 'refused: ' . $id->get_error_message() . "\n";
            return;
        }
        $user = get_user_by( 'id', (int) $id );
        echo "user {$login} made ({$id})\n";
    }
    if ( is_multisite() ) {
        grant_super_admin( $user->ID );
        echo "super admin on the network\n";
    }
    return;
}

if ( 'delete' === $action ) {
    if ( ! $user ) {
        echo "user {$login}: not there\n";
        return;
    }
    if ( is_multisite() ) {
        revoke_super_admin( $user->ID );
        require_once ABSPATH . 'wp-admin/includes/ms.php';
        wpmu_delete_user( $user->ID );
    } else {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user->ID );
    }
    echo "user {$login} deleted\n";
    return;
}

echo "VGML_ACTION is create or delete\n";
