<?php
/**
 * Plugin Name: Lidia Zucaro Custom Booking Engine
 * Description: Sistema di prenotazione su misura con Dashboard Frontend per Gestione Amministrativa e Clienti.
 * Version: 2.1 (FINAL)
 * Author: Lidia Zucaro Tech Team
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Includi Modulo Google Calendar Sync
require_once plugin_dir_path( __FILE__ ) . 'lidia-gcal-sync.php';

// Includi Modulo Firebase FCM Sync
require_once plugin_dir_path( __FILE__ ) . 'lidia-fcm-sync.php';

// Includi Modulo WhatsApp Business Sync
require_once plugin_dir_path( __FILE__ ) . 'lidia-wa-sync.php';

class LZ_Booking_Engine {

    public function __construct() {
        add_action( 'init', array( $this, 'register_custom_post_types' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_shortcode( 'lz_booking_form', array( $this, 'render_booking_form' ) );
        add_shortcode( 'lz_admin_dashboard', array( $this, 'render_admin_dashboard' ) ); // Backend Staff
        add_shortcode( 'lz_app_home', array( $this, 'render_app_home' ) ); // Frontend Clienti PWA
        
        // AJAX Handlers
        add_action( 'wp_ajax_lz_submit_booking', array( $this, 'handle_booking_submission' ) );
        add_action( 'wp_ajax_nopriv_lz_submit_booking', array( $this, 'handle_booking_submission' ) ); // Permetti booking anche ai guest se necessario, ma qui l'app richiede login
        add_action( 'wp_ajax_lz_frontend_login', array( $this, 'handle_frontend_login' ) );
        add_action( 'wp_ajax_nopriv_lz_frontend_login', array( $this, 'handle_frontend_login' ) );
        add_action( 'wp_ajax_nopriv_lz_submit_booking', array( $this, 'handle_booking_submission' ) );
        add_action( 'wp_ajax_lz_update_status', array( $this, 'handle_status_update' ) );
        add_action( 'wp_ajax_lz_add_client', array( $this, 'handle_add_client' ) );
        add_action( 'wp_ajax_lz_search_clients', array( $this, 'handle_search_clients' ) );
        add_action( 'wp_ajax_lz_get_client_details', array( $this, 'handle_get_client_details' ) );
        add_action( 'wp_ajax_lz_manage_points', array( $this, 'handle_manage_points' ) );
        add_action( 'wp_ajax_lz_send_notification', array( $this, 'handle_send_notification' ) );
        add_action( 'wp_ajax_lz_submit_booking_manual', array( $this, 'handle_booking_manual' ) );
        add_action( 'wp_ajax_lz_update_wa_optin', array( $this, 'handle_update_wa_optin' ) );
        add_action( 'wp_ajax_lz_delete_client', array( $this, 'handle_delete_client' ) );
        add_action( 'wp_ajax_lz_edit_client', array( $this, 'handle_edit_client' ) );
        add_action( 'wp_ajax_lz_get_my_dashboard_data', array( $this, 'handle_get_my_dashboard_data' ) );
        add_action( 'wp_ajax_nopriv_lz_get_my_dashboard_data', array( $this, 'handle_get_my_dashboard_data' ) );
        add_action( 'wp_ajax_lz_get_weekly_agenda', array( $this, 'handle_get_weekly_agenda' ) );
        add_action( 'wp_ajax_lz_send_welcome_messages', array( $this, 'handle_send_welcome_messages' ) );
        add_action( 'wp_ajax_lz_send_support_request', array( $this, 'handle_send_support_request' ) );
        add_action( 'wp_ajax_lz_import_clients', array( $this, 'handle_import_clients' ) );
        add_action( 'wp_ajax_lz_change_password', array( $this, 'handle_change_password' ) );
        add_action( 'wp_ajax_lz_wa_admin_reply', array( $this, 'handle_wa_admin_reply' ) );
        add_action( 'wp_ajax_lz_get_booking_details', array( $this, 'handle_get_booking_details' ) );
        add_action( 'wp_ajax_lz_save_user_email', array( $this, 'handle_save_user_email' ) );
        add_action( 'wp_ajax_lz_save_user_birthday', array( $this, 'handle_save_user_birthday' ) );
        add_action( 'wp_ajax_lz_edit_booking', array( $this, 'handle_edit_booking' ) );
        add_action( 'wp_ajax_lz_delete_booking', array( $this, 'handle_delete_booking' ) );
        add_action( 'wp_ajax_lz_request_edit_booking', array( $this, 'handle_request_edit_booking' ) );
        add_action( 'wp_ajax_lz_request_delete_booking', array( $this, 'handle_request_delete_booking' ) );
        add_action( 'wp_ajax_lz_send_group_message', array( $this, 'handle_send_group_message' ) );
        
        // Admin ajax requests per approvare/rifiutare modifiche e annullamenti
        add_action( 'wp_ajax_lz_admin_approve_edit', array( $this, 'handle_admin_approve_edit' ) );
        add_action( 'wp_ajax_lz_admin_reject_edit', array( $this, 'handle_admin_reject_edit' ) );
        add_action( 'wp_ajax_lz_admin_approve_cancel', array( $this, 'handle_admin_approve_cancel' ) );
        add_action( 'wp_ajax_lz_admin_reject_cancel', array( $this, 'handle_admin_reject_cancel' ) );
        add_action( 'wp_ajax_lz_admin_propose_edit', array( $this, 'handle_admin_propose_edit' ) );
        add_action( 'wp_ajax_lz_client_edit_response', array( $this, 'handle_client_edit_response' ) );
        add_action( 'wp_ajax_lz_client_update_profile', array( $this, 'handle_client_update_profile' ) );
        add_action( 'wp_ajax_lz_mark_promo_seen', array( $this, 'handle_mark_promo_seen' ) );
        add_action( 'wp_ajax_lz_save_promo', array( $this, 'handle_save_promo' ) );
        add_action( 'wp_ajax_lz_delete_promo', array( $this, 'handle_delete_promo' ) );
        add_action( 'wp_ajax_lz_get_promo_recipients', array( $this, 'handle_get_promo_recipients' ) );
        
        // Regole Sicurezza: eseguiamo su init quando WordPress ha caricato gli utenti
        add_action( 'init', array($this, 'apply_client_security_restrictions') );
        // Blocca l'accesso fisico al backend wp-admin
        add_action( 'admin_init', array($this, 'restrict_admin_access') );

        // Aggiungi tag HTML nell'HEAD per rendere il sito una vera App Mobile Installabile (PWA)
        add_action('wp_head', array($this, 'inject_pwa_meta_tags'));

        // Serve PWA files (manifest, sw) dynamically
        add_action( 'template_redirect', array( $this, 'handle_pwa_files' ) );

        // Gestione Sub-paths per Dashboard Amministrativa (/gestione/agenda etc.)
        add_action('template_redirect', array($this, 'handle_dashboard_subpaths'));

        // Accessibilità: Link "Salta al contenuto"
        add_action('wp_body_open', array($this, 'add_skip_to_content_link'));
    }

    public function enqueue_assets() {
        // CSS
        wp_enqueue_style( 'lz-app-style', plugin_dir_url( __FILE__ ) . 'assets/css/app-style.css', array(), '2.2' );
        
        // JS
        wp_enqueue_script( 'lz-app-logic', plugin_dir_url( __FILE__ ) . 'assets/js/app-logic.js', array('jquery'), '2.3', true );
        
        // Localize Script per passare variabili PHP a JS in modo sicuro
        wp_localize_script( 'lz-app-logic', 'lzData', array(
            'ajaxurl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce('lz_ajax_nonce'),
            'loginNonce' => wp_create_nonce('lz_login_nonce'),
            'forcedView' => isset($GLOBALS['lz_forced_view']) ? $GLOBALS['lz_forced_view'] : '',
            'adhoc_templates' => array_filter(array_map('trim', explode(',', get_option('lz_wa_adhoc_templates', ''))))
        ) );
    }

    public function handle_dashboard_subpaths() {
        if ( is_admin() ) return;
        
        $path = trim( parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/' );
        $parts = explode('/', $path);
        
        if ( !empty($parts[0]) && $parts[0] === 'gestione' && !empty($parts[1]) ) {
            $sub_view = $parts[1];
            $allowed_views = array('gestioneclienti', 'agenda', 'fidelity', 'webapp', 'messaggi', 'promo');
            
            if ( in_array($sub_view, $allowed_views) ) {
                // Carica la pagina /gestione/ invece di dare 404
                $gestione_page = get_page_by_path('gestione');
                if ( $gestione_page ) {
                    query_posts( array( 'page_id' => $gestione_page->ID ) );
                    // Se la vista è gestioneclienti, internamente usiamo 'clienti'
                    $GLOBALS['lz_forced_view'] = ($sub_view === 'gestioneclienti') ? 'clienti' : $sub_view;
                    
                    // Disabilita il redirect canonico di WP per evitare loop o salti strani
                    remove_action('template_redirect', 'redirect_canonical');
                }
            }
        }
    }

    public function add_skip_to_content_link() {
        if ( ! is_front_page() && ! is_page('gestione') ) return;
        $target = is_user_logged_in() ? '#lz-app-container' : '#lz-pwa-login-screen';
        echo '<a class="lz-skip-link" href="' . esc_url($target) . '">Salta al contenuto principale</a>';
    }

    public function register_custom_post_types() {
        register_post_type( 'lz_appointment', array(
            'labels' => array( 'name' => 'Appuntamenti', 'singular_name' => 'Appuntamento' ),
            'public' => false, 'show_ui' => true, 'supports' => array( 'title', 'custom-fields' ), 'capability_type' => 'post',
        ));

        register_post_type( 'lz_promo', array(
            'labels' => array( 'name' => 'Promozioni', 'singular_name' => 'Promozione' ),
            'public' => false, 'show_ui' => true, 'supports' => array( 'title', 'custom-fields' ), 'capability_type' => 'post',
        ));
    }

    public function inject_pwa_meta_tags() {
        // Tag essenziali per il riconoscimento come PWA nativa su iOS e Android
        echo '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0, viewport-fit=cover">';
        echo '<meta name="apple-mobile-web-app-capable" content="yes">';
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
        echo '<meta name="theme-color" content="#000000">';
        echo '<link rel="manifest" href="/manifest.json">';

        // Meta Pixel Base Code
        echo "<!-- Meta Pixel Code -->
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', '1466172761806176');
        fbq('track', 'PageView');
        </script>
        <noscript><img height=\"1\" width=\"1\" style=\"display:none\"
        src=\"https://www.facebook.com/tr?id=1466172761806176&ev=PageView&noscript=1\"
        /></noscript>
        <!-- End Meta Pixel Code -->";

        // Integrazione Firebase FCM
        echo '<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js"></script>';
        echo '<script src="https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js"></script>';
        echo '<script>
          const firebaseConfig = {
            apiKey: "AIzaSyA9PaNAmrd_jv44wjxNwTvfvg1JehI_WW4",
            authDomain: "lidia-zucaro-app.firebaseapp.com",
            projectId: "lidia-zucaro-app",
            storageBucket: "lidia-zucaro-app.firebasestorage.app",
            messagingSenderId: "250320146866",
            appId: "1:250320146866:web:46602f58e482f4eb7914ab"
          };
          firebase.initializeApp(firebaseConfig);
          const messaging = firebase.messaging();

          if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("/fcm-sw.js").then(function(registration) {
              messaging.getToken({ serviceWorkerRegistration: registration }).then((currentToken) => {
                if (currentToken) {
                  console.log("FCM Token:", currentToken);
                  jQuery.post("' . admin_url('admin-ajax.php') . '", {
                    action: "lz_save_fcm_token",
                    fcm_token: currentToken
                  });
                }
              });
            });
          }

          // Custom Install Prompt Logic
          let deferredPrompt;
          window.addEventListener("beforeinstallprompt", (e) => {
            console.log("PWA: beforeinstallprompt triggered");
            e.preventDefault();
            deferredPrompt = e;
            showPWAInstallBtn();
          });

          function showPWAInstallBtn() {
            const installBtn = document.getElementById("pwa-install-btn");
            if (installBtn && deferredPrompt) {
              installBtn.style.display = "flex";
            }
          }

          // Check if button is ready if event already fired
          window.addEventListener("DOMContentLoaded", showPWAInstallBtn);

          function lzInstallAppNative() {
            if (!deferredPrompt) {
                console.warn("PWA: deferredPrompt not found");
                return;
            }
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
              if (choiceResult.outcome === "accepted") {
                console.log("PWA: User accepted install");
                const installBtn = document.getElementById("pwa-install-btn");
                if (installBtn) installBtn.style.display = "none";
              }
              deferredPrompt = null;
            });
          }
        </script>';
    }

    public function apply_client_security_restrictions() {
        // Nascondi la barra di amministrazione WP per i clienti
        if ( is_user_logged_in() && !current_user_can('edit_posts') ) {
            add_filter('show_admin_bar', '__return_false');
        }
    }

    public function restrict_admin_access() {
        // Se non è una chiamata AJAX e l'utente non può pubblicare articoli (cliente)...
        if ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
            $path = trim( parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/' );
            
            // Se siamo in /gestione/ (anche con sotto-percorsi), non bloccare qui
            if ( strpos($path, 'gestione') === 0 ) return;

            if ( is_user_logged_in() && ! current_user_can( 'edit_posts' ) ) {
                if ( class_exists('LZ_WA_Sync') ) LZ_WA_Sync::lz_log("REDIRECT: restrict_admin_access triggering for " . $_SERVER['REQUEST_URI']);
                wp_redirect( home_url('/clienti/') ); // Reindirizza sempre all'app
                exit;
            }
        }
    }

    // --- FRONTEND DASHBOARD ---
    public function render_admin_dashboard() {
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            return '<div style="text-align:center; padding:50px;">
                        <h3 style="color:red;">⛔️ Accesso Negato</h3>
                        <p>Non hai i permessi per vedere questa pagina.</p>
                        <p><a href="' . wp_login_url() . '">Effettua il Login come Amministratore</a></p>
                    </div>';
        }

        ob_start();

        // --- GESTIONE RICHIESTE MODIFICA/ANNULLAMENTO (URL PARAM) ---
        $edit_request_html = '';
        if (isset($_GET['edit_request'])) {
            $req_id = intval($_GET['edit_request']);
            $req_action = get_post_meta($req_id, '_lz_pending_action', true);
            
            if ($req_action) {
                // Raccogli dati attuali
                $c_title = get_the_title($req_id);
                $old_service = get_post_meta($req_id, '_lz_service', true);
                $old_date = date('d/m/Y', strtotime(get_post_meta($req_id, '_lz_date', true)));
                $old_time = get_post_meta($req_id, '_lz_time', true);

                $title_text = ($req_action === 'cancel') ? "Richiesta Annullamento" : "Richiesta Modifica";
                $icon = ($req_action === 'cancel') ? "🚨" : "🔔";

                $edit_request_html .= '<div id="lz-admin-edit-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">';
                $edit_request_html .= '<div style="background:#fff; width:100%; max-width:400px; border-radius:15px; padding:25px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">';
                
                $edit_request_html .= '<div style="text-align:center; font-size:40px; margin-bottom:10px;">' . $icon . '</div>';
                $edit_request_html .= '<h3 style="text-align:center; margin-bottom:20px; font-weight:800; font-size:22px;">' . $title_text . '</h3>';
                $edit_request_html .= '<p style="color:#666; font-size:14px; text-align:center; margin-bottom:20px;">Il cliente <strong>' . esc_html(str_replace('Manuale: ', '', $c_title)) . '</strong> ha inviato una richiesta.</p>';

                $edit_request_html .= '<div style="background:#f9f9f9; padding:15px; border-radius:10px; margin-bottom:20px; font-size:14px; line-height:1.6;">';
                if ($req_action === 'edit') {
                    $new_service = get_post_meta($req_id, '_lz_pending_service', true);
                    $new_date = date('d/m/Y', strtotime(get_post_meta($req_id, '_lz_pending_date', true)));
                    $new_time = get_post_meta($req_id, '_lz_pending_time', true);
                    
                    $edit_request_html .= '<strong>Servizio:</strong> ' . ($old_service === $new_service ? $old_service : "<del>$old_service</del> &rarr; <span style='color:green; font-weight:bold;'>$new_service</span>") . '<br>';
                    $edit_request_html .= '<strong>Data:</strong> ' . ($old_date === $new_date ? $old_date : "<del>$old_date</del> &rarr; <span style='color:green; font-weight:bold;'>$new_date</span>") . '<br>';
                    $edit_request_html .= '<strong>Ora:</strong> ' . ($old_time === $new_time ? $old_time : "<del>$old_time</del> &rarr; <span style='color:green; font-weight:bold;'>$new_time</span>");
                } else {
                    $edit_request_html .= '<strong>Servizio:</strong> ' . $old_service . '<br>';
                    $edit_request_html .= '<strong>Data:</strong> ' . $old_date . '<br>';
                    $edit_request_html .= '<strong>Ora:</strong> ' . $old_time;
                }
                $edit_request_html .= '</div>';

                $edit_request_html .= '<div id="admin-req-msg" style="display:none; text-align:center; margin-bottom:15px; font-size:14px; font-weight:bold;"></div>';

                $edit_request_html .= '<div style="display:flex; gap:10px;">';
                $edit_request_html .= '<button onclick="handleAdminRequest(' . $req_id . ', \'reject\', \'' . $req_action . '\', this)" style="flex:1; padding:12px; border:1px solid #ddd; border-radius:8px; background:#fff; color:#d9534f; font-weight:600; cursor:pointer;">Non accettare</button>';
                $edit_request_html .= '<button onclick="handleAdminRequest(' . $req_id . ', \'approve\', \'' . $req_action . '\', this)" style="flex:1; padding:12px; border:none; border-radius:8px; background:#000; color:#fff; font-weight:600; cursor:pointer;">Accetta</button>';
                $edit_request_html .= '</div>';
                
                $edit_request_html .= '<div style="text-align:center; margin-top:15px;"><a href="#" onclick="document.getElementById(\'lz-admin-edit-modal\').style.display=\'none\'; return false;" style="color:#aaa; font-size:13px; text-decoration:underline;">Chiudi per ora</a></div>';

                $edit_request_html .= '</div></div>';

                // JS Handler
                $edit_request_html .= "
                <script>
                function handleAdminRequest(id, decision, action_type, btn) {
                    var action = action_type === 'edit' 
                        ? (decision === 'approve' ? 'lz_admin_approve_edit' : 'lz_admin_reject_edit')
                        : (decision === 'approve' ? 'lz_admin_approve_cancel' : 'lz_admin_reject_cancel');
                    
                    var container = jQuery(btn).parent();
                    container.css('opacity', '0.5');
                    container.find('button').prop('disabled', true);
                    
                    jQuery.post('" . admin_url('admin-ajax.php') . "', {
                        action: action,
                        booking_id: id,
                        security: '" . wp_create_nonce('lz_ajax_nonce') . "'
                    }, function(res) {
                        if (res.success) {
                            jQuery('#admin-req-msg').text(res.data).css('color', 'green').show();
                            setTimeout(function() { window.location.href = window.location.pathname; }, 1500);
                        } else {
                            jQuery('#admin-req-msg').text('Errore: ' + res.data).css('color', 'red').show();
                            container.css('opacity', '1').find('button').prop('disabled', false);
                        }
                    }).fail(function() {
                        jQuery('#admin-req-msg').text('Errore di connessione.').css('color', 'red').show();
                        container.css('opacity', '1').find('button').prop('disabled', false);
                    });
                }
                </script>";
            }
        }

        if (isset($_GET['new_booking'])) {
            $req_id = intval($_GET['new_booking']);
            $post = get_post($req_id);
            if ($post && get_post_meta($req_id, '_lz_status', true) === 'pending') {
                $c_title = get_the_title($req_id);
                $service = get_post_meta($req_id, '_lz_service', true);
                $date = date('d/m/Y', strtotime(get_post_meta($req_id, '_lz_date', true)));
                $time = get_post_meta($req_id, '_lz_time', true);

                $edit_request_html .= '<div id="lz-admin-edit-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">';
                $edit_request_html .= '<div style="background:#fff; width:100%; max-width:400px; border-radius:15px; padding:25px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">';
                
                $edit_request_html .= '<div style="text-align:center; font-size:40px; margin-bottom:10px;">🛎️</div>';
                $edit_request_html .= '<h3 style="text-align:center; margin-bottom:20px; font-weight:800; font-size:22px;">Nuova Richiesta Appuntamento</h3>';
                $edit_request_html .= '<p style="color:#666; font-size:14px; text-align:center; margin-bottom:20px;">Il cliente <strong>' . esc_html(str_replace('Manuale: ', '', $c_title)) . '</strong> ha prenotato un appuntamento.</p>';

                $edit_request_html .= '<div style="background:#f9f9f9; padding:15px; border-radius:10px; margin-bottom:20px; font-size:14px; line-height:1.6;">';
                $edit_request_html .= '<strong>Servizio:</strong> ' . esc_html($service) . '<br>';
                $edit_request_html .= '<strong>Data:</strong> ' . esc_html($date) . '<br>';
                $edit_request_html .= '<strong>Ora:</strong> ' . esc_html($time);
                $edit_request_html .= '</div>';

                $edit_request_html .= '<div id="admin-req-msg" style="display:none; text-align:center; margin-bottom:15px; font-size:14px; font-weight:bold;"></div>';

                $edit_request_html .= '<div style="display:flex; gap:10px;">';
                $edit_request_html .= '<button onclick="handleAdminStatusRequest(' . $req_id . ', \'rejected\', this)" style="flex:1; padding:12px; border:1px solid #ddd; border-radius:8px; background:#fff; color:#d9534f; font-weight:600; cursor:pointer;">Rifiuta</button>';
                $edit_request_html .= '<button onclick="handleAdminStatusRequest(' . $req_id . ', \'approved\', this)" style="flex:1; padding:12px; border:none; border-radius:8px; background:#000; color:#fff; font-weight:600; cursor:pointer;">Accetta</button>';
                $edit_request_html .= '</div>';
                
                $edit_request_html .= '<div style="text-align:center; margin-top:15px;"><button onclick="toggleAdvancedEdit(' . $req_id . ')" style="background:transparent; border:none; color:#1a73e8; font-size:15px; font-weight:bold; cursor:pointer; text-decoration:underline;">🛠️ Modifica Avanzata</button></div>';

                // Il form per la proposta di modifica
                // Formatta la data per i campi input type date -> Y-m-d
                $db_date = get_post_meta($req_id, '_lz_date', true);
                $edit_request_html .= '<div id="lz-admin-edit-advanced" style="display:none; background:#f0f0f0; margin-top:15px; padding:15px; border-radius:10px; text-align:left;">';
                $edit_request_html .= '<p style="font-size:13px; color:#555; margin-bottom:10px;">Non puoi accettare questo orario? Proponine uno nuovo al cliente:</p>';
                $edit_request_html .= '<label style="font-size:12px; font-weight:bold;">Servizio:</label><input type="text" id="adv_edit_service" value="'.esc_attr($service).'" style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #ccc; border-radius:5px;">';
                $edit_request_html .= '<label style="font-size:12px; font-weight:bold;">Nuova Data:</label><input type="date" id="adv_edit_date" value="'.esc_attr($db_date).'" style="width:100%; padding:8px; margin-bottom:10px; border:1px solid #ccc; border-radius:5px;">';
                $edit_request_html .= '<label style="font-size:12px; font-weight:bold;">Nuova Ora:</label><input type="time" id="adv_edit_time" value="'.esc_attr($time).'" style="width:100%; padding:8px; margin-bottom:15px; border:1px solid #ccc; border-radius:5px;">';
                $edit_request_html .= '<div style="text-align:right;"><button onclick="submitAdvancedEdit(' . $req_id . ', this)" style="background:#000; color:#fff; padding:8px 15px; border-radius:5px; border:none; cursor:pointer;">Invia Proposta</button></div>';
                $edit_request_html .= '</div>';

                $edit_request_html .= '<div style="text-align:center; margin-top:20px;"><a href="#" onclick="document.getElementById(\'lz-admin-edit-modal\').style.display=\'none\'; return false;" style="color:#aaa; font-size:13px; text-decoration:underline;">Chiudi per ora</a></div>';

                $edit_request_html .= '</div></div>';

                // JS Handler
                $edit_request_html .= "
                <script>
                function handleAdminStatusRequest(id, newStatus, btn) {
                    var container = jQuery(btn).parent();
                    container.css('opacity', '0.5');
                    container.find('button').prop('disabled', true);
                    
                    jQuery.post('" . admin_url('admin-ajax.php') . "', {
                        action: 'lz_update_status',
                        post_id: id,
                        new_status: newStatus,
                        security: '" . wp_create_nonce('lz_ajax_nonce') . "'
                    }, function(res) {
                        if (res.success) {
                            jQuery('#admin-req-msg').text(newStatus === 'approved' ? 'Accettato e cliente avvisato!' : 'Rifiutato e cliente avvisato.').css('color', 'green').show();
                            setTimeout(function() { window.location.href = window.location.pathname; }, 1500);
                        } else {
                            jQuery('#admin-req-msg').text('Errore: ' + res.data).css('color', 'red').show();
                            container.css('opacity', '1').find('button').prop('disabled', false);
                        }
                    }).fail(function() {
                        jQuery('#admin-req-msg').text('Errore di connessione.').css('color', 'red').show();
                        container.css('opacity', '1').find('button').prop('disabled', false);
                    });
                }
                
                function toggleAdvancedEdit(id) {
                    jQuery('#lz-admin-edit-advanced').slideToggle();
                    jQuery('#admin-req-msg').hide();
                }

                function submitAdvancedEdit(id, btn) {
                    var newDate = jQuery('#adv_edit_date').val();
                    var newTime = jQuery('#adv_edit_time').val();
                    var newService = jQuery('#adv_edit_service').val();

                    if (!newDate || !newTime || !newService) {
                        jQuery('#admin-req-msg').text('Compila tutti i campi').css('color', 'red').show();
                        return;
                    }

                    var container = jQuery(btn).parent();
                    container.css('opacity', '0.5');
                    jQuery(btn).prop('disabled', true);

                    jQuery.post('" . admin_url('admin-ajax.php') . "', {
                        action: 'lz_admin_propose_edit',
                        booking_id: id,
                        date: newDate,
                        time: newTime,
                        service: newService,
                        security: '" . wp_create_nonce('lz_ajax_nonce') . "'
                    }, function(res) {
                        if (res.success) {
                            jQuery('#admin-req-msg').text('Proposta inviata al cliente!').css('color', 'green').show();
                            setTimeout(function() { window.location.href = window.location.pathname; }, 1500);
                        } else {
                            jQuery('#admin-req-msg').text('Errore: ' + res.data).css('color', 'red').show();
                            container.css('opacity', '1');
                            jQuery(btn).prop('disabled', false);
                        }
                    }).fail(function() {
                        jQuery('#admin-req-msg').text('Errore di connessione.').css('color', 'red').show();
                        container.css('opacity', '1');
                        jQuery(btn).prop('disabled', false);
                    });
                }
                </script>";
            }
        }
        
        ?>
        <div id="lz-app-container">
            <?php echo $edit_request_html; ?>
            <!-- Header -->
            <header class="lz-app-header">
                <div class="lz-logo"><img src="https://lidiazucaro.it/wp-content/uploads/2026/03/icon-512.png" alt="Lidia Zucaro Logo" style="height: 35px; vertical-align: middle;"></div>
                <a href="<?php echo wp_logout_url( home_url() ); ?>" class="lz-logout-btn"><i class="fa fa-sign-out"></i> Esci</a>
            </header>

            <!-- Main Content -->
            <main class="lz-app-content">
                
                <!-- VISTA: CLIENTI -->
                <div id="view-clienti" class="lz-view">
                    <div class="lz-toolbar-multi">
                        <!-- Riga 1: Ricerca e Nuovo Cliente -->
                        <div class="lz-toolbar-row">
                            <input type="text" id="client-search" class="lz-search-input" placeholder="🔍 Cerca cliente..." onkeyup="searchClients(this.value)">
                            <button class="lz-action-btn lz-btn-primary lz-btn-icon-only" onclick="openNewClientModal()" title="Nuovo Cliente">+</button>
                        </div>
                        <!-- Riga 2: Azioni di Massa -->
                        <div class="lz-toolbar-row">
                            <button class="lz-action-btn lz-btn-success lz-btn-flex" onclick="document.getElementById('excel-file').click()" title="Importa Excel">
                                <span>📥</span> <span>Importa</span>
                            </button>
                            <button class="lz-action-btn lz-btn-teal lz-btn-flex" onclick="openGroupMessageModal()" title="Invia Messaggio di Gruppo">
                                <span>📢</span> <span>Msg. Gruppo</span>
                            </button>
                        </div>
                        <input type="file" id="excel-file" style="display:none;" accept=".xlsx, .xls" onchange="handleExcelImport(event)">
                    </div>
                    <div id="lz-clients-list-container">
                        <p style="text-align:center;color:#999; margin-top: 20px;">Caricamento clienti...</p>
                    </div>
                </div>

                <!-- VISTA: AGENDA -->
                <div id="view-agenda" class="lz-view active">
                    <?php
                        // Calcolo appuntamenti in attesa
                        $pending_count = 0;
                        $pending_args = array(
                            'post_type'      => 'lz_appointment',
                            'posts_per_page' => -1,
                            'fields'         => 'ids',
                            'meta_query'     => array(
                                array('key' => '_lz_status', 'value' => 'pending')
                            )
                        );
                        $pending_query = new WP_Query($pending_args);
                        $pending_count = $pending_query->found_posts;
                        $badge_display = $pending_count > 0 ? 'inline-block' : 'none';
                    ?>
                    <div class="lz-toolbar agenda-toolbar">
                        <div class="lz-sub-tabs">
                            <button class="sub-tab active" onclick="filterAgenda('weekly', this)">Settimana</button>
                            <button class="sub-tab" style="position:relative;" onclick="filterAgenda('pending', this)">
                                In Attesa
                                <span class="lz-pending-badge" id="lz-pending-badge-count" style="display:<?php echo $badge_display; ?>"><?php echo $pending_count; ?></span>
                            </button>
                            <button class="sub-tab" onclick="filterAgenda('all_approved', this)">Tutti</button>
                        </div>
                        <div class="agenda-actions-container">
                            <div class="agenda-nav-controls" id="weekly-nav-controls">
                                <button class="btn-nav" onclick="changeWeek(-1)">◀</button>
                                <span id="current-week-label" style="font-weight:700; font-size:14px; min-width:140px; text-align:center;">Settimana Corrente</span>
                                <button class="btn-nav" onclick="changeWeek(1)">▶</button>
                            </div>
                            <button class="lz-action-btn lz-btn-primary lz-btn-icon-only agenda-add-btn" onclick="openModal('modal-add-booking')" title="Nuovo Appuntamento">+</button>
                        </div>
                    </div>
                    
                    <div id="list-weekly" class="agenda-list">
                        <div id="weekly-grid-container" class="lz-weekly-grid">
                            <!-- Caricato via AJAX -->
                            <p style="text-align:center; padding:50px; color:#999;">Caricamento agenda...</p>
                        </div>
                    </div>
                    <div id="list-pending" class="agenda-list" style="display:none;"><?php $this->render_appointment_list('pending'); ?></div>
                    <div id="list-all_approved" class="agenda-list" style="display:none;"><?php $this->render_appointment_list('all_approved'); ?></div>
                </div>

                <!-- VISTA: PROMO -->
                <div id="view-promo" class="lz-view">
                    <div style="text-align:center; padding: 40px 20px; color:#333;">
                        <div style="font-size:50px; margin-bottom:20px;">✨</div>
                        <h3 style="font-weight:800; font-size:24px; margin-bottom:10px;">Gestione Promozioni</h3>
                        <p style="color:#666; max-width:400px; margin: 0 auto 30px; line-height:1.6;">
                            Crea offerte speciali e inviale ai tuoi clienti tramite WhatsApp. 
                            Puoi usare i template predefiniti o scrivere messaggi personalizzati.
                        </p>
                        
                        <div style="background:#f9f9f9; border-radius:15px; padding:25px; max-width:450px; margin: 0 auto; text-align:left; border:1px solid #eee;">
                            <h4 style="font-size:14px; text-transform:uppercase; color:#999; letter-spacing:1px; margin-bottom:15px;">Azioni Rapide</h4>
                            <button class="lz-action-btn lz-btn-primary full" onclick="openCreatePromoModal()" style="justify-content:center; padding:15px; border-radius:12px; font-weight:700; margin-bottom:12px;">
                                <span>✨</span> Crea Nuova Promo
                            </button>
                            <button class="lz-action-btn lz-btn-teal full" onclick="openGroupMessageModal()" style="justify-content:center; padding:15px; border-radius:12px; font-weight:700; margin-bottom:12px;">
                                <span>📢</span> Invia Promozione di Gruppo
                            </button>
                            <button class="lz-action-btn lz-btn-flex full" onclick="openPromoRecipientsModal()" style="justify-content:center; padding:15px; border-radius:12px; font-weight:700; background:#f0f0f0; color:#333; border:1px solid #ddd;">
                                <span>👥</span> Vedi Destinatari Promo
                            </button>
                        </div>

                        <!-- ELENCO PROMO -->
                        <div style="margin-top:40px; text-align:left; max-width:600px; margin-left:auto; margin-right:auto;">
                            <h4 style="font-size:14px; text-transform:uppercase; color:#999; letter-spacing:1px; margin-bottom:15px; border-bottom:1px solid #eee; padding-bottom:10px;">Le Tue Promozioni</h4>
                            <div id="lz-promo-list-container">
                                <?php 
                                $promos = get_posts(array('post_type' => 'lz_promo', 'posts_per_page' => -1, 'post_status' => 'any'));
                                if ($promos):
                                    foreach ($promos as $p):
                                        $p_id = $p->ID;
                                        $status = get_post_meta($p_id, '_lz_promo_status', true) ?: 'inactive';
                                        $p_badge = get_post_meta($p_id, '_lz_promo_badge', true);
                                        $p_title = get_post_meta($p_id, '_lz_promo_title', true);
                                        $p_desc = get_post_meta($p_id, '_lz_promo_desc', true);
                                        $p_footer = get_post_meta($p_id, '_lz_promo_footer', true);
                                ?>
                                    <div class="promo-list-item" style="background:#fff; border:1px solid #eee; border-radius:12px; padding:15px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 5px rgba(0,0,0,0.02);">
                                        <div>
                                            <div style="font-weight:700; font-size:15px;"><?php echo esc_html($p->post_title); ?> <?php if($status === 'active') echo '<span style="color:green; font-size:10px;">● ATTIVA</span>'; ?></div>
                                            <div style="font-size:12px; color:#888;"><?php echo esc_html($p_badge); ?> - <?php echo esc_html($p_title); ?></div>
                                        </div>
                                        <div style="display:flex; gap:8px;">
                                            <button class="lz-btn-icon-small" onclick="editPromo(<?php echo $p_id; ?>, '<?php echo esc_js($p->post_title); ?>', '<?php echo esc_js($p_badge); ?>', '<?php echo esc_js($p_title); ?>', '<?php echo esc_js($p_desc); ?>', '<?php echo esc_js($p_footer); ?>', '<?php echo $status; ?>')" title="Modifica">✏️</button>
                                            <button class="lz-btn-icon-small" onclick="deletePromo(<?php echo $p_id; ?>, this)" title="Elimina" style="color:red;">🗑️</button>
                                        </div>
                                    </div>
                                <?php 
                                    endforeach;
                                else: 
                                ?>
                                    <p style="text-align:center; color:#999; font-size:13px; padding:20px;">Non hai ancora creato nessuna promozione.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VISTA: MESSAGGI (WhatsApp Inbox) -->
                <div id="view-messaggi" class="lz-view">
                    <div class="lz-section-title">Log Messaggi WhatsApp</div>
                    <div id="wa-messages-list" class="wa-msg-container">
                        <p style="text-align:center; padding:20px; color:#999;">Caricamento messaggi...</p>
                    </div>
                </div>

                <!-- VISTA: WEBAPP -->
                <div id="view-webapp" class="lz-view">
                    <div style="padding: 20px; text-align:center;">
                        <h2 style="margin-bottom:10px;">📲 La Tua WebApp</h2>
                        <p style="color:#666; margin-bottom:30px;">Facilita l'installazione per i tuoi clienti in salone.</p>
                        
                        <div style="background:#fff; padding:30px; border-radius:20px; display:inline-block; box-shadow:0 10px 30px rgba(0,0,0,0.05);">
                            <?php 
                                $webapp_url = home_url('/clienti/');
                                $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($webapp_url);
                            ?>
                            <img src="<?php echo $qr_url; ?>" alt="QR Code WebApp" style="width:200px; height:200px; margin-bottom:15px;">
                            <div style="font-size:12px; color:#999; font-family:monospace;"><?php echo $webapp_url; ?></div>
                        </div>
                        
                        <div style="margin-top:40px; text-align:left; max-width:400px; margin-left:auto; margin-right:auto;">
                            <h3 style="font-size:16px;">Istruzioni per i clienti:</h3>
                            <ul style="list-style:none; padding:0; font-size:14px; color:#444;">
                                <li style="margin-bottom:10px;">1. Inquadra il QR Code con la fotocamera.</li>
                                <li style="margin-bottom:10px;">2. Apri il link nel browser.</li>
                                <li style="margin-bottom:10px;">3. Clicca su <strong>"Installa App"</strong> o <strong>"Aggiungi a Home"</strong>.</li>
                            </ul>
                            <div style="background:#f0f0f0; padding:15px; border-radius:10px; font-size:12px; border-left:4px solid #000;">
                                💡 <strong>Suggerimento:</strong> Stampa questo QR Code e posizionalo alle postazioni o alla cassa!
                            </div>
                        </div>
                    </div>
                </div>

            </main>

            <!-- Bottom Nav -->
            <nav class="lz-bottom-nav">
                <button class="nav-item" onclick="switchView('clienti', this)">
                    <span class="icon">👥</span><span class="label">Clienti</span>
                </button>
                <button class="nav-item active" id="nav-agenda" onclick="switchView('agenda', this)">
                    <span class="icon">📅</span><span class="label">Agenda</span>
                </button>
                <button class="nav-item" onclick="switchView('messaggi', this)">
                    <span class="icon">💬</span><span class="label">Messaggi</span>
                </button>
                <button class="nav-item" onclick="switchView('promo', this)">
                    <span class="icon">✨</span><span class="label">Promo</span>
                </button>
                <button class="nav-item" onclick="switchView('webapp', this)">
                    <span class="icon">📲</span><span class="label">WebApp</span>
                </button>
            </nav>
        </div>

        <!-- MODAL: AGGIUNGI / MODIFICA CLIENTE -->
        <div id="modal-add-client" class="lz-modal">
            <div class="lz-modal-content">
                <span class="close" onclick="closeModal('modal-add-client')">&times;</span>
                <h3 id="client-modal-title">Nuovo Cliente</h3>
                <form id="form-add-client">
                    <input type="hidden" name="user_id" id="edit-user-id" value="">
                    <input type="text" name="first_name" id="edit-first-name" placeholder="Nome" required>
                    <input type="text" name="last_name" id="edit-last-name" placeholder="Cognome" required>
                    <input type="text" name="phone" id="edit-phone" placeholder="Telefono" required>
                    <div id="username-pass-fields">
                        <input type="text" name="username" id="edit-username" placeholder="Username (es. nome.cognome)" required>
                        <input type="password" name="password" id="edit-password" placeholder="Password" required>
                    </div>
                    <input type="email" name="email" id="edit-email" placeholder="Email (Opzionale)">
                    <label style="display:flex; align-items:center; gap:8px; margin: 10px 0;">
                        <input type="checkbox" name="wa_optin" id="edit-wa-optin" value="yes" checked style="width:auto; margin:0;">
                        <span style="font-size:14px;">Invia Notifiche WhatsApp per Appuntamenti</span>
                    </label>
                    <button type="submit" id="btn-save-client" class="btn-ok full">Crea Cliente</button>
                </form>
            </div>
        </div>

        <!-- MODAL: DETTAGLIO CLIENTE -->
        <div id="modal-client-detail" class="lz-modal">
            <div class="lz-modal-content large">
                <span class="close" onclick="closeModal('modal-client-detail')">&times;</span>
                <div id="client-detail-body">Caricamento...</div>
            </div>
        </div>

        <!-- MODAL: MESSAGGIO DI GRUPPO -->
        <div id="modal-group-message" class="lz-modal">
            <div class="lz-modal-content">
                <span class="close" onclick="closeModal('modal-group-message')">&times;</span>
                <h3>📢 Invia Messaggio di Gruppo</h3>
                
                <div id="gm-step-choice">
                    <p style="font-size:14px; color:#666; margin-bottom:15px;">Seleziona il tipo di comunicazione:</p>
                    
                    <label class="gm-choice-item">
                        <input type="radio" name="gm_mode" value="birthday" onchange="switchGroupMode()">
                        <div class="gm-choice-info">
                            <strong>Auguri di Compleanno</strong>
                            <span>Invia oggi ai festeggiati del giorno.</span>
                        </div>
                    </label>
                    
                    <label class="gm-choice-item">
                        <input type="radio" name="gm_mode" value="custom" onchange="switchGroupMode()">
                        <div class="gm-choice-info">
                            <strong>Messaggio Personalizzato</strong>
                            <span>Scegli i destinatari e scrivi il testo.</span>
                        </div>
                    </label>

                    <div id="gm-preview-birthday" style="display:none; margin-top:20px; padding:15px; background:#f9f9f9; border-radius:10px; font-size:13px; border-left:4px solid var(--lz-gold);">
                        <strong>Anteprima Messaggio:</strong><br>
                        <em>"Ciao [nome], tantissimi auguri di buon compleanno! 🎉 Concediti un momento per te: prenota un appuntamento nei prossimi giorni e riceverai uno sconto speciale per festeggiare. Ti aspettiamo in salone! 💇♀️✨"</em>
                    </div>
                </div>

                <div id="gm-step-custom" style="display:none;">
                    <div style="margin-bottom:15px;">
                        <label style="font-size:12px; font-weight:700; color:#999; text-transform:uppercase;">Destinatari</label>
                        <div class="lz-autocomplete-wrapper">
                            <input type="text" id="gm-search-input" placeholder="🔍 Cerca e aggiungi clienti..." onkeyup="groupSearchClients(this.value)">
                            <div id="gm-suggestions" class="lz-suggestions-list"></div>
                        </div>
                        <div id="gm-recipients-pills" style="display:flex; flex-wrap:wrap; gap:5px; margin-top:8px;"></div>
                    </div>

                    <div style="margin-bottom:15px;">
                        <label style="font-size:12px; font-weight:700; color:#999; text-transform:uppercase;">Tipo di Messaggio</label>
                        <select id="gm-template-select" style="width:100%; padding:10px; border-radius:8px; margin-top:5px; border:1px solid #ddd;" onchange="toggleCustomMessageArea()">
                            <option value="custom_text">-- Messaggio di Testo Libero --</option>
                            <?php 
                            $templates = array_filter(array_map('trim', explode(',', get_option('lz_wa_adhoc_templates', ''))));
                            // Aggiungiamo promo_insite e registrazione (per test) se non sono già nella lista
                            if (!in_array('promo_insite', $templates)) {
                                $templates[] = 'promo_insite';
                            }
                            if (!in_array('registrazione', $templates)) {
                                $templates[] = 'registrazione';
                            }
                            foreach ($templates as $tpl): ?>
                                <option value="<?php echo esc_attr($tpl); ?>">Template: <?php echo esc_html($tpl); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="gm-custom-text-area" style="margin-bottom:15px;">
                        <label style="font-size:12px; font-weight:700; color:#999; text-transform:uppercase;">Testo del Messaggio (Ciao [nome], ...)</label>
                        <textarea id="gm-custom-text" placeholder="Scrivi il corpo del messaggio qui..." rows="4" style="width:100%; border-radius:8px; padding:10px; margin-top:5px;"></textarea>
                    </div>
                </div>

                <div style="display:flex; gap:10px; margin-top:25px;">
                    <button class="btn-no" onclick="closeModal('modal-group-message')">Annulla</button>
                    <button id="btn-gm-proceed" class="btn-ok" onclick="submitGroupMessage()" disabled>Procedi</button>
                </div>
            </div>
        </div>

        <!-- MODAL: AGGIUNGI APPUNTAMENTO MANUALE -->
        <div id="modal-add-booking" class="lz-modal">
            <div class="lz-modal-content">
                <span class="close" onclick="closeModal('modal-add-booking')">&times;</span>
                <h3>Nuovo Appuntamento</h3>
                <form id="form-add-manual-booking">
                    <div class="lz-autocomplete-wrapper">
                        <input type="text" name="customer_name" id="manual-customer-name" placeholder="Nome Cliente" autocomplete="off" required>
                        <div id="manual-suggestions" class="lz-suggestions-list"></div>
                    </div>
                    <input type="tel" name="phone" id="manual-customer-phone" placeholder="Telefono (Opzionale)">
                    <select name="service" required style="width:100%; padding:12px; margin:8px 0; border:1px solid #ccc; border-radius:6px;">
                        <option value="">-- Seleziona Trattamento --</option>
                        <option value="Piega">Piega (30 min)</option>
                        <option value="Taglio">Taglio Donna (45 min)</option>
                        <option value="Colore">Colore Base (90 min)</option>
                    </select>
                    <div style="display:flex; gap:10px;">
                        <input type="date" name="date" required style="flex:1;">
                        <input type="time" name="time" required style="flex:1; padding:12px; margin:8px 0; border:1px solid #ccc; border-radius:6px; font-family: inherit;">
                    </div>
                    <!-- Salviamo automaticamente come approvato -->
                    <input type="hidden" name="force_approved" value="1">
                    <button type="submit" class="btn-ok full" style="margin-top:10px;">Salva in Agenda</button>
                </form>
            </div>
        </div>

        <!-- MODAL: CREA PROMO -->
        <div id="modal-create-promo" class="lz-modal">
            <div class="lz-modal-content">
                <span class="close" onclick="closeModal('modal-create-promo')">&times;</span>
                <h3 id="promo-modal-title">✨ Crea Nuova Promo</h3>
                <p style="font-size:13px; color:#666; margin-bottom:20px;">Personalizza il messaggio che i clienti vedono nell'app.</p>
                
                <form id="form-create-promo">
                    <input type="hidden" name="promo_id" id="promo-edit-id" value="">
                    
                    <label style="font-size:12px; font-weight:700; color:#999; text-transform:uppercase;">Nome Interno (es: Promo Estate 2026)</label>
                    <input type="text" name="p_name" id="promo-edit-name" placeholder="Es: Promo Taglio Marzo" required>

                    <label style="font-size:12px; font-weight:700; color:#999; text-transform:uppercase; margin-top:10px; display:block;">Titolo nel Popup</label>
                    <input type="text" name="p_title" id="promo-edit-title" placeholder="Es: Offerta Esclusiva ✨" required>
                    
                    <label style="font-size:12px; font-weight:700; color:#999; text-transform:uppercase; margin-top:10px; display:block;">Badge (testo piccolo in alto)</label>
                    <input type="text" name="p_badge" id="promo-edit-badge" placeholder="Es: Speciale per te" required>
                    
                    <label style="font-size:12px; font-weight:700; color:#999; text-transform:uppercase; margin-top:10px; display:block;">Descrizione (HTML supportato)</label>
                    <textarea name="p_desc" id="promo-edit-desc" rows="4" placeholder="Descrivi l'offerta..." required style="width:100%; padding:10px; border-radius:8px; border:1px solid #ccc; margin-top:5px;"></textarea>
                    
                    <label style="font-size:12px; font-weight:700; color:#999; text-transform:uppercase; margin-top:10px; display:block;">Testo Scadenza / Footer</label>
                    <input type="text" name="p_footer" id="promo-edit-footer" placeholder="Es: Valida per 15 giorni">

                    <label style="display:flex; align-items:center; gap:8px; margin: 15px 0;">
                        <input type="checkbox" name="p_active" id="promo-edit-active" value="active" checked style="width:auto; margin:0;">
                        <span style="font-size:14px; font-weight:700;">Imposta come Promo Attiva</span>
                    </label>
                    
                    <div id="promo-save-msg" style="display:none; text-align:center; margin-top:15px; font-size:14px; font-weight:bold;"></div>
                    
                    <div style="display:flex; gap:10px; margin-top:25px;">
                        <button type="button" class="btn-no" onclick="closeModal('modal-create-promo')">Annulla</button>
                        <button type="submit" id="btn-save-promo" class="btn-ok">Crea Promo</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: DESTINATARI PROMO -->
        <div id="modal-promo-recipients" class="lz-modal">
            <div class="lz-modal-content">
                <span class="close" onclick="closeModal('modal-promo-recipients')">&times;</span>
                <h3>👥 Destinatari Promo InSite</h3>
                <p style="font-size:13px; color:#666; margin-bottom:20px;">
                    Clienti a cui hai inviato l'ultima promo tramite WhatsApp.<br>
                    L'icona occhio verde <span style="color:green; font-weight:bold;">👁️</span> indica che il cliente ha aperto l'app e visto la promo.
                </p>
                <div id="promo-recipients-list" style="max-height:300px; overflow-y:auto; border:1px solid #eee; border-radius:10px; padding:10px; background:#fafafa;">
                    <div style="text-align:center; padding:20px; color:#999;">Caricamento in corso...</div>
                </div>
                <div style="text-align:center; margin-top:15px;">
                    <button class="btn-no" onclick="closeModal('modal-promo-recipients')">Chiudi</button>
                </div>
            </div>
        </div>

        <script>
        function openPromoRecipientsModal() {
            var container = jQuery('#promo-recipients-list');
            container.html('<div style="text-align:center; padding:20px; color:#999;">Caricamento in corso...</div>');
            openModal('modal-promo-recipients');

            jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'lz_get_promo_recipients',
                security: '<?php echo wp_create_nonce("lz_ajax_nonce"); ?>'
            }, function(res) {
                if (res.success) {
                    var users = res.data;
                    if (users.length === 0) {
                        container.html('<div style="text-align:center; padding:20px; color:#999; font-size:13px;">Nessun cliente ha ancora ricevuto una promozione.</div>');
                    } else {
                        var html = '<ul style="list-style:none; padding:0; margin:0;">';
                        users.forEach(function(u) {
                            var icon = u.seen 
                                ? '<span title="Visto" style="color:green; font-size:16px;">👁️</span>' 
                                : '<span title="Non Visto" style="color:#ccc; font-size:16px;">➖</span>';
                            html += '<li style="padding:10px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; background:#fff; margin-bottom:5px; border-radius:5px;">';
                            html += '<strong style="font-size:14px; color:#333;">' + u.name + '</strong>';
                            html += icon;
                            html += '</li>';
                        });
                        html += '</ul>';
                        container.html(html);
                    }
                } else {
                    container.html('<div style="text-align:center; padding:20px; color:red; font-size:13px;">Errore: ' + res.data + '</div>');
                }
            }).fail(function() {
                container.html('<div style="text-align:center; padding:20px; color:red; font-size:13px;">Errore di connessione.</div>');
            });
        }

        function openCreatePromoModal() {
            jQuery('#promo-modal-title').text('✨ Crea Nuova Promo');
            jQuery('#btn-save-promo').text('Crea Promo');
            jQuery('#promo-edit-id').val('');
            jQuery('#form-create-promo')[0].reset();
            openModal('modal-create-promo');
        }

        function editPromo(id, name, badge, title, desc, footer, status) {
            jQuery('#promo-modal-title').text('✏️ Modifica Promo');
            jQuery('#btn-save-promo').text('Salva Modifiche');
            jQuery('#promo-edit-id').val(id);
            jQuery('#promo-edit-name').val(name);
            jQuery('#promo-edit-badge').val(badge);
            jQuery('#promo-edit-title').val(title);
            jQuery('#promo-edit-desc').val(desc);
            jQuery('#promo-edit-footer').val(footer);
            jQuery('#promo-edit-active').prop('checked', status === 'active');
            openModal('modal-create-promo');
        }

        function deletePromo(id, btn) {
            if (!confirm('Sei sicuro di voler eliminare questa promozione?')) return;
            
            var row = jQuery(btn).closest('.promo-list-item');
            row.css('opacity', '0.5');
            
            jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'lz_delete_promo',
                promo_id: id,
                security: '<?php echo wp_create_nonce("lz_ajax_nonce"); ?>'
            }, function(res) {
                if (res.success) {
                    row.fadeOut(300, function() { jQuery(this).remove(); });
                } else {
                    alert('Errore: ' + res.data);
                    row.css('opacity', '1');
                }
            });
        }

        jQuery('#form-create-promo').on('submit', function(e) {
            e.preventDefault();
            var btn = jQuery('#btn-save-promo');
            var msg = jQuery('#promo-save-msg');
            
            btn.prop('disabled', true).css('opacity', '0.5').text('Salvataggio...');
            
            var postData = jQuery(this).serialize() + '&action=lz_save_promo&security=<?php echo wp_create_nonce("lz_ajax_nonce"); ?>';
            
            jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', postData, function(res) {
                btn.prop('disabled', false).css('opacity', '1').text(jQuery('#promo-edit-id').val() ? 'Salva Modifiche' : 'Crea Promo');
                if (res.success) {
                    msg.text('Promo salvata! Ricaricamento...').css('color', 'green').show();
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    msg.text('Errore: ' + res.data).css('color', 'red').show();
                }
            }).fail(function() {
                btn.prop('disabled', false).css('opacity', '1');
                msg.text('Errore di connessione.').css('color', 'red').show();
            });
        });
        </script>
        <div id="modal-booking-detail" class="lz-modal">
            <div class="lz-modal-content" style="border-radius:15px; padding:25px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">
                <span class="close" onclick="closeModal('modal-booking-detail')" style="top:15px; right:20px;">&times;</span>
                
                <div style="text-align:center; font-size:40px; margin-bottom:10px;">🛎️</div>
                <h3 style="text-align:center; margin-bottom:20px; font-weight:800; font-size:22px;">Dettagli Appuntamento</h3>
                
                <form id="form-edit-booking">
                    <input type="hidden" name="booking_id" id="edit-booking-id">
                    <p style="color:#666; font-size:14px; text-align:center; margin-bottom:20px;" id="view-app-client-sentence">
                        Il cliente <strong id="view-app-client"></strong> ha un appuntamento in salone.
                    </p>
                    
                    <div style="background:#f9f9f9; padding:15px; border-radius:10px; margin-bottom:20px;">
                        <label style="font-size:11px; color:#999; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Servizio:</label>
                        <select name="service" id="edit-app-service" required style="width:100%; padding:10px; margin-bottom:12px; border:1px solid #eee; border-radius:8px; background:#fff; font-size:14px; color:#333;">
                            <option value="Piega">Piega</option>
                            <option value="Taglio Donna">Taglio Donna</option>
                            <option value="Colore Base">Colore Base</option>
                        </select>

                        <div style="display:flex; gap:10px;">
                            <div style="flex:1;">
                                <label style="font-size:11px; color:#999; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Data:</label>
                                <input type="date" name="date" id="edit-app-date" required style="width:100%; padding:10px; border:1px solid #eee; border-radius:8px; background:#fff; font-size:14px; color:#333;">
                            </div>
                            <div style="flex:1;">
                                <label style="font-size:11px; color:#999; font-weight:800; text-transform:uppercase; letter-spacing:0.5px;">Ora:</label>
                                <input type="time" name="time" id="edit-app-time" required style="width:100%; padding:10px; border:1px solid #eee; border-radius:8px; background:#fff; font-size:14px; color:#333;">
                            </div>
                        </div>
                    </div>

                    <div style="display:flex; gap:12px; margin-top:20px;">
                        <button type="button" class="btn-no" onclick="deleteBookingFromDetail()" style="flex:1; border:1px solid #ddd; color:#d9534f; border-radius:8px; padding:12px; font-weight:600; background:#fff; font-size:14px;">Elimina</button>
                        <button type="button" id="btn-submit-edit-booking" onclick="handleEditBookingSubmit()" style="flex:1; padding:12px; border:none; border-radius:8px; background:#000; color:#fff; font-weight:600; cursor:pointer; font-size:14px;">Chiudi</button>
                    </div>

                    <div style="text-align:center; margin-top:20px;">
                        <a href="#" onclick="closeModal('modal-booking-detail'); return false;" style="color:#aaa; font-size:13px; text-decoration:underline;">Chiudi per ora</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- SheetJS for Excel Parsing -->
        <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
        <!-- FontAwesome for Icons -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">


        <?php
        return ob_get_clean();
    }

    // --- FRONTEND APP HOME (CLIENTI) ---
    public function render_app_home() {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            
            // RUOLO AMMINISTRATORE -> VAI A GESTIONE (Admin Dashboard)
            // RUOLO AMMINISTRATORE -> VAI A GESTIONE (Admin Dashboard)
            if ( in_array( 'administrator', (array) $user->roles ) || in_array( 'editor', (array) $user->roles ) ) {
                return $this->render_admin_dashboard();
            } 
            
            // RUOLO CLIENTE -> VAI A DASHBOARD CLIENTE
            return $this->render_client_dashboard( $user );
        }

        ob_start();
        ?>
        <div id="lz-pwa-login-screen">
            <div class="lz-login-content">
                <div class="lz-logo-big">
                    <img src="https://lidiazucaro.it/wp-content/uploads/2026/03/icon-512.png" alt="Lidia Zucaro Logo" style="width: 120px; height: auto; margin: 0 auto 30px; display: block;">
                </div>
                
                <h2 class="lz-welcome-text">Benvenuta!<br>Entra nel tuo profilo</h2>
                
                <div id="lz-login-interface">
                    <?php 
                    $prefill_user = isset($_GET['log']) ? sanitize_user($_GET['log']) : ''; 
                    $show_form = !empty($prefill_user);
                    ?>
                    <!-- Bottone Iniziale -->
                    <button id="btn-reveal-login" class="btn-black-big" onclick="revealLogin()" style="<?php echo $show_form ? 'display:none;' : ''; ?>">LOGIN</button>
                    
                    <!-- Form Login (Nascosto inizialmente, mostrato se c'è prefill) -->
                    <form id="lz-login-form" style="<?php echo $show_form ? 'display:block;' : 'display:none;'; ?>" onsubmit="submitLogin(event)">
                        <div class="input-group">
                            <input type="text" id="log_u" name="log" placeholder="Username o Email" value="<?php echo esc_attr($prefill_user); ?>" required>
                        </div>
                        <div class="input-group">
                            <input type="password" id="log_p" name="pwd" placeholder="Password" autofocus required>
                        </div>
                        <button type="submit" class="btn-black-big">ACCEDI</button>
                        <p class="lz-login-error" style="display:none; color:red; margin-top:10px; font-size:14px;"></p>
                    </form>
                </div>

                <div class="lz-footer-links">
                    <a href="https://lidiazucaro.it/privacy-policy/">Privacy Policy</a>
                    <a href="https://lidiazucaro.it/contatti/">Contatti</a>
                </div>
            </div>

            <div class="lz-meta-verification-links">
                <a href="https://lidiazucaro.it/privacy-policy/">Privacy Policy</a>
                <a href="https://lidiazucaro.it/contatti/">Contatti</a>
            </div>
            
            <style>
                #lz-pwa-login-screen {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                    background: #fff; z-index: 9999; display: flex; align-items: center; justify-content: center;
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; text-align: center;
                    padding: 30px; box-sizing: border-box;
                }
                .lz-login-content { width: 100%; max-width: 320px; }
                .lz-logo-big { 
                    margin-bottom: 20px;
                    text-align: center;
                }
                .lz-welcome-text { 
                    font-size: 22px; font-weight: 300; line-height: 1.4; margin-bottom: 50px; color: #333;
                }
                .btn-black-big {
                    width: 100%; padding: 16px; background: #000; color: #fff; 
                    border: none; border-radius: 0; font-size: 14px; font-weight: 700; 
                    text-transform: uppercase; letter-spacing: 1px; cursor: pointer;
                    transition: opacity 0.3s;
                }
                .btn-black-big:active { opacity: 0.8; }
                
                .input-group { margin-bottom: 15px; }
                .input-group input {
                    width: 100%; padding: 14px; border: 1px solid #ddd; background: #fafafa;
                    font-size: 16px; box-sizing: border-box; outline: none; border-radius: 0;
                }
                .input-group input:focus { border-color: #000; background: #fff; }
                
                .lz-footer-links { margin-top: 40px; }
                .lz-forgot-link { 
                    color: #888; text-decoration: none; font-size: 13px; line-height: 1.5; display:block;
                }
                
                .lz-meta-verification-links {
                    position: absolute;
                    bottom: 20px;
                    left: 0;
                    width: 100%;
                    display: flex;
                    justify-content: center;
                    gap: 30px;
                }
                .lz-meta-verification-links a {
                    color: #bbb;
                    text-decoration: none;
                    font-size: 12px;
                    transition: color 0.3s;
                }
                .lz-meta-verification-links a:hover {
                    color: #000;
                }
                
                /* Animations */
                #lz-login-form { animation: fadeIn 0.4s ease; }
                @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

                .lz-skip-link {
                    position: absolute;
                    top: -100px;
                    left: 20px;
                    background: #000;
                    color: #fff;
                    padding: 10px 20px;
                    z-index: 100000;
                    text-decoration: none;
                    font-weight: bold;
                    transition: top 0.3s;
                }
                .lz-skip-link:focus {
                    top: 20px;
                }

                /* PROMO PREMIUM STYLES */
                .lz-promo-popup-card {
                    background: #fff;
                    border-radius: 24px;
                    overflow: hidden;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                }
                .lz-promo-hero {
                    height: 200px;
                    background-size: cover;
                    background-position: center;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    position: relative;
                }
                .lz-promo-hero::after {
                    content: "";
                    position: absolute;
                    top: 0; left: 0; width: 100%; height: 100%;
                    background: linear-gradient(0deg, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 100%);
                }
                .lz-promo-title {
                    color: #fff;
                    font-size: 28px;
                    font-weight: 800;
                    position: relative;
                    z-index: 2;
                    text-shadow: 0 2px 10px rgba(0,0,0,0.3);
                }
                .lz-promo-content {
                    padding: 30px;
                    background: #fff;
                    text-align: center;
                }
                .lz-promo-badge {
                    display: inline-block;
                    padding: 6px 12px;
                    background: var(--lz-gold, #d4af37);
                    color: #000;
                    font-size: 11px;
                    font-weight: 800;
                    text-transform: uppercase;
                    border-radius: 100px;
                    margin-bottom: 15px;
                    letter-spacing: 1px;
                }
                @keyframes lzFadeUp {
                    from { opacity: 0; transform: translateY(30px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                .lz-promo-home-card {
                    background: #000;
                    color: #fff;
                    position: relative;
                    overflow: hidden;
                }
                .lz-promo-home-card::before {
                    content: "✨";
                    position: absolute;
                    top: -10px;
                    right: -10px;
                    font-size: 80px;
                    opacity: 0.1;
                }
                .lz-promo-home-card h3 {
                    color: var(--lz-gold, #d4af37);
                    margin: 10px 0;
                    font-size: 20px;
                }
                .lz-promo-footer {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-top: 20px;
                    padding-top: 15px;
                    border-top: 1px solid rgba(255,255,255,0.1);
                }
                .lz-promo-code {
                    font-family: monospace;
                    font-weight: 700;
                    color: var(--lz-gold, #d4af37);
                    letter-spacing: 1px;
                }
            </style>


        </div>
        <?php
        return ob_get_clean();
    }

    // --- NUOVA DASHBOARD CLIENTE (Home Cliente Loggato) ---
    public function render_client_dashboard( $user ) {
        // Recupera Dati
        $points = (int) get_user_meta($user->ID, '_lz_points', true);
        $user_phone = get_user_meta($user->ID, '_lz_phone', true);
        
        // Recupera Prossimo Appuntamento (Futuro) e solo quelli APPROVATI
        $next_booking = null;
        $meta_query = array('relation' => 'AND');
        
        // Cerca per ID Cliente o per Telefono
        $client_filter = array('relation' => 'OR');
        $client_filter[] = array('key' => '_lz_client_id', 'value' => $user->ID, 'compare' => '=');
        if (!empty($user_phone)) {
            $client_filter[] = array('key' => '_lz_phone', 'value' => $user_phone, 'compare' => '=');
        }
        $meta_query[] = $client_filter;
        
        // Solo Appuntamenti Approvati (ignora in attesa o cancellati)
        $meta_query[] = array('key' => '_lz_status', 'value' => 'approved', 'compare' => '=');

        // Filtra per Data futura o oggi (Named clause for sorting)
        $meta_query['date_clause'] = array(
            'key' => '_lz_date', 
            'value' => date_i18n('Y-m-d'), 
            'compare' => '>=', 
            'type' => 'DATE'
        );
        // Time clause for sorting
        $meta_query['time_clause'] = array(
            'key' => '_lz_time',
            'compare' => 'EXISTS'
        );

        $bq = new WP_Query(array(
            'post_type'      => 'lz_appointment',
            'posts_per_page' => 1,
            'meta_query'     => $meta_query,
            'orderby'        => array(
                'date_clause' => 'ASC',
                'time_clause' => 'ASC'
            )
        ));
        
        if($bq->have_posts()) {
            $bq->the_post();
            $next_booking = array(
                'id'      => get_the_ID(),
                'service' => get_post_meta(get_the_ID(), '_lz_service', true),
                'date'    => date('d/m/Y', strtotime(get_post_meta(get_the_ID(), '_lz_date', true))),
                'raw_date'=> get_post_meta(get_the_ID(), '_lz_date', true),
                'time'    => get_post_meta(get_the_ID(), '_lz_time', true),
            );
            wp_reset_postdata();
        }

        ob_start();
        ?>
        <div id="lz-app-container">
            <?php
            // --- GESTIONE RISPOSTA CLIENTE A PROPOSTA MODIFICA ---
            if (isset($_GET['edit_response'])) {
                $req_id = intval($_GET['edit_response']);
                $post = get_post($req_id);
                // Verifica che l'appuntamento sia di questo utente e sia in stato 'pending_propose'
                if ($post && get_post_meta($req_id, '_lz_client_id', true) == $user->ID && get_post_meta($req_id, '_lz_status', true) === 'pending_propose') {
                    
                    $new_service = get_post_meta($req_id, '_lz_proposed_service', true);
                    $new_date = date('d/m/Y', strtotime(get_post_meta($req_id, '_lz_proposed_date', true)));
                    $new_time = get_post_meta($req_id, '_lz_proposed_time', true);

                    echo '<div id="lz-client-response-modal" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:99999; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box;">';
                    echo '<div style="background:#fff; width:100%; max-width:400px; border-radius:15px; padding:25px; box-shadow:0 10px 30px rgba(0,0,0,0.2);">';
                    
                    echo '<div style="text-align:center; font-size:40px; margin-bottom:10px;">🌟</div>';
                    echo '<h3 style="text-align:center; margin-bottom:10px; font-weight:800; font-size:22px;">Proposta di Modifica</h3>';
                    echo '<p style="color:#666; font-size:14px; text-align:center; margin-bottom:20px;">Il salone ha proposto un nuovo orario per il tuo appuntamento:</p>';

                    echo '<div style="background:#f9f9f9; padding:15px; border-radius:10px; margin-bottom:20px; font-size:14px; line-height:1.6;">';
                    echo '<strong>Servizio:</strong> ' . esc_html($new_service) . '<br>';
                    echo '<strong>Data:</strong> ' . esc_html($new_date) . '<br>';
                    echo '<strong>Ora:</strong> ' . esc_html($new_time);
                    echo '</div>';

                    echo '<div id="client-resp-msg" style="display:none; text-align:center; margin-bottom:15px; font-size:14px; font-weight:bold;"></div>';

                    echo '<div style="display:flex; gap:10px;">';
                    echo '<button onclick="handleClientResponse(' . $req_id . ', \'reject\', this)" style="flex:1; padding:12px; border:1px solid #ddd; border-radius:8px; background:#fff; color:#d9534f; font-weight:600; cursor:pointer;">Rifiuta</button>';
                    echo '<button onclick="handleClientResponse(' . $req_id . ', \'accept\', this)" style="flex:1; padding:12px; border:none; border-radius:8px; background:#000; color:#fff; font-weight:600; cursor:pointer;">Accetta</button>';
                    echo '</div>';

                    echo '<div style="text-align:center; margin-top:20px;"><a href="#" onclick="document.getElementById(\'lz-client-response-modal\').style.display=\'none\'; return false;" style="color:#aaa; font-size:13px; text-decoration:underline;">Chiudi e decidi dopo</a></div>';

                    echo '</div></div>';

                    echo "
                    <script>
                    function handleClientResponse(id, decision, btn) {
                        var container = jQuery(btn).parent();
                        container.css('opacity', '0.5');
                        container.find('button').prop('disabled', true);
                        
                        jQuery.post('" . admin_url('admin-ajax.php') . "', {
                            action: 'lz_client_edit_response',
                            booking_id: id,
                            decision: decision,
                            security: '" . wp_create_nonce('lz_ajax_nonce') . "'
                        }, function(res) {
                            if (res.success) {
                                jQuery('#client-resp-msg').text(decision === 'accept' ? 'Appuntamento Confermato!' : 'Appuntamento Annullato').css('color', 'green').show();
                                setTimeout(function() { window.location.href = window.location.pathname; }, 1500);
                            } else {
                                jQuery('#client-resp-msg').text('Errore: ' + res.data).css('color', 'red').show();
                                container.css('opacity', '1').find('button').prop('disabled', false);
                            }
                        }).fail(function() {
                            jQuery('#client-resp-msg').text('Errore di connessione.').css('color', 'red').show();
                            container.css('opacity', '1').find('button').prop('disabled', false);
                        });
                    }
                    </script>";
                }
            }
            
            // --- Header ---
            echo '
            <header class="lz-app-header">
                <div class="lz-logo"><img src="https://lidiazucaro.it/wp-content/uploads/2026/03/icon-512.png" alt="Lidia Zucaro Logo" style="height: 35px; vertical-align: middle;"></div>
                <a href="' . wp_logout_url( home_url() ) . '" class="lz-logout-btn"><i class="fa fa-sign-out"></i> ESCI</a>
            </header>';

            // --- PROMO POPUP PREMIUM ---
            $promo_received = get_user_meta($user->ID, '_lz_promo_insite_received', true);
            $promo_seen = get_user_meta($user->ID, '_lz_promo_insite_seen', true);

            // Trova la promo attiva più recente
            $active_promos = get_posts(array(
                'post_type' => 'lz_promo',
                'posts_per_page' => 1,
                'meta_query' => array(
                    array('key' => '_lz_promo_status', 'value' => 'active')
                )
            ));

            if ($promo_received === 'yes' && $promo_seen === 'no' && !empty($active_promos)) {
                $active_p = $active_promos[0];
                $p_id = $active_p->ID;
                $p_title = get_post_meta($p_id, '_lz_promo_title', true);
                $p_badge = get_post_meta($p_id, '_lz_promo_badge', true);
                $p_desc = get_post_meta($p_id, '_lz_promo_desc', true);
                $p_footer = get_post_meta($p_id, '_lz_promo_footer', true);
                
                $promo_bg = plugin_dir_url(__FILE__) . 'assets/img/promo-bg-premium.png';
                echo '
                <div id="lz-promo-popup" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.85); z-index:1000002; display:flex; align-items:center; justify-content:center; padding:20px; box-sizing:border-box; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);">
                    <div class="lz-promo-popup-card" style="width:100%; max-width:400px; position:relative; animation: lzFadeUp 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);">
                        <div class="lz-promo-hero" style="background-image: url(\'' . $promo_bg . '\');">
                            <h2 class="lz-promo-title">' . esc_html($p_title) . '</h2>
                            <span onclick="closePromoPopup()" style="position:absolute; top:15px; right:15px; width:36px; height:36px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:24px; cursor:pointer; z-index:10; backdrop-filter: blur(5px);">&times;</span>
                        </div>
                        <div class="lz-promo-content">
                            <div class="lz-promo-badge">' . esc_html($p_badge) . '</div>
                            <p style="color:#333; font-size:17px; line-height:1.6; margin-bottom:25px; font-weight: 500;">
                                ' . wp_kses_post($p_desc) . '
                            </p>
                            <button onclick="closePromoPopup()" class="btn-black" style="width:100%; border-radius:15px; padding:18px;">SCOPRI DI PIÙ</button>
                            <p style="font-size:12px; color:#888; margin-top:15px;">' . esc_html($p_footer) . '</p>
                        </div>
                    </div>
                </div>
                <script>
                function closePromoPopup() {
                    jQuery(\'#lz-promo-popup\').fadeOut(400);
                    jQuery.post(lzData.ajaxurl, {
                        action: \'lz_mark_promo_seen\',
                        security: lzData.nonce
                    });
                }
                </script>';
            }
            ?>

            <!-- Main Content Area with View Switching Logic -->
            <main class="lz-app-content">
                
                <!-- TAB 1: HOME -->
                <div id="tab-home" class="lz-tab-view active">
                    <div class="lz-welcome-banner">
                        <div class="banner-title">Ciao, <?php echo esc_html($user->first_name ?: $user->display_name); ?></div>
                        <p>Benvenuta nel tuo spazio dedicato.</p>
                    </div>

                    <?php if ( get_user_meta($user->ID, '_lz_first_login_done', true) !== 'yes' ): ?>
                    <div id="lz-password-reminder" class="lz-premium-card reminder">
                        <span class="card-label">🔒 SICUREZZA</span>
                        <p style="margin-bottom:15px;">Per proteggere il tuo account, ti consigliamo di cambiare la password predefinita.</p>
                        <button class="btn-black-small" onclick="switchTab('profilo')">CAMBIA ORA</button>
                    </div>
                    <?php endif; ?>

                    <div id="lz-next-app-container">
                        <?php if($next_booking): ?>
                        <div class="lz-premium-card next-app" onclick="openManageModal(<?php echo $next_booking['id']; ?>, '<?php echo esc_js($next_booking['service']); ?>', '<?php echo esc_js($next_booking['raw_date']); ?>', '<?php echo esc_js($next_booking['time']); ?>')" style="cursor: pointer;">
                            <span class="card-label">PROSSIMO APPUNTAMENTO</span>
                            <div class="card-large-text"><?php echo $next_booking['date']; ?> <small>alle</small> <?php echo $next_booking['time']; ?></div>
                            <div class="card-sub-text"><?php echo $next_booking['service']; ?></div>
                            
                            <button class="lz-cal-btn" onclick="event.stopPropagation(); addToCalendar('<?php echo esc_js($next_booking['service']); ?>', '<?php echo esc_js($next_booking['date']); ?>', '<?php echo esc_js($next_booking['time']); ?>')">
                                <i class="fa fa-calendar-plus-o"></i> AGGIUNGI AL CALENDARIO
                            </button>
                        </div>
                        <?php else: ?>
                        <div class="lz-premium-card" style="text-align:center; padding: 40px 20px;">
                            <div style="font-size:40px; margin-bottom:15px;">🗓️</div>
                            <p style="color:#888; margin-bottom:20px;">Non hai appuntamenti in programma.</p>
                            <button class="btn-black" onclick="openModal('modal-prenota-cliente')">PRENOTA ORA</button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($promo_received === 'yes'): ?>
                    <!-- BLOCCO PROMO PREMIUM IN HOME PAGE -->
                    <div class="lz-premium-card lz-promo-home-card" style="margin-top:20px;">
                        <div style="position:relative; z-index:2;">
                            <span class="card-label">PROMOZIONE ATTIVA</span>
                            <h3>Sconto 20% per te</h3>
                            <p>Utilizza questa promozione esclusiva per il tuo prossimo servizio in salone. Valida su tutti i trattamenti completi.</p>
                            
                            <div class="lz-promo-footer">
                                <div class="lz-promo-code">CODICE: PROMO20</div>
                                <button class="btn-black-small" style="background:var(--lz-gold); color:#000; border-radius:8px;" onclick="openModal('modal-prenota-cliente')">PRENOTA ORA</button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>


                    <!-- MODAL: Nuova Prenotazione (dal tab Home) -->
                    <div id="modal-prenota-cliente" class="lz-modal">
                        <div class="lz-modal-content">
                            <span class="close" onclick="closeModal('modal-prenota-cliente')">×</span>
                            <h3 style="margin-bottom:20px; font-size:18px; font-weight:800;">📅 Nuova Prenotazione</h3>
                            <?php echo $this->render_booking_form(); ?>
                        </div>
                    </div>

                    <!-- MODAL: Gestisci Prossimo Appuntamento (dal tab Home) -->
                    <div id="modal-manage-appointment" class="lz-modal">
                        <div class="lz-modal-content" style="padding: 25px 20px;">
                            
                            <!-- VISTA: Scelta Iniziale -->
                            <div id="manage-app-default">
                                <div style="display:flex; align-items:center; margin-bottom: 25px; cursor: pointer; color: #888;" onclick="closeModal('modal-manage-appointment')">
                                    <i class="fa fa-arrow-left" style="margin-right: 10px;"></i>
                                    <span style="font-weight: 600; font-size: 15px;">Indietro</span>
                                </div>
                                <h3 style="margin-bottom:10px; font-size:22px; font-weight:800;">Gestisci Appuntamento</h3>
                                <p style="font-size:14px; color:#888; margin-bottom: 25px;">Scegli un'opzione per il tuo prossimo appuntamento in salone.</p>
                                
                                <button class="btn-black" style="width: 100%; margin-bottom: 15px; border-radius:12px; font-size:15px; padding:15px;" onclick="switchManageView('edit')">Modifica appuntamento</button>
                                <button class="btn-no" style="width: 100%; font-size:15px; font-weight:600; padding:15px; border-radius:12px; background:#fff; border: 1px solid #ddd; color:#d9534f;" onclick="switchManageView('cancel')">Annulla appuntamento</button>
                            </div>

                            <!-- VISTA: Modifica Appuntamento -->
                            <div id="manage-app-edit" style="display:none;">
                                <div style="display:flex; align-items:center; margin-bottom: 25px; cursor: pointer; color: #888;" onclick="switchManageView('default')">
                                    <i class="fa fa-arrow-left" style="margin-right: 10px;"></i>
                                    <span style="font-weight: 600; font-size: 15px;">Indietro</span>
                                </div>
                                <h3 style="margin-bottom:20px; font-size:22px; font-weight:800;">Modifica Appuntamento</h3>
                                
                                <form id="form-manage-edit">
                                    <input type="hidden" id="edit-app-id">
                                    <div class="form-group">
                                        <label>Trattamento</label>
                                        <select id="edit-app-service" style="width:100%; padding:13px; border:1px solid #eee; border-radius:8px; font-size:15px; margin-bottom:15px; box-sizing:border-box;">
                                            <option value="Piega">Piega (30 min) - 20€</option>
                                            <option value="Taglio">Taglio Donna (45 min) - 35€</option>
                                            <option value="Colore">Colore Base (90 min) - 50€</option>
                                        </select>
                                    </div>
                                    <div style="display:flex; gap:10px; margin-bottom:15px;">
                                        <div style="flex:1;">
                                            <label style="display:block; margin-bottom:5px; font-size:13px; color:#666; font-weight:700;">Data</label>
                                            <input type="date" id="edit-app-date" style="width:100%; padding:13px; border:1px solid #eee; border-radius:8px; font-size:15px; box-sizing:border-box;" min="<?php echo date('Y-m-d'); ?>">
                                        </div>
                                        <div style="flex:1;">
                                            <label style="display:block; margin-bottom:5px; font-size:13px; color:#666; font-weight:700;">Ora</label>
                                            <input type="time" id="edit-app-time" style="width:100%; padding:13px; border:1px solid #eee; border-radius:8px; font-size:15px; box-sizing:border-box;">
                                        </div>
                                    </div>
                                    <div id="edit-app-msg" style="font-size:13px; margin-bottom:15px; display:none;"></div>
                                    <div style="display:flex; gap:10px; margin-top:20px;">
                                        <button type="button" class="btn-no" onclick="switchManageView('default')" style="flex:1;">Annulla</button>
                                        <button type="button" class="btn-ok" onclick="submitManageEdit(this)" style="flex:1;">Richiedi modifica</button>
                                    </div>
                                </form>
                            </div>

                            <!-- VISTA: Annulla Appuntamento -->
                            <div id="manage-app-cancel" style="display:none; text-align:center;">
                                <div style="display:flex; align-items:center; margin-bottom: 25px; cursor: pointer; color: #888;" onclick="switchManageView('default')">
                                    <i class="fa fa-arrow-left" style="margin-right: 10px;"></i>
                                    <span style="font-weight: 600; font-size: 15px;">Indietro</span>
                                </div>
                                <div style="font-size:40px; margin-bottom:15px;">⚠️</div>
                                <h3 style="margin-bottom:10px; font-size:20px; font-weight:800; color:#d9534f;">Attenzione</h3>
                                <p style="font-size:15px; color:#666; margin-bottom: 25px;">Sei sicura di voler annullare l'appuntamento? Questa azione non può essere revocata.</p>
                                
                                <div id="cancel-app-msg" style="font-size:13px; margin-bottom:15px; display:none;"></div>
                                <div style="display:flex; gap:10px;">
                                    <button class="btn-no" onclick="switchManageView('default')" style="flex:1;">NO</button>
                                    <button class="btn-ok" style="flex:1; background:#d9534f; color:#fff; border:none;" onclick="submitManageCancel(this)">SI</button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- TAB 2: FIDELITY -->
                <div id="tab-fidelity" class="lz-tab-view">
                    <div class="lz-welcome-banner" style="margin-bottom: 20px;">
                        <div class="banner-title">Fidelity Card</div>
                        <p>Raccogli i timbri e ricevi un omaggio!</p>
                    </div>
                    
                    <div id="lz-fidelity-card-container">
                        <div class="lz-fidelity-card" style="margin-bottom: 30px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
                                <div style="font-size:12px; font-weight:800; letter-spacing:1px; opacity:0.8;">PUNTI ACCUMULATI</div>
                                <div class="points-count"><?php echo $points; ?> / 9</div>
                            </div>
                            
                            <div class="lz-timbri-grid">
                                <?php for($i=1; $i<=9; $i++): ?>
                                    <div class="lz-timbro <?php echo ($points >= $i) ? 'active' : ''; ?>">
                                        <?php if($points >= $i) echo '<svg viewBox="0 0 24 24" style="width:20px; height:20px; fill:#000;"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'; ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                            
                            <div style="margin-top:40px;">
                                <div style="font-size:11px; margin-bottom:8px; opacity:0.7;">PROSSIMO OMAGGIO AL RAGGIUNGIMENTO DI 9 TIMBRI</div>
                                <div style="height:6px; background:rgba(255,255,255,0.1); border-radius:10px; overflow:hidden;">
                                    <div style="height:100%; background:var(--lz-gold); width:<?php echo ($points/9)*100; ?>%; transition:width 1s ease-out;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lz-premium-card">
                        <h3 style="margin-bottom:10px; font-size:18px;">Come funziona?</h3>
                        <p style="color:#666; font-size:14px; line-height:1.5;">Ricevi un timbro per ogni visita in salone. Al raggiungimento del 9° timbro, Lidia ti riserverà un trattamento omaggio speciale.</p>
                    </div>
                </div>

                <!-- TAB 3: PROFILO -->
                <div id="tab-profilo" class="lz-tab-view">

                    <!-- Le mie info - Accordion -->
                    <?php
                    $lz_birthday    = get_user_meta($user->ID, '_lz_birthday', true);
                    $lz_has_email   = !empty($user->user_email) && strpos($user->user_email, '@') !== false && strpos($user->user_email, 'placeholder') === false;
                    ?>
                    <div class="lz-accordion-card" id="accordion-info" style="margin-bottom: 20px;">
                        <div class="lz-accordion-header" onclick="toggleAccordion('accordion-info')">
                            <div style="display:flex; align-items:center; gap:12px; flex:1;">
                                <span style="font-size:22px;">👤</span>
                                <div style="flex:1;">
                                    <div style="font-weight:800; font-size:16px;">Le mie info</div>
                                    <div style="font-size:12px; color:#999; margin-top:1px;">Profilo personale e sicurezza</div>
                                </div>
                            </div>
                            <span class="lz-accordion-chevron" id="chevron-accordion-info" style="margin-left:10px;">▾</span>
                        </div>

                        <div class="lz-accordion-body" id="body-accordion-info" style="display:none;">
                            <div style="height:1px; background:#f0f0f0; margin-bottom:20px;"></div>

                            <div id="lz-profile-view">
                            <!-- Nome e Cognome -->
                            <div class="lz-info-row">
                                <div class="lz-info-label">Nome e Cognome</div>
                                <div class="lz-info-value"><?php echo esc_html($user->display_name); ?></div>
                            </div>

                            <!-- Cellulare -->
                            <?php $phone_display = get_user_meta($user->ID, 'billing_phone', true); ?>
                            <div class="lz-info-row">
                                <div class="lz-info-label">Cellulare</div>
                                <div class="lz-info-value"><?php echo $phone_display ? esc_html($phone_display) : '<span style="color:#bbb;">Non inserito</span>'; ?></div>
                            </div>

                            <!-- Email -->
                            <div class="lz-info-row">
                                <div class="lz-info-label">Email</div>
                                <div class="lz-info-value">
                                    <?php if($lz_has_email): ?>
                                        <?php echo esc_html($user->user_email); ?>
                                    <?php else: ?>
                                        <span style="color:#D4A956; font-weight:600; cursor:pointer; text-decoration:underline;" onclick="openModal('modal-add-email')">+ Aggiungi email</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Data di compleanno -->
                            <div class="lz-info-row">
                                <div class="lz-info-label">Compleanno</div>
                                <div class="lz-info-value">
                                    <?php if($lz_birthday): ?>
                                        <?php echo esc_html(date('d/m/Y', strtotime($lz_birthday))); ?>
                                    <?php else: ?>
                                        <span style="color:#D4A956; font-weight:600; cursor:pointer; text-decoration:underline;" onclick="openModal('modal-add-birthday')">+ Aggiungi data</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="text-align: right; margin-top: 15px;">
                                <button type="button" onclick="window.lzToggleProfileEdit(event);" id="btn-edit-profile" style="padding: 10px 20px; background: #fff; color: #000; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; display: inline-block;">MODIFICA</button>
                            </div>

                            </div> <!-- Fine #lz-profile-view -->

                            <!-- Form di Modifica (Nascosto di default) -->
                            <?php
                                $first_name = get_user_meta($user->ID, 'first_name', true);
                                $last_name  = get_user_meta($user->ID, 'last_name', true);
                                // Fallback se first/last name sono vuoti
                                if(empty($first_name) && empty($last_name)) {
                                    $parts = explode(' ', $user->display_name);
                                    $first_name = $parts[0];
                                    $last_name = isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : '';
                                }
                            ?>
                            <div id="lz-profile-edit-form" style="display:none; margin-top:10px;">
                                <div style="margin-bottom:12px;">
                                    <label style="font-size:11px; font-weight:bold; color:#666; display:block; margin-bottom:4px;">Nome</label>
                                    <input type="text" id="edit_first_name" value="<?php echo esc_attr($first_name); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; box-sizing:border-box;">
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label style="font-size:11px; font-weight:bold; color:#666; display:block; margin-bottom:4px;">Cognome</label>
                                    <input type="text" id="edit_last_name" value="<?php echo esc_attr($last_name); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; box-sizing:border-box;">
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label style="font-size:11px; font-weight:bold; color:#666; display:block; margin-bottom:4px;">Cellulare</label>
                                    <input type="text" id="edit_phone_val" value="<?php echo esc_attr($phone_display); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; box-sizing:border-box;">
                                </div>
                                <div style="margin-bottom:12px;">
                                    <label style="font-size:11px; font-weight:bold; color:#666; display:block; margin-bottom:4px;">Email</label>
                                    <input type="email" id="edit_email_val" value="<?php echo $lz_has_email ? esc_attr($user->user_email) : ''; ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; box-sizing:border-box;">
                                </div>
                                <div style="margin-bottom:15px;">
                                    <label style="font-size:11px; font-weight:bold; color:#666; display:block; margin-bottom:4px;">Compleanno</label>
                                    <input type="date" id="edit_birthday_val" value="<?php echo esc_attr($lz_birthday); ?>" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px; font-size:14px; box-sizing:border-box;">
                                </div>
                                
                                <div id="edit-profile-msg" style="display:none; font-size:12px; margin-bottom:10px; text-align:center;"></div>
                                
                                <div style="display:flex; gap:10px;">
                                    <button type="button" onclick="window.lzToggleProfileEdit(event);" style="flex:1; padding:10px; background:#f0f0f0; border:none; border-radius:6px; font-weight:bold; color:#333; cursor:pointer;">Annulla</button>
                                    <button type="button" onclick="saveClientProfile(this)" style="flex:1; padding:10px; background:#000; border:none; border-radius:6px; font-weight:bold; color:#fff; cursor:pointer;">Salva</button>
                                </div>
                            </div>
                            
                            <script>
                            window.lzToggleProfileEdit = function(e) {
                                if(e) {
                                    e.preventDefault();
                                }
                                var view = document.getElementById('lz-profile-view');
                                var form = document.getElementById('lz-profile-edit-form');
                                var btn = document.getElementById('btn-edit-profile');
                                
                                if(view && form) {
                                    // Determina se stiamo nascondendo la view per mostrare il form, o viceversa
                                    var isViewVisible = (window.getComputedStyle(view).display !== 'none');
                                    
                                    if(isViewVisible) {
                                        view.style.display = 'none';
                                        form.style.display = 'block';
                                        if(btn) btn.style.display = 'none';
                                    } else {
                                        form.style.display = 'none';
                                        view.style.display = 'block';
                                        if(btn) btn.style.display = 'inline-block';
                                    }
                                }
                            };

                            window.saveClientProfile = function(btn) {
                                var container = jQuery(btn).parent();
                                container.css('opacity', '0.5');
                                jQuery(btn).prop('disabled', true);
                                
                                jQuery.post('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                                    action: 'lz_client_update_profile',
                                    first_name: jQuery('#edit_first_name').val(),
                                    last_name: jQuery('#edit_last_name').val(),
                                    phone: jQuery('#edit_phone_val').val(),
                                    email: jQuery('#edit_email_val').val(),
                                    birthday: jQuery('#edit_birthday_val').val(),
                                    security: '<?php echo wp_create_nonce('lz_ajax_nonce'); ?>'
                                }, function(res) {
                                    if (res.success) {
                                        jQuery('#edit-profile-msg').text('Profilo aggiornato!').css('color', 'green').show();
                                        setTimeout(function() { window.location.reload(); }, 1000);
                                    } else {
                                        jQuery('#edit-profile-msg').text('Errore: ' + res.data).css('color', 'red').show();
                                        container.css('opacity', '1');
                                        jQuery(btn).prop('disabled', false);
                                    }
                                }).fail(function() {
                                    jQuery('#edit-profile-msg').text('Errore di connessione.').css('color', 'red').show();
                                    container.css('opacity', '1');
                                    jQuery(btn).prop('disabled', false);
                                });
                            }
                            </script>

                            <!-- Sicurezza / Cambio Password -->
                            <div style="height:1px; background:#f0f0f0; margin: 20px 0;"></div>
                            <div style="font-size:11px; font-weight:800; color:#999; text-transform:uppercase; letter-spacing:1.5px; margin-bottom:15px;">🔒 Sicurezza</div>
                            <p style="font-size:13px; margin-bottom:15px; color:#666;">Aggiorna la tua password per una maggiore sicurezza.</p>
                            <input type="password" id="new_pwd" placeholder="Nuova Password (min. 6 car.)" style="width:100%; padding:12px; margin-bottom:10px; border:1px solid #eee; border-radius:8px; box-sizing:border-box;">
                            <input type="password" id="confirm_pwd" placeholder="Conferma Password" style="width:100%; padding:12px; margin-bottom:15px; border:1px solid #eee; border-radius:8px; box-sizing:border-box;">
                            <button class="btn-black-small" onclick="changePassword(this)">AGGIORNA PASSWORD</button>
                            <div id="pwd-msg" style="margin-top:10px; font-size:13px; display:none;"></div>
                        </div>
                    </div>

                    <!-- Modal: Aggiungi Email -->
                    <div id="modal-add-email" class="lz-modal">
                        <div class="lz-modal-content">
                            <span class="close" onclick="closeModal('modal-add-email')">&times;</span>
                            <h3 style="margin-bottom:5px; font-size:18px; font-weight:800;">✉️ Aggiungi Email</h3>
                            <p style="font-size:13px; color:#999; margin-bottom:20px;">Inserisci il tuo indirizzo email per ricevere comunicazioni.</p>
                            <input type="email" id="new-email-input" placeholder="tuaemail@esempio.it" style="width:100%; padding:13px; border:1px solid #eee; border-radius:8px; font-size:15px; box-sizing:border-box; margin-bottom:5px;">
                            <div id="email-modal-msg" style="font-size:13px; margin-bottom:15px; display:none;"></div>
                            <div style="display:flex; gap:10px; margin-top:15px;">
                                <button class="btn-no" onclick="closeModal('modal-add-email')" style="flex:1;">Annulla</button>
                                <button class="btn-ok" onclick="saveUserEmail(this)" style="flex:1;">Salva</button>
                            </div>
                        </div>
                    </div>

                    <!-- Modal: Aggiungi Compleanno -->
                    <div id="modal-add-birthday" class="lz-modal">
                        <div class="lz-modal-content">
                            <span class="close" onclick="closeModal('modal-add-birthday')">&times;</span>
                            <h3 style="margin-bottom:5px; font-size:18px; font-weight:800;">🎂 Aggiungi Compleanno</h3>
                            <p style="font-size:13px; color:#999; margin-bottom:20px;">Inserisci la tua data di nascita per ricevere sorprese speciali.</p>
                            <input type="date" id="new-birthday-input" style="width:100%; padding:13px; border:1px solid #eee; border-radius:8px; font-size:15px; box-sizing:border-box; margin-bottom:5px;">
                            <div id="birthday-modal-msg" style="font-size:13px; margin-bottom:15px; display:none;"></div>
                            <div style="display:flex; gap:10px; margin-top:15px;">
                                <button class="btn-no" onclick="closeModal('modal-add-birthday')" style="flex:1;">Annulla</button>
                                <button class="btn-ok" onclick="saveUserBirthday(this)" style="flex:1;">Salva</button>
                            </div>
                        </div>
                    </div>

                    <!-- I miei appuntamenti - Accordion -->
                    <div class="lz-accordion-card" id="accordion-appuntamenti" style="margin-bottom: 20px;">
                        <div class="lz-accordion-header" onclick="toggleAccordion('accordion-appuntamenti')">
                            <div style="display:flex; align-items:center; gap:12px;">
                                <span style="font-size:22px;">📅</span>
                                <div>
                                    <div style="font-weight:800; font-size:16px;">I miei appuntamenti</div>
                                    <div style="font-size:12px; color:#999; margin-top:1px;">Storico delle tue visite in salone</div>
                                </div>
                            </div>
                            <span class="lz-accordion-chevron" id="chevron-accordion-appuntamenti">▾</span>
                        </div>

                        <div class="lz-accordion-body" id="body-accordion-appuntamenti" style="display:none;">
                            <div style="height:1px; background:#f0f0f0; margin-bottom:20px;"></div>
                            <div id="lz-client-history-list">
                                <?php 
                                $user_phone = get_user_meta($user->ID, 'billing_phone', true);
                                $mq_hist = array('relation' => 'OR');
                                $mq_hist[] = array('key' => '_lz_client_id', 'value' => $user->ID, 'compare' => '=');
                                if(!empty($user_phone)) {
                                    $mq_hist[] = array('key' => '_lz_phone', 'value' => $user_phone, 'compare' => '=');
                                }

                                $hist_q = new WP_Query(array(
                                    'post_type'      => 'lz_appointment',
                                    'posts_per_page' => 10,
                                    'meta_query'     => $mq_hist,
                                    'meta_key'       => '_lz_date',
                                    'orderby'        => 'meta_value',
                                    'order'          => 'DESC'
                                ));
                                if($hist_q->have_posts()):
                                    while($hist_q->have_posts()): $hist_q->the_post();
                                        $st = get_post_meta(get_the_ID(), '_lz_status', true);
                                        $dt = get_post_meta(get_the_ID(), '_lz_date', true);
                                        $sv = get_post_meta(get_the_ID(), '_lz_service', true);
                                        $tm = get_post_meta(get_the_ID(), '_lz_time', true);
                                        
                                        echo '<div class="lz-history-card ' . $st . '">';
                                        echo '<div class="h-date">' . date('d/m/Y', strtotime($dt)) . ' <small>' . $tm . '</small></div>';
                                        echo '<div class="h-service">' . esc_html($sv) . '</div>';
                                        echo '<div class="h-status">' . ucfirst($st) . '</div>';
                                        echo '</div>';
                                    endwhile; wp_reset_postdata();
                                else:
                                    echo '<div class="lz-premium-card" style="text-align:center; color:#999; padding: 30px;">Nessuno storico disponibile.</div>';
                                endif;
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

            </main>

            <!-- Pulsante Aiuto Flottante -->
            <button onclick="openSupportModal()" class="lz-help-btn" title="Ricevi Aiuto">
                <i class="fa fa-commenting"></i>
                <span>Aiuto</span>
            </button>

            <!-- Modal Supporto Interno (Stile Widget Flottante) -->
            <div id="modal-support" style="display: none; position: fixed; bottom: 140px; right: 20px; z-index: 100001; width: calc(100% - 40px); max-width: 380px; background: transparent; pointer-events: none;">
                <div class="lz-chat-widget" style="background: #ffffff; color: #000; padding: 25px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); pointer-events: auto; transform-origin: bottom right; animation: scaleUpChat 0.3s cubic-bezier(0.175, 0.885, 0.32, 1) forwards; border: 1px solid #eee;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                        <span style="font-size: 20px; cursor: pointer; color: #999; line-height: 1; padding: 5px; margin-left: auto;" onclick="closeSupportModal()">&times;</span>
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
                        <div style="background: #000; color: #fff; padding: 15px 20px; border-radius: 12px 12px 0 12px; display: inline-block; font-size: 14px; text-align: right; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                            <strong>Servizio Clienti</strong><br>
                            Siamo qui per aiutarti.
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; align-items: stretch;">
                        <textarea id="support-message" placeholder="Scrivi il tuo messaggio..." style="flex: 1; padding: 12px 15px; border: 1px solid #eee; border-radius: 8px; min-height: 45px; height: 45px; font-family: inherit; font-size: 14px; resize: none; box-sizing: border-box; background: #fafafa; color: #000; outline: none; transition: border-color 0.2s;" onfocus="this.style.borderColor='#000'; this.style.background='#fff';" onblur="this.style.borderColor='#eee'; this.style.background='#fafafa';" onkeydown="if(event.keyCode===13 && !event.shiftKey){event.preventDefault();sendSupportRequest(this.nextElementSibling);}"></textarea>
                        
                        <button class="btn-black-small" onclick="sendSupportRequest(this)" style="background: var(--p-gold); color: #fff; border: none; padding: 0 20px; border-radius: 8px; font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; transition: transform 0.1s; cursor: pointer; box-shadow: 0 4px 10px rgba(212,169,86,0.3);">
                            Invia
                        </button>
                    </div>
                    
                    <div id="support-res" style="margin-top:10px; font-size:13px; text-align:center; display:none; padding: 5px; border-radius: 8px;"></div>
                </div>
            </div>
            
            <style>
                @keyframes scaleUpChat {
                    from { transform: scale(0.8) translateY(20px); opacity: 0; }
                    to { transform: scale(1) translateY(0); opacity: 1; }
                }
                #modal-support.fade-out .lz-chat-widget {
                    animation: scaleDownChat 0.2s ease-in forwards;
                }
                @keyframes scaleDownChat {
                    from { transform: scale(1) translateY(0); opacity: 1; }
                    to { transform: scale(0.8) translateY(20px); opacity: 0; }
                }
            </style>

            <!-- Bottom Navigation Bar -->
            <nav class="lz-bottom-nav">
                <button class="nav-item active" onclick="switchTab('home', this)">
                    <span class="icon">🏠</span>
                    <span class="label">Home</span>
                </button>
                <button class="nav-item" onclick="switchTab('fidelity', this)">
                    <span class="icon">⭐</span>
                    <span class="label">Fidelity</span>
                </button>
                <button class="nav-item" onclick="switchTab('profilo', this)">
                    <span class="icon">👤</span>
                    <span class="label">Profilo</span>
                </button>
            </nav>
        </div>


<?php
        return ob_get_clean();
    }

    public function handle_frontend_login() {
        check_ajax_referer( 'lz_login_nonce', 'security' );
        
        $creds = array(
            'user_login'    => $_POST['log'],
            'user_password' => $_POST['pwd'],
            'remember'      => true
        );
        
        $user = wp_signon( $creds, false );
        
        if ( is_wp_error( $user ) ) {
            // Traduzione messaggi errore
            $msg = $user->get_error_message();
            if(strpos($msg, 'password') !== false) $msg = "Password errata.";
            elseif(strpos($msg, 'username') !== false || strpos($msg, 'email') !== false) $msg = "Utente non trovato.";
            
            wp_send_json_error( strip_tags($msg) );
        } else {
             wp_send_json_success();
        }
    }

    // 1. ADD CLIENT
    public function handle_add_client() {
        error_log("LZ_DEBUG: Inizio handle_add_client");
        $check = check_ajax_referer('lz_ajax_nonce', 'security', false);
        if (!$check) {
            error_log("LZ_DEBUG: Fallita verifica Nonce AJAX");
            wp_send_json_error('Sessione scaduta o non valida (Nonce error). Ricarica la pagina.');
        }

        if(!current_user_can('edit_users')) {
            error_log("LZ_DEBUG: Permessi insufficienti per creare utenti");
            wp_send_json_error('No permissions to create users');
        }
        
        $user = sanitize_text_field($_POST['username']);
        $pass = sanitize_text_field($_POST['password']);
        $email = sanitize_email($_POST['email']);
        
        error_log("LZ_DEBUG: Creazione utente: $user ($email)");
        $uid = wp_create_user($user, $pass, $email);
        
        if(is_wp_error($uid)) {
            error_log("LZ_DEBUG: Errore creazione utente: " . $uid->get_error_message());
            wp_send_json_error($uid->get_error_message());
        } else {
            $first = sanitize_text_field($_POST['first_name']);
            $last = sanitize_text_field($_POST['last_name']);
            
            wp_update_user(array(
                'ID' => $uid,
                'first_name' => $first,
                'last_name' => $last,
                'display_name' => trim($first . ' ' . $last)
            ));

            update_user_meta($uid, 'billing_phone', sanitize_text_field($_POST['phone']));
            
            // Genera chiave di accesso univoca e salva password temporanea
            update_user_meta($uid, 'lz_access_key', wp_generate_password(20, false));
            update_user_meta($uid, '_lz_initial_pass', $pass);

            // Attiva Notifiche WhatsApp di default
            $optin = isset($_POST['wa_optin']) ? 'yes' : 'no';
            update_user_meta($uid, '_lz_wa_optin', $optin);
            
            // Invia Messaggio di Benvenuto Automatico (Non bloccante per il successo AJAX)
            try {
                error_log("LZ_DEBUG: Invio benvenuto WhatsApp...");
                $this->send_welcome_whatsapp($uid, $pass);
            } catch (Exception $e) {
                error_log("LZ_DEBUG: Errore durante invio benvenuto: " . $e->getMessage());
            }
            
            error_log("LZ_DEBUG: Successo handle_add_client");
            wp_send_json_success();
        }
    }

    // 2.5 EDIT CLIENT
    public function handle_edit_client() {
        error_log("LZ_DEBUG: Inizio handle_edit_client");
        $check = check_ajax_referer('lz_ajax_nonce', 'security', false);
        if (!$check) {
            error_log("LZ_DEBUG: Fallita verifica Nonce AJAX (Edit)");
            wp_send_json_error('Sessione scaduta o non valida. Ricarica la pagina.');
        }

        if(!current_user_can('edit_users')) wp_send_json_error('No permissions');
        $uid = intval($_POST['user_id']);
        if(!$uid) wp_send_json_error('Invalid ID');

        $first = sanitize_text_field($_POST['first_name']);
        $last = sanitize_text_field($_POST['last_name']);

        wp_update_user(array(
            'ID' => $uid,
            'user_email' => sanitize_email($_POST['email']),
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => trim($first . ' ' . $last)
        ));

        update_user_meta($uid, 'billing_phone', sanitize_text_field($_POST['phone']));
        
        $optin = isset($_POST['wa_optin']) ? 'yes' : 'no';
        update_user_meta($uid, '_lz_wa_optin', $optin);
        
        wp_send_json_success();
    }

    // 2.6 DELETE CLIENT
    public function handle_delete_client() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('delete_users')) wp_send_json_error('No permissions');
        $uid = intval($_POST['user_id']);
        if(!$uid) wp_send_json_error('Invalid ID');

        require_once(ABSPATH.'wp-admin/includes/user.php');
        wp_delete_user($uid);
        wp_send_json_success();
    }

    // 2. SEARCH CLIENTS
    public function handle_search_clients() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_posts')) wp_send_json_error('No permissions');
        
        $q = sanitize_text_field($_POST['query']);
        
        $args = array(
            'role__not_in'   => array('administrator'),
            'number'         => 30,
            'search'         => '*' . $q . '*',
            'search_columns' => array('user_login', 'user_email', 'display_name'),
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => 'first_name',
                    'value'   => $q,
                    'compare' => 'LIKE'
                ),
                array(
                    'key'     => 'last_name',
                    'value'   => $q,
                    'compare' => 'LIKE'
                )
            )
        );
        if(!$q) unset($args['search']);

        $user_query = new WP_User_Query($args);
        $results = array();
        
        foreach($user_query->get_results() as $user) {
            $phone = get_user_meta($user->ID, 'billing_phone', true);
            // Quick phone check
            if($q && is_numeric($q) && strpos($phone, $q) === false && strpos($user->display_name, $q) === false) continue; 
            
            $results[] = array(
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_login' => $user->user_login,
                'phone' => $phone ?: 'N/D',
                'wa_optin' => get_user_meta($user->ID, '_lz_wa_optin', true)
            );
        }
        wp_send_json_success($results);
    }

    // 3. GET CLIENT DETAILS
    public function handle_get_client_details() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_posts')) wp_send_json_error('No permissions');
        $uid = intval($_POST['user_id']);
        $user = get_userdata($uid);
        
        $points = (int) get_user_meta($uid, '_lz_points', true); // Simple Points System

        $bookings = array();
        $pq = new WP_Query(array(
            'post_type' => 'lz_appointment',
            'meta_key' => '_lz_client_id',
            'meta_value' => $uid,
            'posts_per_page' => 10,
            'orderby' => 'date', 'order' => 'DESC'
        ));
        
        while($pq->have_posts()) {
            $pq->the_post();
            $d = get_post_meta(get_the_ID(), '_lz_date', true);
            $bookings[] = array(
                'id' => get_the_ID(),
                'service' => get_post_meta(get_the_ID(), '_lz_service', true),
                'date_fmt' => date('d/m/Y', strtotime($d)),
                'time' => get_post_meta(get_the_ID(), '_lz_time', true),
                'status' => get_post_meta(get_the_ID(), '_lz_status', true),
            );
        } wp_reset_postdata();

        $promo_history = get_user_meta($uid, '_lz_promo_history', true) ?: array();
        $promotions = array();
        foreach (array_reverse($promo_history) as $h) {
            $p_id = $h['id'];
            $promotions[] = array(
                'title' => get_post_meta($p_id, '_lz_promo_title', true) ?: get_the_title($p_id),
                'badge' => get_post_meta($p_id, '_lz_promo_badge', true),
                'sent_at' => date('d/m/Y H:i', strtotime($h['sent_at'])),
                'seen' => $h['seen'] === 'yes',
                'seen_at' => isset($h['seen_at']) ? date('d/m/Y H:i', strtotime($h['seen_at'])) : ''
            );
        }

        wp_send_json_success(array(
            'ID' => $user->ID,
            'name' => $user->display_name,
            'first_name' => get_user_meta($uid, 'first_name', true),
            'last_name' => get_user_meta($uid, 'last_name', true),
            'email' => $user->user_email,
            'phone' => get_user_meta($uid, 'billing_phone', true) ?: 'N/D',
            'points' => $points,
            'bookings' => $bookings,
            'bookings_count' => count($bookings),
            'wa_optin' => get_user_meta($uid, '_lz_wa_optin', true),
            'promotions' => $promotions
        ));
    }

    // 4. MANAGE POINTS
    public function handle_manage_points() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_users')) wp_send_json_error();
        $uid = intval($_POST['user_id']);
        $amt = intval($_POST['amount']);
        $type = $_POST['type']; 
        
        $current = (int) get_user_meta($uid, '_lz_points', true);
        
        if($type == 'add') $new = $current + $amt;
        else $new = max(0, $current - $amt);
        
        update_user_meta($uid, '_lz_points', $new);

        // Notifica Push per i punti fedeltà (FCM)
        if ( class_exists('LZ_FCM_Sync') ) {
            if ($type == 'add') {
                LZ_FCM_Sync::send_push($uid, "Hai ricevuto $amt nuovi timbri fidelity! Totale attuale: $new timbri.", "🎁 Timbri Ricevuti!");
            }
        }

        wp_send_json_success();
    }

    // 5. SEND NOTIFICATION (WhatsApp, Email e PUSH)
public function handle_send_notification() {
    check_ajax_referer('lz_ajax_nonce', 'security');
    if(!current_user_can('edit_posts')) wp_send_json_error('No permissions');
    $uid = intval($_POST['user_id']);
    $msg = sanitize_textarea_field($_POST['message']);
    $user = get_userdata($uid);
    $phone = get_user_meta($uid, 'billing_phone', true);
    
    // 1. Invia WhatsApp (Priorità)
    $wa_success = false;
    if ( class_exists('LZ_WA_Sync') && !empty($phone) ) {
        // Se il messaggio è uno dei template approvati (non contiene spazi e corrisponde a un nome template)
        $templates = array_filter(array_map('trim', explode(',', get_option('lz_wa_adhoc_templates', ''))));
        if ( in_array($msg, $templates) ) {
            $variables = array($user->display_name, "Lidia Zucaro Parrucchieri");
            $lang = null; // Usa default (it)

            // Casi speciali per template che non richiedono 2 parametri
            if ($msg === 'meta_review_test' || $msg === 'meta_test') {
                $variables = array($user->display_name);
                if ($msg === 'meta_review_test') $lang = 'en';
            }

            $res = LZ_WA_Sync::send_whatsapp_template($phone, $msg, $variables, $lang);
            $wa_success = $res['success'];
        } else {
            $wa_success = LZ_WA_Sync::send_whatsapp_text($phone, $msg);
        }
    }

    // 2. Invia Email (Backup)
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail(
        $user->user_email, 
        'Messaggio da Lidia Zucaro', 
        "<p>Ciao " . $user->display_name . ",</p><p>" . nl2br($msg) . "</p><p>A presto,<br>Lidia Zucaro</p>",
        $headers
    );
    
    // 3. Invia Push Diretta al telefono via FCM (Backup)
    if ( class_exists('LZ_FCM_Sync') ) {
        LZ_FCM_Sync::send_push($uid, $msg, "💬 Nuovo Messaggio");
    }
    
    if($wa_success) {
        wp_send_json_success('Inviato con successo via WhatsApp');
    } else {
        wp_send_json_success('Inviato via Email (WhatsApp non disponibile)');
    }
}


    // --- HELPERS ESTERNI (BOOKING FORM & LISTS) ---
    // Ricostruisco le funzioni originali per garantire che il file sia completo

    private function render_appointment_list($filter_type) {
        $today_str = current_time('Y-m-d');
        $next_week_str = date('Y-m-d', strtotime('+7 days', current_time('timestamp')));

        $args = array(
            'post_type' => 'lz_appointment',
            'posts_per_page' => -1,
            'orderby' => 'meta_value', 
            'meta_key' => '_lz_date',
            'order' => 'ASC'
        );

        if($filter_type == 'pending') {
            $args['meta_query'] = array( array('key' => '_lz_status', 'value' => 'pending') );
        } elseif($filter_type == 'all_approved') {
            $args['meta_query'] = array( array('key' => '_lz_status', 'value' => 'approved') );
            $args['order'] = 'DESC'; // I più recenti/futuri in cima
        } elseif($filter_type == 'today_week') {
            // Nuova logica: Mostra solo "Approvati" per questa settimana (Da oggi a +7 gg)
            $args['meta_query'] = array(
                'relation' => 'AND',
                array('key' => '_lz_status', 'value' => 'approved'),
                array(
                    'key' => '_lz_date',
                    'value' => array($today_str, $next_week_str),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            );
        }

        $query = new WP_Query($args);

        if($query->have_posts()) {
            while($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $service = get_post_meta($id, '_lz_service', true);
                $date = get_post_meta($id, '_lz_date', true);
                $time = get_post_meta($id, '_lz_time', true);
                $phone = get_post_meta($id, '_lz_phone', true);
                $status = get_post_meta($id, '_lz_status', true);
                $date_fmt = date('d/m', strtotime($date));

                echo '<div class="lz-card ' . $status . '">';
                echo '<div class="lz-info">';
                echo '<h4>' . get_the_title() . '</h4>';
                echo '<p>📅 ' . $date_fmt . ' ore ' . $time . '</p>';
                echo '<p>💇‍♀️ ' . $service . '</p>';
                echo '<p>📞 <a href="tel:' . $phone . '" style="color:#007bff; text-decoration:none;">' . $phone . '</a></p>';
                echo '</div>';
                
                echo '<div class="lz-actions">';
                if($filter_type == 'pending') {
                    echo '<button class="btn-ok" onclick="updateBooking('.$id.', \'approved\', this)">Accetta</button>';
                    echo '<button class="btn-no" onclick="updateBooking('.$id.', \'rejected\', this)">Rifiuta</button>';
                } else {
                    echo '<button class="btn-no" onclick="updateBooking('.$id.', \'canceled\', this)">Cancella</button>';
                }
                echo '</div>';
                echo '</div>';
            }
            wp_reset_postdata();
        } else {
             echo '<div style="text-align:center; padding:30px; color:#999;">
                    <p>Nessun appuntamento in questa lista.</p>
                  </div>';
        }
    }

    public function handle_status_update() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_posts')) wp_send_json_error();
        $post_id = intval($_POST['post_id']);
        $new_status = sanitize_text_field($_POST['new_status']);
        
        $old_status = get_post_meta($post_id, '_lz_status', true);
        update_post_meta($post_id, '_lz_status', $new_status);
        
        // Trigger per Google Calendar o altri eventuali listener
        do_action( 'lz_appointment_status_changed', $post_id, $new_status, $old_status );
        
        wp_send_json_success();
    }

    public function render_booking_form() {
        $this->enqueue_styles();
        $current_user = wp_get_current_user();
        $name_val = $current_user->exists() ? $current_user->display_name : '';
        $phone_val = $current_user->exists() ? get_user_meta($current_user->ID, 'billing_phone', true) : '';

        ob_start();
        ?>
        <div id="lz-booking-wrapper">
            <form id="lz-booking-form">
                <div class="form-group">
                    <label>Trattamento</label>
                    <select name="service" required>
                        <option value="Piega">Piega (30 min) - 20€</option>
                        <option value="Taglio">Taglio Donna (45 min) - 35€</option>
                        <option value="Colore">Colore Base (90 min) - 50€</option>
                    </select>
                </div>
                <div class="form-group-row">
                    <div class="form-group half">
                        <label>Data</label>
                        <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group half">
                        <label>Ora</label>
                        <div style="display:flex; flex-direction:column; gap:10px; margin-top:5px;">
                            <input type="time" name="time" id="lz-time-input" required style="width:100% !important;">
                            <label style="font-size:14px; display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" id="lz-no-pref-time" style="width:18px; height:18px; margin:0;"> 
                                <span>Nessuna preferenza</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Promo</label>
                    <?php 
                    $promo_history = array();
                    if ($current_user->exists()) {
                        $promo_history = get_user_meta($current_user->ID, '_lz_promo_history', true) ?: array();
                    }
                    if (!empty($promo_history)): ?>
                        <select name="promo_id">
                            <option value="">-- Seleziona Promo (Opzionale) --</option>
                            <?php 
                            foreach (array_reverse($promo_history) as $h): 
                                $p_id = $h['id'];
                                $p_title = get_post_meta($p_id, '_lz_promo_title', true) ?: get_the_title($p_id);
                                ?>
                                <option value="<?php echo esc_attr($p_id); ?>"><?php echo esc_html($p_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <select name="promo_id" disabled>
                            <option value="">Nessuna promozione ricevuta</option>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Nome</label>
                    <input type="text" name="customer_name" value="<?php echo esc_attr($name_val); ?>" required>
                </div>
                <div class="form-group">
                    <label>Telefono</label>
                    <input type="tel" name="phone" value="<?php echo esc_attr($phone_val); ?>" required>
                </div>
                <input type="hidden" name="action" value="lz_submit_booking">
                <?php wp_nonce_field( 'lz_booking_nonce', 'security' ); ?>
                <button type="submit" id="lz-submit-btn">Prenota</button>
                <div id="lz-response"></div>
            </form>
        </div>
        <script>
        var lzClientSubmitting = false;
        jQuery(document).ready(function($) {
            $('#lz-no-pref-time').on('change', function() {
                if($(this).is(':checked')) {
                    $('#lz-time-input').hide().val('');
                } else {
                    $('#lz-time-input').show();
                }
            });

            $('#lz-booking-form').off('submit').on('submit', function(e) {
                e.preventDefault();
                var btn = $('#lz-submit-btn');
                var msg = $('#lz-response');
                
                if (btn.prop('disabled') || lzClientSubmitting) return false;
                
                lzClientSubmitting = true;
                btn.text('Attendi...').prop('disabled', true);
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', $(this).serialize(), function(response) {
                    if(response.success) {
                        msg.html('<div class="success-msg" style="color:green;margin-top:10px;font-weight:bold;">✅ Richiesta inviata!</div>');
                        $('#lz-booking-form')[0].reset();

                        // Meta Pixel Conversion Event
                        if (typeof fbq === 'function') {
                            fbq('track', 'Schedule');
                        }
                        
                        // Aggiorna dinamicamente la lista senza ricaricare
                        if(typeof refreshDashboardData === "function") {
                            refreshDashboardData();
                        }
                        
                        // Ripristina il bottone dopo 3 secondi
                        setTimeout(function(){ 
                            msg.fadeOut(500, function() { $(this).html('').show(); });
                            btn.text('Prenota').prop('disabled', false);
                            lzClientSubmitting = false;
                        }, 3000); 
                    } else {
                        msg.html('<div class="error-msg" style="color:red;margin-top:10px;">❌ Errore durante l\'invio.</div>');
                        btn.text('Riprova').prop('disabled', false);
                        lzClientSubmitting = false;
                    }
                }).fail(function() {
                    msg.html('<div class="error-msg" style="color:red;margin-top:10px;">❌ Errore di rete.</div>');
                    btn.text('Riprova').prop('disabled', false);
                    lzClientSubmitting = false;
                });
                return false;
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    // Save User Email (from client profile accordion modal)
    public function handle_save_user_email() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if ( ! is_user_logged_in() ) wp_send_json_error('Non autenticato.');

        $user_id = get_current_user_id();
        $email   = sanitize_email( $_POST['email'] );

        if ( ! is_email( $email ) ) {
            wp_send_json_error('Indirizzo email non valido.');
        }

        if ( email_exists( $email ) && email_exists( $email ) !== $user_id ) {
            wp_send_json_error('Email già in uso da un altro account.');
        }

        $result = wp_update_user( array( 'ID' => $user_id, 'user_email' => $email ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success('Email salvata correttamente.');
    }

    // Save User Birthday (from client profile accordion modal)
    public function handle_save_user_birthday() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if ( ! is_user_logged_in() ) wp_send_json_error('Non autenticato.');

        $user_id = get_current_user_id();
        $birthday = sanitize_text_field( $_POST['birthday'] );

        if ( empty( $birthday ) ) {
            wp_send_json_error('Data di nascita non valida.');
        }

        update_user_meta( $user_id, '_lz_birthday', $birthday );

        wp_send_json_success('Data di nascita salvata correttamente.');
    }

    public function handle_update_wa_optin() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_posts')) wp_send_json_error();
        $user_id = intval($_POST['user_id']);
        $optin = sanitize_text_field($_POST['optin']);
        
        if($user_id && in_array($optin, array('yes', 'no'))) {
            update_user_meta($user_id, '_lz_wa_optin', $optin);
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    public function handle_booking_submission() {
        check_ajax_referer( 'lz_booking_nonce', 'security' );
        $name = sanitize_text_field( $_POST['customer_name'] );
        $post_id = wp_insert_post(array('post_title'=>$name,'post_type'=>'lz_appointment','post_status'=>'publish'));

        if( $post_id ) {
            $service = sanitize_text_field($_POST['service']);
            $date = sanitize_text_field($_POST['date']);
            $time = !empty($_POST['time']) ? sanitize_text_field($_POST['time']) : 'Da definire';
            
            update_post_meta( $post_id, '_lz_service', $service );
            update_post_meta( $post_id, '_lz_date', $date );
            update_post_meta( $post_id, '_lz_time', $time );
            update_post_meta( $post_id, '_lz_phone', sanitize_text_field($_POST['phone']) );
            update_post_meta( $post_id, '_lz_client_id', get_current_user_id() );
            update_post_meta( $post_id, '_lz_status', 'pending' );

            if (!empty($_POST['promo_id'])) {
                update_post_meta($post_id, '_lz_applied_promo', intval($_POST['promo_id']));
            }

            // Notifica Push all'Admin per Nuova Richiesta via FCM
            if (class_exists('LZ_FCM_Sync')) {
                $admin_users = get_users(array('role' => 'administrator'));
                foreach ($admin_users as $admin) {
                    LZ_FCM_Sync::send_push(
                        $admin->ID,
                        $name . ' ha richiesto un appuntamento per ' . sanitize_text_field($_POST['service']) . ' il ' . sanitize_text_field($_POST['date']),
                        '📅 Nuova Richiesta Appuntamento'
                    );
                }
            }

            // Notifica WhatsApp all'Admin
            do_action( 'lz_new_booking_created', $post_id );

            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    public function handle_booking_manual() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_posts')) wp_send_json_error();
        
        $name = sanitize_text_field( $_POST['customer_name'] );
        $phone = sanitize_text_field( $_POST['phone'] );
        
        $post_id = wp_insert_post(array(
            'post_title'  => 'Manuale: ' . $name,
            'post_type'   => 'lz_appointment',
            'post_status' => 'publish'
        ));

        if( $post_id ) {
            update_post_meta( $post_id, '_lz_service', sanitize_text_field($_POST['service']) );
            update_post_meta( $post_id, '_lz_date', sanitize_text_field($_POST['date']) );
            update_post_meta( $post_id, '_lz_time', sanitize_text_field($_POST['time']) );
            update_post_meta( $post_id, '_lz_phone', $phone );
            update_post_meta( $post_id, '_lz_status', 'approved' );

            // Associazione automatica al cliente se esiste un utente con quel numero di telefono
            if (!empty($phone)) {
                $users = get_users(array(
                    'meta_key'   => '_lz_phone',
                    'meta_value' => $phone,
                    'number'     => 1,
                    'fields'     => 'ID'
                ));
                if (!empty($users)) {
                    update_post_meta($post_id, '_lz_client_id', $users[0]);
                }
            }
            
            do_action( 'lz_appointment_status_changed', $post_id, 'approved', 'none' );
            wp_send_json_success();
        } else {
            wp_send_json_error();
        }
    }

    private function enqueue_styles() {
        echo '<style>
            #lz-booking-wrapper form { display: flex; flex-direction: column; gap: 15px; }
            #lz-booking-wrapper input, #lz-booking-wrapper select { 
                padding: 15px; 
                border: 1px solid #ccc; 
                border-radius: 12px; 
                font-size: 16px; 
                background: #fff; 
                color: #000;
                width: 100%;
                box-sizing: border-box;
                -webkit-appearance: none;
                min-height: 50px;
            }
            #lz-booking-wrapper input[type="date"], #lz-booking-wrapper input[type="time"] {
                display: block;
                min-width: 0;
            }
            /* iOS Specific fix for date/time alignment */
            #lz-booking-wrapper input[type="date"]::-webkit-date-and-time-value,
            #lz-booking-wrapper input[type="time"]::-webkit-date-and-time-value {
                height: 1.2em;
                min-height: 1.2em;
                text-align: left;
            }
            #lz-submit-btn { padding: 15px; background: #000; color: #fff; border: 0; border-radius: 12px; font-size: 16px; font-weight: 800; text-transform: uppercase; margin-top: 10px; cursor: pointer; }
            .success-msg { color: green; background: #eaffea; padding: 15px; border-radius: 8px; margin-top: 10px; text-align: center; }
            .error-msg { color: red; margin-top: 10px; text-align: center; }
            .form-group-row { display: flex; gap: 12px; width: 100%; }
            .form-group.half { flex: 1; min-width: 0; }
            .form-group label { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 800; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }
        </style>';
    }
    public function handle_get_my_dashboard_data() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if ( ! is_user_logged_in() ) wp_send_json_error('Not logged in');
        $user_id = get_current_user_id();
        
        $data = array();
        
        // 1. Render HISTORY
        ob_start();
        $user_phone = get_user_meta($user_id, 'billing_phone', true);
        if(empty($user_phone)) $user_phone = get_user_meta($user_id, '_lz_phone', true);

        $mq_client = array('relation' => 'OR');
        $mq_client[] = array('key' => '_lz_client_id', 'value' => $user_id, 'compare' => '=');
        if(!empty($user_phone)) {
            $mq_client[] = array('key' => '_lz_phone', 'value' => $user_phone, 'compare' => '=');
        }

        $hist_q = new WP_Query(array(
            'post_type'      => 'lz_appointment',
            'meta_query'     => $mq_client,
            'posts_per_page' => 10,
            'meta_key'       => '_lz_date',
            'orderby'        => 'meta_value', 
            'order'          => 'DESC'
        ));
        
        if($hist_q->have_posts()):
            while($hist_q->have_posts()): $hist_q->the_post();
                $st = get_post_meta(get_the_ID(), '_lz_status', true);
                $dt = get_post_meta(get_the_ID(), '_lz_date', true);
                $sv = get_post_meta(get_the_ID(), '_lz_service', true);
                $tm = get_post_meta(get_the_ID(), '_lz_time', true);
                
                echo '<div class="lz-history-card ' . $st . '">';
                echo '<div class="h-date">' . date('d/m/Y', strtotime($dt)) . ' <small>' . $tm . '</small></div>';
                echo '<div class="h-service">' . esc_html($sv) . '</div>';
                echo '<div class="h-status">Stat: ' . ucfirst($st) . '</div>';
                echo '</div>';
            endwhile; wp_reset_postdata();
        else:
            echo '<div class="lz-premium-card" style="text-align:center; color:#999; padding: 30px;">Nessuno storico disponibile.</div>';
        endif;
        $data['history'] = ob_get_clean();

        // 2. Render NEXT APPOINTMENT (Solo Approvati)
        ob_start();
        $next_booking = null;
        
        $mq_next = array('relation' => 'AND');
        $mq_next[] = $mq_client;
        $mq_next[] = array('key' => '_lz_status', 'value' => 'approved', 'compare' => '=');
        
        $mq_next['date_clause'] = array(
            'key' => '_lz_date', 
            'value' => date_i18n('Y-m-d'), 
            'compare' => '>=', 
            'type' => 'DATE'
        );
        $mq_next['time_clause'] = array(
            'key' => '_lz_time',
            'compare' => 'EXISTS'
        );

        $bq = new WP_Query(array(
            'post_type'      => 'lz_appointment',
            'posts_per_page' => 1,
            'meta_query'     => $mq_next,
            'orderby'        => array(
                'date_clause' => 'ASC',
                'time_clause' => 'ASC'
            )
        ));
        if($bq->have_posts()) {
            $bq->the_post();
            $next_booking = array(
                'id'      => get_the_ID(),
                'service' => get_post_meta(get_the_ID(), '_lz_service', true),
                'date'    => date('d/m/Y', strtotime(get_post_meta(get_the_ID(), '_lz_date', true))),
                'raw_date'=> get_post_meta(get_the_ID(), '_lz_date', true),
                'time'    => get_post_meta(get_the_ID(), '_lz_time', true),
            );
            wp_reset_postdata();
        }

        if($next_booking): ?>
            <div class="lz-premium-card next-app" onclick="openManageModal(<?php echo $next_booking['id']; ?>, '<?php echo esc_js($next_booking['service']); ?>', '<?php echo esc_js($next_booking['raw_date']); ?>', '<?php echo esc_js($next_booking['time']); ?>')" style="cursor: pointer;">
                <div class="card-label">PROSSIMO APPUNTAMENTO</div>
                <div class="card-large-text"><?php echo $next_booking['date']; ?> <small>alle</small> <?php echo $next_booking['time']; ?></div>
                <div class="card-sub-text"><?php echo $next_booking['service']; ?></div>
                <button class="lz-cal-btn" onclick="event.stopPropagation(); addToCalendar('<?php echo esc_js($next_booking['service']); ?>', '<?php echo esc_js($next_booking['date']); ?>', '<?php echo esc_js($next_booking['time']); ?>')">
                    <i class="fas fa-calendar-plus"></i> AGGIUNGI AL CALENDARIO
                </button>
            </div>
        <?php else: ?>
            <div class="lz-premium-card" style="text-align:center; padding: 40px 20px;">
                <div style="font-size:40px; margin-bottom:15px;">🗓️</div>
                <p style="color:#888; margin-bottom:20px;">Non hai appuntamenti in programma.</p>
                <button class="btn-black" onclick="openModal('modal-prenota-cliente')">PRENOTA ORA</button>
            </div>
        <?php endif;
        $data['next'] = ob_get_clean();

        // 3. Render FIDELITY CARD
        ob_start();
        $points = (int) get_user_meta($user_id, '_lz_points', true);
        ?>
        <div class="lz-fidelity-card">
            <div class="card-header">
                <div class="brand"></div>
                <div class="points-count"><?php echo $points; ?> / 9</div>
            </div>
            
            <div class="lz-timbri-grid">
                <?php for($i=1; $i<=9; $i++): ?>
                    <div class="lz-timbro <?php echo ($points >= $i) ? 'active' : ''; ?>">
                        <?php if($points >= $i) echo '<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'; ?>
                    </div>
                <?php endfor; ?>
            </div>
            
            <div class="card-footer">
                <div class="promo-text">Completa la card per ricevere un trattamento omaggio.</div>
                <div class="status-bar"><div class="progress" style="width: <?php echo ($points/9)*100; ?>%;"></div></div>
            </div>
        </div>
        <?php
        $data['fidelity'] = ob_get_clean();
        
        wp_send_json_success($data);
    }
    public function handle_get_weekly_agenda() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_posts')) wp_send_json_error();

        $offset = isset($_POST['week_offset']) ? intval($_POST['week_offset']) : 0;
        
        // Calcola l'inizio della settimana (Lunedì)
        $start_of_week = date('Y-m-d', strtotime('monday this week ' . ($offset >= 0 ? '+' : '') . $offset . ' weeks'));
        $end_of_week = date('Y-m-d', strtotime($start_of_week . ' +6 days'));

        $label = date('d M', strtotime($start_of_week)) . ' - ' . date('d M', strtotime($end_of_week));

        // Query appuntamenti
        $query = new WP_Query(array(
            'post_type' => 'lz_appointment',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array('key' => '_lz_status', 'value' => 'approved'),
                array(
                    'key' => '_lz_date',
                    'value' => array($start_of_week, $end_of_week),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            ),
            'orderby' => 'meta_value', 'meta_key' => '_lz_time', 'order' => 'ASC'
        ));

        $days_map = array();
        if($query->have_posts()) {
            while($query->have_posts()) {
                $query->the_post();
                $d = get_post_meta(get_the_ID(), '_lz_date', true);
                $app_id = get_the_ID();
                $days_map[$d][] = array(
                    'id' => $app_id,
                    'title' => get_the_title(),
                    'time' => get_post_meta($app_id, '_lz_time', true),
                    'service' => get_post_meta($app_id, '_lz_service', true)
                );
            }
            wp_reset_postdata();
        }

        $grid_html = '';
        $days_names = array('Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica');
        
        for($i=0; $i<7; $i++) {
            $current_day = date('Y-m-d', strtotime($start_of_week . " +$i days"));
            $day_display = date('d/m', strtotime($current_day));
            
            $grid_html .= '<div class="grid-day-container">';
            $grid_html .= '  <div class="grid-day-header">';
            $grid_html .= '    <div class="grid-day-name">' . $days_names[$i] . '</div>';
            $grid_html .= '    <div class="grid-day-date">' . $day_display . '</div>';
            $grid_html .= '  </div>';
            $grid_html .= '  <div class="grid-day-column">';
            
            // --- CONTROLLO COMPLEANNI ---
            $current_md = date('m-d', strtotime($current_day));
            $current_y  = date('Y', strtotime($current_day));
            
            // Cerca tutti gli utenti che hanno _lz_birthday e controlla se corrisponde il mese-giorno
            $birthday_query = new WP_User_Query(array(
                'meta_query' => array(
                    array(
                        'key'     => '_lz_birthday',
                        'value'   => '-' . $current_md,
                        'compare' => 'LIKE'
                    )
                )
            ));
            
            if ( ! empty( $birthday_query->get_results() ) ) {
                foreach ( $birthday_query->get_results() as $b_user ) {
                    $b_date = get_user_meta($b_user->ID, '_lz_birthday', true); // Format YYYY-MM-DD
                    $b_year = date('Y', strtotime($b_date));
                    $age = intval($current_y) - intval($b_year);
                    
                    $grid_html .= '    <div style="background:#fff3cd; color:#856404; padding:10px; border-radius:8px; margin-bottom:10px; font-size:12px; font-weight:bold; border-left:4px solid #ffc107; text-align:center;">';
                    $grid_html .= '      🎉 Oggi è il compleanno di<br><span style="font-size:14px; color:#000;">' . esc_html($b_user->display_name) . '</span><br>(' . $age . ' anni)';
                    $grid_html .= '    </div>';
                }
            }
            // ----------------------------

            if(!empty($days_map[$current_day])) {
                foreach($days_map[$current_day] as $app) {
                    $grid_html .= '    <div class="grid-app-item" onclick="openBookingDetail(' . $app['id'] . ')" style="cursor:pointer;">';
                    $grid_html .= '      <div class="grid-app-time">' . $app['time'] . '</div>';
                    $grid_html .= '      <div class="grid-app-name">' . esc_html($app['title']) . '</div>';
                    $grid_html .= '      <div class="grid-app-service">' . esc_html($app['service']) . '</div>';
                    $grid_html .= '    </div>';
                }
            }
            
            $grid_html .= '  </div>';
            $grid_html .= '</div>';
        }

        wp_send_json_success(array(
            'grid_html' => $grid_html,
            'week_label' => $label
        ));
    }

    public function handle_get_booking_details() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_posts')) wp_send_json_error();
        $id = intval($_POST['booking_id']);
        $post = get_post($id);
        if(!$post) wp_send_json_error('Non trovato');

        wp_send_json_success(array(
            'id' => $id,
            'client_name' => str_replace('Manuale: ', '', get_the_title($id)),
            'service' => get_post_meta($id, '_lz_service', true),
            'date' => date('Y-m-d', strtotime(get_post_meta($id, '_lz_date', true))),
            'time' => get_post_meta($id, '_lz_time', true)
        ));
    }

    public function handle_edit_booking() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_posts')) wp_send_json_error();
        $id = intval($_POST['booking_id']);
        
        $new_service = sanitize_text_field($_POST['service']);
        $new_date = sanitize_text_field($_POST['date']);
        $new_time = sanitize_text_field($_POST['time']);

        update_post_meta($id, '_lz_service', $new_service);
        update_post_meta($id, '_lz_date', $new_date);
        update_post_meta($id, '_lz_time', $new_time);

        // Notifica Cliente (Tramite Template appuntamento_modificato)
        $client_phone = get_post_meta($id, '_lz_phone', true);
        if ($client_phone && class_exists('LZ_WA_Sync')) {
            $template = 'appuntamento_modificato';
            
            $user_id = get_post_meta($id, '_lz_client_id', true);
            $customer_name = "Cliente";
            if ($user_id) {
                $user_info = get_userdata($user_id);
                $full_name = trim($user_info->first_name . ' ' . $user_info->last_name);
                $customer_name = !empty($full_name) ? $full_name : $user_info->display_name;
            } else {
                $customer_name = str_replace(' (WhatsApp)', '', get_the_title($id));
            }

            LZ_WA_Sync::send_whatsapp_template($client_phone, $template, array(
                $customer_name,
                "Lidia Zucaro Parrucchieri",
                $new_service,
                date('d/m/Y', strtotime($new_date)),
                $new_time
            ));
        }

        wp_send_json_success('Modificato');
    }

    public function handle_delete_booking() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_posts')) wp_send_json_error();
        $id = intval($_POST['booking_id']);
        
        // Notifica Cliente (Tramite Template appuntamento_annullato) prima di eliminare
        $client_phone = get_post_meta($id, '_lz_phone', true);
        $service = get_post_meta($id, '_lz_service', true);
        $date = get_post_meta($id, '_lz_date', true);
        $time = get_post_meta($id, '_lz_time', true);

        if ($client_phone && class_exists('LZ_WA_Sync')) {
            $template = get_option('lz_wa_template_rejected', 'appuntamento_annullato');
            
            $user_id = get_post_meta($id, '_lz_client_id', true);
            $customer_name = "Cliente";
            if ($user_id) {
                $user_info = get_userdata($user_id);
                $full_name = trim($user_info->first_name . ' ' . $user_info->last_name);
                $customer_name = !empty($full_name) ? $full_name : $user_info->display_name;
            } else {
                $customer_name = str_replace(' (WhatsApp)', '', get_the_title($id));
            }

            LZ_WA_Sync::send_whatsapp_template($client_phone, $template, array(
                $customer_name,
                "Lidia Zucaro Parrucchieri",
                $service,
                date('d/m/Y', strtotime($date)),
                $time
            ));
        }

        wp_delete_post($id, true);
        wp_send_json_success('Eliminato');
    }

    // --- Nuovi endpoint per invio richiesta modifica/annullamento via WhatsApp (Cliente) ---
    public function handle_request_edit_booking() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error('Non autenticato');

        $user = wp_get_current_user();
        $client_name = $user->first_name ? $user->first_name . ' ' . $user->last_name : $user->display_name;

        $id = intval($_POST['booking_id']);
        $new_service = sanitize_text_field($_POST['service']);
        $new_date_raw = sanitize_text_field($_POST['date']);
        $new_time = sanitize_text_field($_POST['time']);

        $old_service = get_post_meta($id, '_lz_service', true);
        $old_date_raw = get_post_meta($id, '_lz_date', true);
        $old_time = get_post_meta($id, '_lz_time', true);

        // Salva la richiesta nel database
        update_post_meta($id, '_lz_pending_action', 'edit');
        update_post_meta($id, '_lz_pending_service', $new_service);
        update_post_meta($id, '_lz_pending_date', $new_date_raw);
        update_post_meta($id, '_lz_pending_time', $new_time);

        // Formatta le date per il messaggio
        $old_date = date('d/m/Y', strtotime($old_date_raw));
        $new_date = date('d/m/Y', strtotime($new_date_raw));

        $msg = "🔔 *Nuova Richiesta di modifica Appuntamento*\n\n";
        $msg .= "CLIENTE: " . $client_name . "\n";
        $msg .= "SERVIZIO: " . ($old_service === $new_service ? $old_service : $old_service . " -> " . $new_service) . "\n";
        $msg .= "DATA: " . ($old_date === $new_date ? $old_date : $old_date . " -> " . $new_date) . "\n";
        $msg .= "ORA: " . ($old_time === $new_time ? $old_time : $old_time . " -> " . $new_time) . "\n\n";
        
        $admin_url = "https://lidiazucaro.it/gestione/agenda/?edit_request=" . $id;
        $msg .= "👉 Gestisci qui: $admin_url";

        $admin_phone = get_option('lz_wa_admin_phone', '393913079633'); // Default admin phone
        if (class_exists('LZ_WA_Sync')) {
            LZ_WA_Sync::send_whatsapp_text($admin_phone, $msg);
        }

        wp_send_json_success('Richiesta inviata. Verrai ricontattato a breve.');
    }

    public function handle_request_delete_booking() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error('Non autenticato');

        $user = wp_get_current_user();
        $client_name = $user->first_name ? $user->first_name . ' ' . $user->last_name : $user->display_name;

        $id = intval($_POST['booking_id']);
        
        // Salva la richiesta di annullamento nel database
        update_post_meta($id, '_lz_pending_action', 'cancel');

        $old_service = get_post_meta($id, '_lz_service', true);
        $old_date_raw = get_post_meta($id, '_lz_date', true);
        $old_time = get_post_meta($id, '_lz_time', true);
        $old_date = date('d/m/Y', strtotime($old_date_raw));

        $msg = "🚨 *Richiesta di annullamento Appuntamento*\n\n";
        $msg .= "CLIENTE: " . $client_name . "\n";
        $msg .= "SERVIZIO: " . $old_service . "\n";
        $msg .= "DATA: " . $old_date . "\n";
        $msg .= "ORA: " . $old_time . "\n\n";
        
        $admin_url = "https://lidiazucaro.it/gestione/agenda/?edit_request=" . $id;
        $msg .= "👉 Gestisci qui: $admin_url";

        $admin_phone = get_option('lz_wa_admin_phone', '393913079633');
        if (class_exists('LZ_WA_Sync')) {
            LZ_WA_Sync::send_whatsapp_text($admin_phone, $msg);
        }

        wp_send_json_success('Richiesta inviata. Il salone aggiornerà la tua agenda a breve.');
    }

    // --- Endpoint Admin per Rispondere alle Richieste ---
    public function handle_admin_approve_edit() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Non autorizzato');

        $id = intval($_POST['booking_id']);
        $new_service = get_post_meta($id, '_lz_pending_service', true);
        $new_date = get_post_meta($id, '_lz_pending_date', true);
        $new_time = get_post_meta($id, '_lz_pending_time', true);

        // Applica le modifiche
        if ($new_service) update_post_meta($id, '_lz_service', $new_service);
        if ($new_date) update_post_meta($id, '_lz_date', $new_date);
        if ($new_time) update_post_meta($id, '_lz_time', $new_time);

        // Pulisci i meta pending
        delete_post_meta($id, '_lz_pending_action');
        delete_post_meta($id, '_lz_pending_service');
        delete_post_meta($id, '_lz_pending_date');
        delete_post_meta($id, '_lz_pending_time');

        // Notifica WhatsApp Cliente
        if (class_exists('LZ_WA_Sync')) {
            $client_id = get_post_meta($id, '_lz_client_id', true);
            $client_phone = get_user_meta($client_id, 'billing_phone', true) ?: get_user_meta($client_id, '_lz_phone', true);
            $client_name = get_the_title($id); // Il titolo è il nome cliente

            if ($client_phone) {
                $service_display = get_post_meta($id, '_lz_service', true);
                $date_display = date('d/m/Y', strtotime(get_post_meta($id, '_lz_date', true)));
                $time_display = get_post_meta($id, '_lz_time', true);
                
                LZ_WA_Sync::send_whatsapp_template(
                    $client_phone, 
                    'appuntamento_modificato', 
                    array(str_replace('Manuale: ', '', $client_name), "Lidia Zucaro Parrucchieri", $service_display, $date_display, $time_display)
                );
            }
        }

        wp_send_json_success('Modifica accettata e notificata al cliente.');
    }

    public function handle_admin_reject_edit() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Non autorizzato');

        $id = intval($_POST['booking_id']);
        
        // Pulisci i meta pending, l'appuntamento rimane come prima
        delete_post_meta($id, '_lz_pending_action');
        delete_post_meta($id, '_lz_pending_service');
        delete_post_meta($id, '_lz_pending_date');
        delete_post_meta($id, '_lz_pending_time');

        // Annulla l'appuntamento come richiesto dall'utente
        
        $client_name = get_the_title($id);
        $service_display = get_post_meta($id, '_lz_service', true);
        $date_display = date('d/m/Y', strtotime(get_post_meta($id, '_lz_date', true)));
        $time_display = get_post_meta($id, '_lz_time', true);
        $client_id = get_post_meta($id, '_lz_client_id', true);
        $client_phone = get_user_meta($client_id, 'billing_phone', true) ?: get_user_meta($client_id, '_lz_phone', true);
        
        wp_delete_post($id, true);

        // Notifica WhatsApp Cliente (Rifiutato -> Annullato)
        if (class_exists('LZ_WA_Sync') && $client_phone) {
            LZ_WA_Sync::send_whatsapp_template(
                $client_phone, 
                'appuntamento_annullato', 
                array(str_replace('Manuale: ', '', $client_name), "Lidia Zucaro Parrucchieri", $service_display, $date_display, $time_display)
            );
        }

        wp_send_json_success('La modifica è stata rifiutata, l\'appuntamento è stato rimosso e il cliente avvisato.');
    }

    public function handle_admin_approve_cancel() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Non autorizzato');

        $id = intval($_POST['booking_id']);
        
        // Prima di cancellarlo prendiamo i dati per la notifica
        $client_id = get_post_meta($id, '_lz_client_id', true);
        $client_phone = get_user_meta($client_id, 'billing_phone', true) ?: get_user_meta($client_id, '_lz_phone', true);
        $client_name = get_the_title($id);
        $service_display = get_post_meta($id, '_lz_service', true);
        $date_display = date('d/m/Y', strtotime(get_post_meta($id, '_lz_date', true)));
        $time_display = get_post_meta($id, '_lz_time', true);

        // Cancella appuntamento
        wp_delete_post($id, true);

        // Notifica WhatsApp Cliente
        if (class_exists('LZ_WA_Sync') && $client_phone) {
            LZ_WA_Sync::send_whatsapp_template(
                $client_phone, 
                'appuntamento_annullato', 
                array(str_replace('Manuale: ', '', $client_name), "Lidia Zucaro Parrucchieri", $service_display, $date_display, $time_display)
            );
        }

        wp_send_json_success('Appuntamento annullato con successo.');
    }

    public function handle_admin_reject_cancel() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Non autorizzato');

        $id = intval($_POST['booking_id']);
        
        // Il salone non accetta l'annullamento. Cancelliamo solo la request.
        delete_post_meta($id, '_lz_pending_action');

        wp_send_json_success('La richiesta di annullamento è stata ignorata.');
    }

    public function handle_admin_propose_edit() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Non autorizzato');

        $id = intval($_POST['booking_id']);
        $new_date = sanitize_text_field($_POST['date']);
        $new_time = sanitize_text_field($_POST['time']);
        $new_service = sanitize_text_field($_POST['service']);

        update_post_meta($id, '_lz_proposed_date', $new_date);
        update_post_meta($id, '_lz_proposed_time', $new_time);
        update_post_meta($id, '_lz_proposed_service', $new_service);
        update_post_meta($id, '_lz_status', 'pending_propose');

        $client_id = get_post_meta($id, '_lz_client_id', true);
        $client_phone = get_user_meta($client_id, 'billing_phone', true) ?: get_user_meta($client_id, '_lz_phone', true);
        $client_name = get_the_title($id);

        if (class_exists('LZ_WA_Sync') && $client_phone) {
            $formatted_date = date('d/m/Y', strtotime($new_date));
            // Invia template modifica_proposta. Pulsante dinmico usa $id.
            LZ_WA_Sync::send_whatsapp_template(
                $client_phone, 
                'modifica_proposta', 
                array(str_replace('Manuale: ', '', $client_name), "Lidia Zucaro Parrucchieri", $new_service, $formatted_date, $new_time),
                null,
                array("?edit_response=" . $id) // Questo diventerà https://lidiazucaro.it/?edit_response=ID
            );
        }

        wp_send_json_success('Proposta salvata e inviata al cliente.');
    }

    public function handle_client_edit_response() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        // Il cliente (utente normale) deve essere loggato
        if (!is_user_logged_in()) wp_send_json_error('Non autorizzato');

        $id = intval($_POST['booking_id']);
        $decision = sanitize_text_field($_POST['decision']);
        $post = get_post($id);

        if (!$post || get_post_meta($id, '_lz_client_id', true) != get_current_user_id() || get_post_meta($id, '_lz_status', true) !== 'pending_propose') {
            wp_send_json_error('Azione non valida o scaduta.');
        }

        if ($decision === 'accept') {
            // Applica i nuovi dati
            update_post_meta($id, '_lz_service', get_post_meta($id, '_lz_proposed_service', true));
            update_post_meta($id, '_lz_date', get_post_meta($id, '_lz_proposed_date', true));
            update_post_meta($id, '_lz_time', get_post_meta($id, '_lz_proposed_time', true));
            
            // Pulisci
            delete_post_meta($id, '_lz_proposed_service');
            delete_post_meta($id, '_lz_proposed_date');
            delete_post_meta($id, '_lz_proposed_time');
            
            // Imposta come approvato - farà trigger a WA Sync appuntamento_confermato in automatico tramite hook
            update_post_meta($id, '_lz_status', 'approved');
            do_action( 'lz_appointment_status_changed', $id, 'approved', 'pending_propose' );
            
            // Notifica all'admin (Lidia) che la proposta è stata accettata
            $admin_phone = get_option('lz_wa_admin_phone');
            if (class_exists('LZ_WA_Sync') && $admin_phone) {
                $client_name = get_the_title($id);
                $service = get_post_meta($id, '_lz_service', true);
                $date_fmt = date('d/m/Y', strtotime(get_post_meta($id, '_lz_date', true)));
                $time = get_post_meta($id, '_lz_time', true);
                
                $msg_admin = "✅ *PROPOSTA DI MODIFICA ACCETTATA*\n\n";
                $msg_admin .= "*CLIENTE:* " . str_replace('Manuale: ', '', $client_name) . "\n";
                $msg_admin .= "*SERVIZIO:* " . $service . "\n";
                $msg_admin .= "*DATA:* " . $date_fmt . "\n";
                $msg_admin .= "*ORA:* " . $time . "\n\n";
                $msg_admin .= "👉 *Gestisci qui:* " . home_url('/gestione/agenda/');
                
                LZ_WA_Sync::send_whatsapp_text($admin_phone, $msg_admin);
            }

            wp_send_json_success('Appuntamento Confermato!');
            
        } else {
            // Rifiutato
            $old_status = get_post_meta($id, '_lz_status', true);
            update_post_meta($id, '_lz_status', 'cancelled'); // Mettiamo in cancellato o trash
            do_action( 'lz_appointment_status_changed', $id, 'cancelled', $old_status );
            
            // Trigger annullato al volo per essere sicuri
            $client_id = get_post_meta($id, '_lz_client_id', true);
            $client_phone = get_user_meta($client_id, 'billing_phone', true) ?: get_user_meta($client_id, '_lz_phone', true);
            $client_name = get_the_title($id);
            if (class_exists('LZ_WA_Sync') && $client_phone) {
                $service = get_post_meta($id, '_lz_service', true); // Quei vecchi
                $date = date('d/m/Y', strtotime(get_post_meta($id, '_lz_date', true)));
                $time = get_post_meta($id, '_lz_time', true);
                LZ_WA_Sync::send_whatsapp_template(
                    $client_phone, 
                    'appuntamento_annullato', 
                    array(str_replace('Manuale: ', '', $client_name), "Lidia Zucaro Parrucchieri", $service, $date, $time)
                );
            }
            
            // Notifica all'admin (Lidia) che la proposta è stata rifiutata
            $admin_phone = get_option('lz_wa_admin_phone');
            if (class_exists('LZ_WA_Sync') && $admin_phone) {
                $service = get_post_meta($id, '_lz_service', true);
                $date_fmt = date('d/m/Y', strtotime(get_post_meta($id, '_lz_date', true)));
                $time = get_post_meta($id, '_lz_time', true);
                
                $msg_admin = "❌ *PROPOSTA DI MODIFICA RIFIUTATA*\n\n";
                $msg_admin .= "*CLIENTE:* " . str_replace('Manuale: ', '', $client_name) . "\n";
                $msg_admin .= "*SERVIZIO:* " . $service . "\n";
                $msg_admin .= "*DATA:* " . $date_fmt . "\n";
                $msg_admin .= "*ORA:* " . $time . "\n\n";
                $msg_admin .= "Il cliente non ha accettato la proposta. Contattalo su Whatsapp al " . $client_phone;
                
                LZ_WA_Sync::send_whatsapp_text($admin_phone, $msg_admin);
            }

            // Elimina post
            wp_delete_post($id, true);
            
            wp_send_json_success('Appuntamento Annullato');
        }
    }

    public function handle_client_update_profile() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        
        $uid = get_current_user_id();
        if (!$uid) {
            wp_send_json_error('Devi essere loggato per modificare il profilo.');
        }

        $first = sanitize_text_field($_POST['first_name']);
        $last  = sanitize_text_field($_POST['last_name']);
        $phone = sanitize_text_field($_POST['phone']);
        $email = sanitize_email($_POST['email']);
        $bday  = sanitize_text_field($_POST['birthday']);

        // Aggiorna info utente base (che impatta anche display_name in WP nativo)
        $user_data = array(
            'ID' => $uid,
            'first_name' => $first,
            'last_name' => $last,
            'display_name' => trim($first . ' ' . $last)
        );

        if (!empty($email)) {
            // Controlla che l'email non sia in uso da altri
            if(email_exists($email) && email_exists($email) !== $uid) {
                wp_send_json_error('Questa email è già registrata in un altro account.');
            }
            $user_data['user_email'] = $email;
        }

        $res = wp_update_user($user_data);
        if (is_wp_error($res)) {
            wp_send_json_error($res->get_error_message());
        }

        // Meta dati custom
        update_user_meta($uid, 'billing_phone', $phone);
        // Sync custom meta if missing
        update_user_meta($uid, '_lz_phone', $phone);
        
        if (!empty($bday)) {
            update_user_meta($uid, '_lz_birthday', $bday);
        } else {
            delete_user_meta($uid, '_lz_birthday');
        }

        wp_send_json_success('Profilo aggiornato con successo.');
    }

    public function handle_send_welcome_messages() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_users')) wp_send_json_error('No permissions');

        $last_sent = get_option('lz_last_welcome_sent', '2000-01-01 00:00:00');
        
        $args = array(
            'role' => 'subscriber',
            'orderby' => 'user_registered',
            'order' => 'ASC',
            'date_query' => array(
                array(
                    'after' => $last_sent,
                    'inclusive' => false,
                ),
            ),
        );

        $users = get_users($args);
        
        if (empty($users)) {
            wp_send_json_success('Nessun nuovo cliente a cui inviare il messaggio.');
        }

        $count = 0;
        foreach ($users as $user) {
            if ($this->send_welcome_whatsapp($user->ID, "112233")) {
                $count++;
            }
        }

        update_option('lz_last_welcome_sent', current_time('mysql'));
        wp_send_json_success("Operazione terminata! Inviati $count messaggi di benvenuto.");
    }

    public function handle_send_support_request() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error('Devi effettuare il login.');
        $msg = sanitize_textarea_field($_POST['message']);
        if (empty($msg)) wp_send_json_error('Messaggio vuoto.');

        if (class_exists('LZ_WA_Sync')) {
            LZ_WA_Sync::log_support_message(get_current_user_id(), $msg);
            wp_send_json_success('Inviato correttamente.');
        } else {
            wp_send_json_error('Modulo WhatsApp Sync non attivo.');
        }
    }

    public function handle_wa_admin_reply() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Permessi insufficienti.');
        
        $phone = sanitize_text_field($_POST['phone']);
        $message = sanitize_textarea_field($_POST['message']);
        
        if (empty($phone) || empty($message)) wp_send_json_error('Dati mancanti.');
        
        if (class_exists('LZ_WA_Sync')) {
            // Cerchiamo di trovare il nome utente dal telefono per salutarlo
            $users = get_users(array(
                'meta_query' => array(
                    'relation' => 'OR',
                    array('key' => 'billing_phone', 'value' => $phone, 'compare' => 'LIKE'),
                    array('key' => 'phone', 'value' => $phone, 'compare' => 'LIKE'),
                    array('key' => '_lz_phone', 'value' => $phone, 'compare' => 'LIKE')
                )
            ));
            
            $name = '';
            if (!empty($users)) {
                $name = $users[0]->first_name ?: $users[0]->display_name;
            }

            $greeting = $name ? "Ehi " . $name : "Ehi";
            $msg_body = $greeting . ", hai ricevuto una risposta per la tua richiesta di supporto.\n\n" .
                        "Lidia ti ha scritto:\n" .
                        "«" . $message . "»\n";

            $interactive_payload = array(
                "type" => "cta_url",
                "header" => array(
                    "type" => "text",
                    "text" => "Risposta Assistenza"
                ),
                "body" => array(
                    "text" => $msg_body
                ),
                "action" => array(
                    "name" => "cta_url",
                    "parameters" => array(
                        "display_text" => "Vai al Supporto",
                        "url" => home_url()
                    )
                )
            );

            $res = LZ_WA_Sync::send_whatsapp_interactive($phone, $interactive_payload);
            
            if ($res) {
                // Logghiamo comunque il body per avere traccia nel file di log
                LZ_WA_Sync::lz_log('WA_Sync Sending Body: ' . json_encode(array(
                    "to" => $phone,
                    "type" => "interactive",
                    "text" => array("body" => "Risposta inviata: " . $message)
                )));
                wp_send_json_success('Risposta inviata.');
            } else {
                wp_send_json_error('Errore durante l\'invio con le API di Meta.');
            }
        } else {
            wp_send_json_error('Modulo WhatsApp Sync non attivo.');
        }
    }

    /**
     * Invia il messaggio di benvenuto via WhatsApp a un utente specifico
     */
    private function send_welcome_whatsapp($user_id, $password) {
        $user = get_userdata($user_id);
        if (!$user) return false;

        $phone = get_user_meta($user_id, 'billing_phone', true) ?: get_user_meta($user_id, 'phone', true);
        if (empty($phone)) {
            $phone = get_user_meta($user_id, '_lz_phone', true);
        }

        if (empty($phone)) return false;

        $first_name = $user->first_name ?: $user->display_name;
        $username = $user->user_login;
        $site_url = home_url();

        if (class_exists('LZ_WA_Sync')) {
            $lz_key = get_user_meta($user_id, 'lz_access_key', true);
            $query_params = "?uid={$user_id}&key={$lz_key}";

            error_log("LZ_DEBUG: Invio TEMPLATE benvenuto...");
            $welcome_template = get_option('lz_wa_template_welcome', 'benvenuto_cliente');
            
            $res_tpl = LZ_WA_Sync::send_whatsapp_template(
                $phone, 
                $welcome_template, 
                array($first_name),            // Variabile {{1}} per il Body
                null, 
                array($query_params)           // Variabile dinamica per l'URL del Button (index 0)
            );
            return $res_tpl;
        }
        return false;
    }

    public function handle_import_clients() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if(!current_user_can('edit_users')) wp_send_json_error('No permissions');
        
        $clients_json = isset($_POST['clients']) ? stripslashes($_POST['clients']) : '';
        $clients = json_decode($clients_json, true);
        
        if (empty($clients)) wp_send_json_error('Dati non validi.');
        
        $count = 0;
        $skipped = 0;
        $password = "112233";
        
        foreach ($clients as $c) {
            $username = sanitize_user($c['username']);
            $email = sanitize_email($c['email']);
            
            if (username_exists($username) || ($email && email_exists($email))) {
                $skipped++;
                continue;
            }
            
            $uid = wp_create_user($username, $password, $email);
            
            if (!is_wp_error($uid)) {
                wp_update_user(array(
                    'ID' => $uid,
                    'first_name' => sanitize_text_field($c['first_name']),
                    'last_name' => sanitize_text_field($c['last_name']),
                    'display_name' => trim($c['first_name'] . ' ' . $c['last_name'])
                ));
                
                update_user_meta($uid, 'billing_phone', sanitize_text_field($c['phone']));
                
                // Genera chiave di accesso univoca e salva password temporanea
                update_user_meta($uid, 'lz_access_key', wp_generate_password(20, false));
                update_user_meta($uid, '_lz_initial_pass', $password);

                // Attiva Notifiche WhatsApp di default per importati
                update_user_meta($uid, '_lz_wa_optin', 'yes');
                
                // Invia benvenuto
                $this->send_welcome_whatsapp($uid, $password);
                $count++;
            }
        }
        
        wp_send_json_success("Importazione completata: $count creati, $skipped saltati perché già esistenti.");
    }

    /**
     * Serve PWA files (manifest.json, fcm-sw.js) at the root
     */
    public function handle_pwa_files() {
        $request_uri = $_SERVER['REQUEST_URI'];

        if (strpos($request_uri, '/manifest.json') !== false) {
            header('Content-Type: application/manifest+json; charset=utf-8');
            $start_url = parse_url(home_url('/clienti/'), PHP_URL_PATH) ?: '/clienti/';
            echo '{
    "name": "Lidia Zucaro Hairstylist",
    "short_name": "Lidia Zucaro",
    "description": "L\'App Ufficiale del Salone Lidia Zucaro. Prenota appuntamenti, accumula timbri fidelity e ricevi le notifiche!",
    "start_url": "' . $start_url . '",
    "display": "standalone",
    "background_color": "#000000",
    "theme_color": "#000000",
    "orientation": "portrait",
    "icons": [
        {
            "src": "/wp-content/uploads/2026/02/icon-192.png",
            "type": "image/png",
            "sizes": "192x192"
        },
        {
            "src": "/wp-content/uploads/2026/02/icon-512-1.png",
            "type": "image/png",
            "sizes": "512x512"
        }
    ]
}';
            exit;
        }

        if (strpos($request_uri, '/fcm-sw.js') !== false) {
            header('Content-Type: application/javascript; charset=utf-8');
            echo "importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/9.0.0/firebase-messaging-compat.js');

firebase.initializeApp({
    apiKey: 'AIzaSyA9PaNAmrd_jv44wjxNwTvfvg1JehI_WW4',
    authDomain: 'lidia-zucaro-app.firebaseapp.com',
    projectId: 'lidia-zucaro-app',
    storageBucket: 'lidia-zucaro-app.firebasestorage.app',
    messagingSenderId: '250320146866',
    appId: '1:250320146866:web:46602f58e482f4eb7914ab'
});

const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
    const notificationTitle = payload.notification?.title || 'Lidia Zucaro';
    const notificationOptions = {
        body: payload.notification?.body || 'Nuovo messaggio ricevuto.',
        icon: '/wp-content/uploads/2026/02/icon-192.png',
        badge: '/wp-content/uploads/2026/02/icon-192.png',
        vibrate: [200, 100, 200],
        tag: 'fcm-notification',
        renotify: true,
        data: payload.data || {}
    };
    return self.registration.showNotification(notificationTitle, notificationOptions);
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    event.waitUntil(clients.openWindow('/clienti/'));
});";
            exit;
        }
    }

    /**
     * Handle Password Change for Clients
     */
    public function handle_change_password() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if ( ! is_user_logged_in() ) wp_send_json_error('Sessione scaduta.');
        
        $user_id = get_current_user_id();
        $pwd = $_POST['pwd'];
        $pwd_confirm = $_POST['pwd_confirm'];
        
        if ( empty($pwd) || strlen($pwd) < 6 ) {
            wp_send_json_error('La password deve essere di almeno 6 caratteri.');
        }
        
        if ( $pwd !== $pwd_confirm ) {
            wp_send_json_error('Le password non coincidono.');
        }
        
        wp_set_password( $pwd, $user_id );
        update_user_meta( $user_id, '_lz_first_login_done', 'yes' );
        
        wp_send_json_success('Password aggiornata con successo!');
    }

    /**
     * Mark Promotion as Seen
     */
    public function handle_mark_promo_seen() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!is_user_logged_in()) wp_send_json_error();
        
        $user_id = get_current_user_id();
        update_user_meta($user_id, '_lz_promo_insite_seen', 'yes');
        
        // Aggiorna lo storico se presente
        $history = get_user_meta($user_id, '_lz_promo_history', true);
        if ($history && is_array($history)) {
            $last_key = count($history) - 1;
            if ($history[$last_key]['seen'] !== 'yes') {
                $history[$last_key]['seen'] = 'yes';
                $history[$last_key]['seen_at'] = current_time('mysql');
                update_user_meta($user_id, '_lz_promo_history', $history);
            }
        }

        wp_send_json_success();
    }

    public function handle_save_promo() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Non autorizzato');

        $p_id = !empty($_POST['promo_id']) ? intval($_POST['promo_id']) : 0;
        $title = sanitize_text_field($_POST['p_name']);
        
        $post_data = array(
            'post_title'   => $title,
            'post_type'    => 'lz_promo',
            'post_status'  => 'publish'
        );

        if ($p_id > 0) {
            $post_data['ID'] = $p_id;
            wp_update_post($post_data);
        } else {
            $p_id = wp_insert_post($post_data);
        }

        update_post_meta($p_id, '_lz_promo_title', sanitize_text_field($_POST['p_title']));
        update_post_meta($p_id, '_lz_promo_badge', sanitize_text_field($_POST['p_badge']));
        update_post_meta($p_id, '_lz_promo_desc', wp_kses_post($_POST['p_desc']));
        update_post_meta($p_id, '_lz_promo_footer', sanitize_text_field($_POST['p_footer']));
        
        $is_active = isset($_POST['p_active']) && $_POST['p_active'] === 'active' ? 'active' : 'inactive';
        
        if ($is_active === 'active') {
            // Disattiva tutte le altre
            $all_promos = get_posts(array('post_type' => 'lz_promo', 'posts_per_page' => -1, 'fields' => 'ids'));
            foreach ($all_promos as $oid) {
                update_post_meta($oid, '_lz_promo_status', 'inactive');
            }
        }
        update_post_meta($p_id, '_lz_promo_status', $is_active);

        wp_send_json_success('Promozione salvata!');
    }

    public function handle_delete_promo() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Non autorizzato');
        
        $p_id = intval($_POST['promo_id']);
        wp_delete_post($p_id, true);
        wp_send_json_success('Promozione eliminata.');
    }

    public function handle_get_promo_recipients() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Non autorizzato');

        $args = array(
            'meta_key'     => '_lz_promo_insite_received',
            'meta_value'   => 'yes',
            'fields'       => 'all'
        );
        $users = get_users($args);
        
        $results = array();
        foreach($users as $user) {
            $seen = get_user_meta($user->ID, '_lz_promo_insite_seen', true) === 'yes';
            $results[] = array(
                'name' => $user->first_name ?: $user->display_name,
                'seen' => $seen
            );
        }

        wp_send_json_success($results);
    }

    /**
     */
    public function handle_send_group_message() {
        check_ajax_referer('lz_ajax_nonce', 'security');
        if (!current_user_can('edit_posts')) wp_send_json_error('Non autorizzato');

        $mode = sanitize_text_field($_POST['mode']);
        $count = 0;

        if ($mode === 'birthday') {
            // Trova utenti che compiono gli anni oggi
            $today_md = date('m-d'); // Assumiamo formato YYYY-MM-DD
            
            $args = array(
                'role__in' => array('customer', 'subscriber'),
                'meta_query' => array(
                    array(
                        'key' => '_lz_birthday',
                        'value' => '-' . $today_md,
                        'compare' => 'LIKE'
                    )
                )
            );
            $users = get_users($args);
            
            $text_template = "Ciao [nome], tantissimi auguri di buon compleanno! 🎉\nConcediti un momento per te: prenota un appuntamento nei prossimi giorni e riceverai uno sconto speciale per festeggiare. Ti aspettiamo in salone! 💇♀️✨";

            foreach ($users as $user) {
                $phone = get_user_meta($user->ID, 'billing_phone', true) ?: get_user_meta($user->ID, '_lz_phone', true);
                if ($phone) {
                    $first_name = $user->first_name ?: $user->display_name;
                    $message = str_replace('[nome]', $first_name, $text_template);
                    if (class_exists('LZ_WA_Sync')) {
                        LZ_WA_Sync::send_whatsapp_text($phone, $message);
                        $count++;
                    }
                }
            }
            wp_send_json_success("Auguri inviati con successo a $count festeggiati!");

        } elseif ($mode === 'custom') {
            $recipient_ids = json_decode(stripslashes($_POST['recipients']), true);
            $custom_text   = isset($_POST['message']) ? trim(sanitize_textarea_field($_POST['message'])) : '';
            $template_name = isset($_POST['template']) ? sanitize_text_field($_POST['template']) : '';

            if (empty($recipient_ids) || (empty($custom_text) && empty($template_name))) {
                wp_send_json_error('Dati mancanti per l\'invio personalizzato.');
            }

            // Se il testo è esattamente 'promo_insite', lo trattiamo come il template promo_insite (fallback per compatibilità)
            if ($custom_text === 'promo_insite') {
                $template_name = 'promo_insite';
            }

            $is_promo_insite = ($template_name === 'promo_insite');
            $active_promo_id = 0;
            if ($is_promo_insite) {
                $active_promos = get_posts(array(
                    'post_type'      => 'lz_promo',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array('key' => '_lz_promo_status', 'value' => 'active')
                    )
                ));
                if (!empty($active_promos)) {
                    $active_promo_id = $active_promos[0];
                }
            }

            foreach ($recipient_ids as $uid) {
                $user = get_userdata($uid);
                $phone = get_user_meta($uid, 'billing_phone', true) ?: get_user_meta($uid, '_lz_phone', true);
                if ($user && $phone) {
                    $first_name = $user->first_name ?: $user->display_name;
                    
                    if ($template_name) {
                        // Invio Template
                        if (class_exists('LZ_WA_Sync')) {
                            // Se è promo_insite o registrazione (per test), flagghiamo l'utente
                            if ($is_promo_insite || $template_name === 'registrazione') {
                                update_user_meta($uid, '_lz_promo_insite_received', 'yes');
                                update_user_meta($uid, '_lz_promo_insite_seen', 'no');

                                // Aggiungi allo storico
                                if ($active_promo_id) {
                                    $history = get_user_meta($uid, '_lz_promo_history', true) ?: array();
                                    $history[] = array(
                                        'id' => $active_promo_id,
                                        'sent_at' => current_time('mysql'),
                                        'seen' => 'no'
                                    );
                                    update_user_meta($uid, '_lz_promo_history', $history);
                                }
                                
                                if ($is_promo_insite) {
                                    LZ_WA_Sync::send_whatsapp_template($phone, 'promo_insite', array($first_name, "Lidia Zucaro Parrucchieri"));
                                } else {
                                    // registrazione o altro test
                                    $button_params = array();
                                    if ($template_name === 'registrazione') {
                                        $lz_key = get_user_meta($uid, 'lz_access_key', true);
                                        $button_params = array("?uid={$uid}&key={$lz_key}");
                                    }
                                    LZ_WA_Sync::send_whatsapp_template($phone, $template_name, array($first_name), null, $button_params);
                                }
                            } else {
                                // Altri template ad-hoc
                                LZ_WA_Sync::send_whatsapp_template($phone, $template_name, array($first_name));
                            }
                            $count++;
                        }
                    } else {
                        // Invio Testo Libero
                        $message = "Ciao $first_name, " . $custom_text;
                        if (class_exists('LZ_WA_Sync')) {
                            LZ_WA_Sync::send_whatsapp_text($phone, $message);
                            $count++;
                        }
                    }
                }
            }
            wp_send_json_success("Messaggio inviato correttamente a $count destinatari!");
        }

        wp_send_json_error('Modalità non valida.');
    }

}

new LZ_Booking_Engine();
