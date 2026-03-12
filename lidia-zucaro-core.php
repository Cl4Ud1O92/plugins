<?php
/**
 * Plugin Name: Lidia Zucaro Core App & Login
 * Description: Funzionalità core per la PWA: Shortcode Login e Bridge Booking-Loyalty.
 * Version: 1.2
 * Author: Lidia Zucaro Tech Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * 1. Shortcode Login Form [lz_login]
 * Mostra un form di login pulito e reindirizza alla Home.
 */
function lz_login_form_shortcode() {
    if ( is_user_logged_in() ) {
        // Logica intelligente: Se admin -> mostra link dashboard admin
        $current_user = wp_get_current_user();
        $redirect_to = home_url(); // Default home cliente
        
        if ( in_array( 'administrator', (array) $current_user->roles ) || in_array( 'editor', (array) $current_user->roles ) ) {
             $redirect_to = home_url('/gestione/');
        }

        return '<div style="text-align:center; padding: 20px;">
                    <p>Ciao, ' . esc_html( $current_user->display_name ) . '!</p>
                    <a href="' . $redirect_to . '" class="elementor-button elementor-size-sm">Vai alla Tua Area</a>
                    <br><br>
                    <a href="' . wp_logout_url( home_url() ) . '" style="font-size:12px; color:#999;">Esci</a>
                </div>';
    }

    $args = array(
        'echo'           => false,
        'redirect'       => home_url(), // Il redirect viene sovrascritto dalla funzione lz_login_redirect sotto
        'form_id'        => 'lz_loginform',
        'label_username' => __( 'Username o Email' ),
        'label_password' => __( 'Password' ),
        'label_remember' => __( 'Ricordami' ),
        'label_log_in'   => __( 'Accedi' ),
        'id_username'    => 'user_login',
        'id_password'    => 'user_pass',
        'id_remember'    => 'rememberme',
        'id_submit'      => 'wp-submit',
        'remember'       => true,
        'value_username' => isset($_GET['log']) ? sanitize_user($_GET['log']) : '',
        'value_remember' => false
    );
    
    // CSS inline per semplicità
    $css = '<style>
        #lz_loginform { max-width: 320px; margin: 0 auto; }
        #lz_loginform .login-username, #lz_loginform .login-password { margin-bottom: 15px; }
        #lz_loginform label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 14px; }
        #lz_loginform input[type="text"], #lz_loginform input[type="password"] { 
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; 
        }
        #lz_loginform input[type="submit"] { 
            width: 100%; background: #333; color: #fff; border: 0; padding: 12px; border-radius: 8px; font-size: 16px; cursor: pointer; transition: background 0.3s;
        }
        #lz_loginform input[type="submit"]:hover { background: #000; }
        #lz_loginform .login-remember { font-size: 13px; color: #666; margin-bottom: 20px; }
    </style>';

    return $css . wp_login_form( $args );
}
add_shortcode( 'lz_login', 'lz_login_form_shortcode' );


/**
 * 2. Redirect Intelligente al Login
 * Admin -> /gestione/
 * Cliente -> / (Home App)
 */
function lz_login_redirect( $redirect_to, $request, $user ) {
    // Se c'è un errore nel login, $user è un WP_Error object
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        // Se è Amministratore o Editor (Lidia o Staff)
        if ( in_array( 'administrator', $user->roles ) || in_array( 'editor', $user->roles ) ) {
            return home_url( '/gestione/' ); // Pagina Dashboard Admin frontend
        } else {
            return home_url( '/' ); // Home App Cliente
        }
    }
    return $redirect_to;
}
add_filter( 'login_redirect', 'lz_login_redirect', 10, 3 );


/**
 * 3. Bridge Amelia -> GamiPress (Booking Reward)
 * (Codice precedente rimasto uguale per compatibilità futura se torni ad Amelia)
 */
function lz_award_points_on_amelia_hook( $reservation, $bookings ) {
    if ( ! function_exists( 'gamipress_award_points_to_user' ) ) return;

    foreach ( $bookings as $booking ) {
        $user_id = isset( $booking['userId'] ) ? intval( $booking['userId'] ) : 0;
        
        if ( ! $user_id && isset( $booking['customer']['email'] ) ) {
            $user = get_user_by( 'email', $booking['customer']['email'] );
            if ( $user ) $user_id = $user->ID;
        }

        if ( ! $user_id ) continue;

        $new_status = isset( $booking['status'] ) ? $booking['status'] : '';
        
        $points_type = 'punti-fedelta'; 
        $points_amount = 10;

        if ( $new_status === 'approved' || $new_status === 'completed' ) {
            $meta_key = '_amelia_awarded_' . $booking['id'];
            if ( ! get_user_meta( $user_id, $meta_key, true ) ) {
                gamipress_award_points_to_user( $user_id, $points_amount, $points_type, array(
                    'description' => 'Prenotazione #' . $booking['id'],
                    'trigger'     => 'amelia_booking',
                    'reference_id'=> $booking['id']
                ) );
                update_user_meta( $user_id, $meta_key, true );
            }
        }
    }
}
add_action( 'AmeliaAppointmentBookingStatusUpdated', 'lz_award_points_on_amelia_hook', 10, 2 );

/** 
 * 4. Redirect Non-Logged Users to Login
 * Impedisce accesso a dashboard o pagine interne se non loggati
 */
function lz_redirect_non_logged_users() {
    if ( ! is_user_logged_in() && ! is_page('login') && ! is_page('recupera-password') && ! is_admin() ) {
        // Scommenta per attivare il muro di protezione totale
        // wp_redirect( home_url( '/login/' ) ); exit;
    }
}
add_action( 'template_redirect', 'lz_redirect_non_logged_users' );

/**
 * 5. Shortcode Recupero Credenziali Unificate [lz_credenziali]
 * Consente l'accesso tramite link univoco (uid + key) per i nuovi utenti.
 */
function lz_credenziali_shortcode() {
    $uid = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
    $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

    if (!$uid || !$key) {
        return '<div style="text-align:center; padding:40px; color:#666;">
                    <i class="fas fa-link-slash" style="font-size:40px; display:block; margin-bottom:15px;"></i>
                    <p>Link non valido o incompleto.</p>
                </div>';
    }

    $stored_key = get_user_meta($uid, 'lz_access_key', true);

    if (empty($stored_key) || $key !== $stored_key) {
        return '<div style="text-align:center; padding:40px; color:#d9534f;">
                    <i class="fas fa-lock" style="font-size:40px; display:block; margin-bottom:15px;"></i>
                    <p>Chiave di accesso non valida o scaduta.</p>
                </div>';
    }

    $user = get_userdata($uid);
    if (!$user) return '<p>Utente non esistente.</p>';

    $pass = get_user_meta($uid, '_lz_initial_pass', true);
    // Se non c'è la pass iniziale, mostriamo un messaggio di sicurezza
    $pass_display = $pass ? esc_html($pass) : '<em>Già aggiornata o non disponibile</em>';

    $html = '
    <div class="lz-credenziali-card" style="background:#fff; padding:40px; border-radius:20px; box-shadow:0 10px 30px rgba(0,0,0,0.05); text-align:center; max-width:450px; margin:40px auto; border:1px solid #f0f0f0;">
        <div style="font-size:50px; margin-bottom:20px;">🔑</div>
        <h2 style="margin-bottom:10px; font-weight:700;">Ciao, ' . esc_html($user->first_name ?: $user->display_name) . '!</h2>
        <p style="color:#666; margin-bottom:30px;">Ecco i tuoi dati per accedere alla tua area riservata su <strong>Lidia Zucaro</strong>.</p>
        
        <div style="background:#f9f9f9; padding:25px; border-radius:15px; margin-bottom:30px; text-align:left; border-left:5px solid #000;">
            <div style="margin-bottom:15px;">
                <span style="font-size:12px; text-transform:uppercase; color:#999; display:block; letter-spacing:1px;">Username</span>
                <strong style="font-size:18px; color:#333;">' . esc_html($user->user_login) . '</strong>
            </div>
            <div>
                <span style="font-size:12px; text-transform:uppercase; color:#999; display:block; letter-spacing:1px;">Password Temporanea</span>
                <strong style="font-size:18px; color:#333;">' . $pass_display . '</strong>
            </div>
        </div>
        
        <p style="font-size:13px; color:#999; margin-bottom:30px;">Ti consigliamo di cambiare la password dopo il primo accesso.</p>
        
        <a href="' . home_url('/?log=' . urlencode($user->user_login)) . '" style="display:inline-block; background:#000; color:#fff; padding:15px 35px; border-radius:12px; text-decoration:none; font-weight:600; transition:all 0.3s; box-shadow:0 5px 15px rgba(0,0,0,0.1);">
            Accedi ora alla Web App
        </a>
    </div>';

    return $html;
}
add_shortcode('lz_credenziali', 'lz_credenziali_shortcode');
