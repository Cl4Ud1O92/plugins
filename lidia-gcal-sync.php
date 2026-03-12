<?php
/**
 * Lidia Zucaro - Google Calendar 2-Way Sync (API Method 1)
 * Richiede permessi OAuth 2.0 su Google Cloud Console.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LZ_GCal_Sync {

    private $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth';
    private $token_url = 'https://oauth2.googleapis.com/token';
    private $api_url = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    public function __construct() {
        // Aggiungi menu impostazioni
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Listener per OAuth Redirect
        add_action( 'admin_init', array( $this, 'handle_oauth_redirect' ) );

        // Hook per Sync su Appuntamento Confermato/Cancellato
        add_action( 'lz_appointment_status_changed', array( $this, 'sync_appointment_to_gcal' ), 10, 3 );
    }

    public function add_settings_page() {
        add_options_page(
            'Google Calendar Sync',
            'GCal Sync App',
            'manage_options',
            'lz-gcal-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'lz_gcal_group', 'lz_gcal_client_id' );
        register_setting( 'lz_gcal_group', 'lz_gcal_client_secret' );
    }

    public function render_settings_page() {
        $client_id = get_option('lz_gcal_client_id');
        $redirect_uri = admin_url('options-general.php?page=lz-gcal-settings');
        $access_token = get_option('lz_gcal_access_token');
        ?>
        <div class="wrap">
            <h1>Sincronizzazione Google Calendar</h1>
            <p>Per configurare la sincronizzazione, devi creare un'App su <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a>, abilitare le "Google Calendar API" e creare le Credenziali OAuth 2.0.</p>
            
            <table class="form-table">
                <tr>
                    <th>URI di Reindirizzamento Autorizzato (Copia in Google)</th>
                    <td><input type="text" class="regular-text" value="<?php echo esc_url($redirect_uri); ?>" readonly style="background:#f0f0f0;"></td>
                </tr>
            </table>

            <form method="post" action="options.php">
                <?php settings_fields( 'lz_gcal_group' ); ?>
                <?php do_settings_sections( 'lz_gcal_group' ); ?>
                <table class="form-table">
                    <tr valign="top">
                    <th scope="row">Client ID</th>
                    <td><input type="text" name="lz_gcal_client_id" class="regular-text" value="<?php echo esc_attr( get_option('lz_gcal_client_id') ); ?>" /></td>
                    </tr>
                     
                    <tr valign="top">
                    <th scope="row">Client Secret</th>
                    <td><input type="password" name="lz_gcal_client_secret" class="regular-text" value="<?php echo esc_attr( get_option('lz_gcal_client_secret') ); ?>" /></td>
                    </tr>
                </table>
                <?php submit_button('Salva Credenziali'); ?>
            </form>

            <hr>
            <h3>Stato Connessione:</h3>
            <?php if ( $access_token ) : ?>
                <p style="color:green; font-weight:bold;">✅ Connesso a Google Calendar.</p>
                <a href="<?php echo esc_url( add_query_arg('lz_gcal_action', 'disconnect', $redirect_uri) ); ?>" class="button button-secondary">Disconnetti</a>
            <?php else : ?>
                <p style="color:red; font-weight:bold;">❌ Non Connesso.</p>
                <?php if ( $client_id ): ?>
                    <?php 
                        $auth_link = add_query_arg(array(
                            'client_id' => $client_id,
                            'redirect_uri' => urlencode($redirect_uri),
                            'response_type' => 'code',
                            'scope' => 'https://www.googleapis.com/auth/calendar.events',
                            'access_type' => 'offline',
                            'prompt' => 'consent'
                        ), $this->auth_url);
                        // un-encode parameters manually to ensure exact format
                        $auth_link = $this->auth_url . '?client_id=' . $client_id . '&redirect_uri=' . urlencode($redirect_uri) . '&response_type=code&scope=https://www.googleapis.com/auth/calendar.events&access_type=offline&prompt=consent';
                    ?>
                    <a href="<?php echo esc_url($auth_link); ?>" class="button button-primary">Connetti Google Calendar</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_oauth_redirect() {
        if ( ! current_user_can('manage_options') ) return;

        // Disconnect
        if ( isset($_GET['lz_gcal_action']) && $_GET['lz_gcal_action'] == 'disconnect' ) {
            delete_option('lz_gcal_access_token');
            delete_option('lz_gcal_refresh_token');
            delete_option('lz_gcal_token_expires');
            wp_redirect( admin_url('options-general.php?page=lz-gcal-settings') );
            exit;
        }

        // Handle Code response
        if ( isset($_GET['code']) && isset($_GET['page']) && $_GET['page'] == 'lz-gcal-settings' ) {
            $code = sanitize_text_field($_GET['code']);
            $client_id = get_option('lz_gcal_client_id');
            $client_secret = get_option('lz_gcal_client_secret');
            $redirect_uri = admin_url('options-general.php?page=lz-gcal-settings');

            $response = wp_remote_post( $this->token_url, array(
                'body' => array(
                    'code' => $code,
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'redirect_uri' => $redirect_uri,
                    'grant_type' => 'authorization_code'
                )
            ));

            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( isset( $body['access_token'] ) ) {
                    update_option( 'lz_gcal_access_token', $body['access_token'] );
                    if ( isset( $body['refresh_token'] ) ) {
                        update_option( 'lz_gcal_refresh_token', $body['refresh_token'] );
                    }
                    update_option( 'lz_gcal_token_expires', time() + $body['expires_in'] );
                }
            }
            wp_redirect( admin_url('options-general.php?page=lz-gcal-settings') );
            exit;
        }
    }

    private function get_valid_token() {
        $access_token = get_option('lz_gcal_access_token');
        $refresh_token = get_option('lz_gcal_refresh_token');
        $expires = get_option('lz_gcal_token_expires');

        if ( ! $access_token ) return false;

        // Se è scaduto (con 5 min di margine), ri-chiedi
        if ( time() > ( $expires - 300 ) ) {
            $response = wp_remote_post( $this->token_url, array(
                'body' => array(
                    'client_id' => get_option('lz_gcal_client_id'),
                    'client_secret' => get_option('lz_gcal_client_secret'),
                    'refresh_token' => $refresh_token,
                    'grant_type' => 'refresh_token'
                )
            ));

            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                if ( isset( $body['access_token'] ) ) {
                    $access_token = $body['access_token'];
                    update_option( 'lz_gcal_access_token', $access_token );
                    update_option( 'lz_gcal_token_expires', time() + $body['expires_in'] );
                } else {
                    return false; // Error refreshing
                }
            } else {
                return false;
            }
        }
        return $access_token;
    }

    public function sync_appointment_to_gcal( $post_id, $new_status, $old_status ) {
        $token = $this->get_valid_token();
        if ( ! $token ) return; // Non connesso a GCal

        $gcal_id = get_post_meta( $post_id, '_gcal_event_id', true );

        // SE APPROVATO -> CREA/AGGIORNA SU GCAL
        if ( $new_status == 'approved' ) {
            
            $service = get_post_meta($post_id, '_lz_service', true);
            $date = get_post_meta($post_id, '_lz_date', true);
            $time = get_post_meta($post_id, '_lz_time', true);
            $client = get_the_title($post_id);
            $phone = get_post_meta($post_id, '_lz_phone', true);

            // Calcolo durata (fallback 1 ora)
            $start_datetime = $date . 'T' . $time . ':00';
            $end_datetime = date('Y-m-d\TH:i:s', strtotime($start_datetime) + 3600); 

            $event_data = array(
                'summary' => "💇‍♀️ $service - $client",
                'description' => "Appuntamento Prenotato da App\nCliente: $client\nTelefono: $phone\nServizio: $service",
                'start' => array( 'dateTime' => $start_datetime, 'timeZone' => 'Europe/Rome' ),
                'end' => array( 'dateTime' => $end_datetime, 'timeZone' => 'Europe/Rome' ),
            );

            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json'
                ),
                'body' => wp_json_encode($event_data),
                'method' => $gcal_id ? 'PUT' : 'POST'
            );

            $endpoint = $gcal_id ? $this->api_url . '/' . $gcal_id : $this->api_url;
            $res = wp_remote_request( $endpoint, $args );

            if ( ! is_wp_error($res) && wp_remote_retrieve_response_code($res) == 200 ) {
                $body = json_decode(wp_remote_retrieve_body($res), true);
                if ( ! $gcal_id ) {
                    update_post_meta( $post_id, '_gcal_event_id', $body['id'] );
                }
            }
        }

        // SE CANCELLATO/RIFIUTATO -> RIMUOVI DA GCAL
        if ( ( $new_status == 'rejected' || $new_status == 'canceled' ) && $gcal_id ) {
            wp_remote_request( $this->api_url . '/' . $gcal_id, array(
                'headers' => array( 'Authorization' => 'Bearer ' . $token ),
                'method' => 'DELETE'
            ));
            delete_post_meta( $post_id, '_gcal_event_id' );
        }
    }
}

new LZ_GCal_Sync();
