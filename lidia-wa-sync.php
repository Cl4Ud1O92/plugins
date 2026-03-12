<?php
/**
 * Lidia Zucaro - WhatsApp Business Cloud API Sync
 * Gestisce l'invio di messaggi WhatsApp automatici (Template) tramite le API ufficiali di Meta.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LZ_WA_Sync {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        
        // Handler per il Test Webhook Rapido e Log
        add_action( 'wp_ajax_lz_wa_test_webhook', array( $this, 'ajax_test_webhook' ) );
        add_action( 'wp_ajax_lz_wa_test_log', array( $this, 'ajax_test_log' ) );
        add_action( 'wp_ajax_lz_wa_clear_logs', array( $this, 'ajax_clear_logs' ) );
        add_action( 'wp_ajax_lz_wa_test_template', array( $this, 'ajax_test_template' ) );
        add_action( 'wp_ajax_lz_wa_test_admin_notif', array( $this, 'ajax_test_admin_notif' ) );
        add_action( 'wp_ajax_lz_wa_simulate_msg', array( $this, 'ajax_simulate_msg' ) );
        add_action( 'wp_ajax_lz_get_wa_messages', array( $this, 'ajax_get_wa_messages' ) );
        add_action( 'wp_ajax_lz_wa_refresh_number', array( $this, 'ajax_refresh_number' ) );
        
        // Listener per cambio stato appuntamento -> Messaggio WhatsApp
        add_action( 'lz_appointment_status_changed', array( $this, 'sync_appointment_to_wa' ), 20, 3 );
        
        // Listener per nuova prenotazione da webapp -> Notifica Admin
        add_action( 'lz_new_booking_created', array( $this, 'notify_admin_on_new_booking' ) );

        // Registrazione Webhook Endpoint (REST API)
        add_action( 'rest_api_init', array( $this, 'register_webhook_route' ) );

        // Cron Job per Promemoria Giornaliero
        /*
        if ( ! wp_next_scheduled( 'lz_wa_daily_reminders' ) ) {
            wp_schedule_event( strtotime('09:00:00'), 'daily', 'lz_wa_daily_reminders' );
        }
        add_action( 'lz_wa_daily_reminders', array( $this, 'send_daily_reminders' ) );
        */
    }

    public function register_webhook_route() {
        register_rest_route( 'lz-wa/v1', '/cloud-gateway-handler', array(
            'methods'  => array( 'GET', 'POST' ),
            'callback' => array( $this, 'handle_webhook' ),
            'permission_callback' => '__return_true', // Meta richiede accesso pubblico
        ) );
    }

    public function add_settings_page() {
        add_options_page(
            'WhatsApp Sync Settings',
            'WhatsApp Sync',
            'manage_options',
            'lz-wa-settings',
            array( $this, 'render_settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'lz_wa_group', 'lz_wa_access_token' );
        register_setting( 'lz_wa_group', 'lz_wa_phone_number_id' );
        register_setting( 'lz_wa_group', 'lz_wa_verify_token' );
        register_setting( 'lz_wa_group', 'lz_wa_admin_phone' );
        register_setting( 'lz_wa_group', 'lz_wa_template_approved' );
        register_setting( 'lz_wa_group', 'lz_wa_template_rejected' );
        register_setting( 'lz_wa_group', 'lz_wa_template_reminder' );
        register_setting( 'lz_wa_group', 'lz_wa_template_welcome' );
        register_setting( 'lz_wa_group', 'lz_wa_template_lang' );
        register_setting( 'lz_wa_group', 'lz_wa_adhoc_templates' );
        register_setting( 'lz_wa_group', 'lz_wa_active_number' );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap lz-settings-wrap">
            <h1 class="lz-main-title">Configurazione WhatsApp Business API (Meta Cloud)</h1>
            <p class="lz-main-desc">Gestisci la connessione tra il tuo salone e le API ufficiali di WhatsApp per automatizzare le comunicazioni.</p>
            
            <style>
                .lz-settings-wrap { max-width: 900px; margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
                .lz-main-title { font-weight: 800; font-size: 28px; margin-bottom: 5px; }
                .lz-main-desc { color: #666; font-size: 15px; margin-bottom: 30px; }
                
                /* Accordion Styles */
                .lz-accordion { border: 1px solid #e0e0e0; border-radius: 12px; overflow: hidden; background: #fff; margin-bottom: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.02); transition: all 0.3s ease; }
                .lz-accordion:hover { border-color: #ccc; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
                .lz-accordion.active { border-color: #000; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
                
                .lz-accordion-header { padding: 18px 25px; background: #fff; cursor: pointer; display: flex; justify-content: space-between; align-items: center; user-select: none; transition: background 0.2s; }
                .lz-accordion-header:hover { background: #fcfcfc; }
                .lz-accordion-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #1d2327; display: flex; align-items: center; gap: 10px; }
                .lz-accordion-header .lz-icon { font-size: 20px; color: #999; transition: transform 0.3s ease; }
                .lz-accordion.active .lz-icon { transform: rotate(180_deg); color: #000; }
                
                .lz-accordion-content { padding: 0 25px; max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out, padding 0.3s ease; background: #fff; border-top: 0px solid #eee; }
                .lz-accordion.active .lz-accordion-content { padding: 25px; max-height: 2000px; border-top-width: 1px; }
                
                /* Form Control Styles */
                .lz-form-group { margin-bottom: 25px; }
                .lz-form-group:last-child { margin-bottom: 0; }
                .lz-form-group label { display: block; font-weight: 700; font-size: 13px; color: #1d2327; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
                .lz-form-group input[type="text"], .lz-form-group input[type="password"], .lz-form-group input[type="email"] { 
                    width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; background: #fdfdfd; transition: all 0.2s;
                }
                .lz-form-group input:focus { border-color: #000; outline: none; box-shadow: 0 0 0 2px rgba(0,0,0,0.05); background: #fff; }
                .lz-form-group .description { margin-top: 8px; font-size: 13px; color: #777; line-height: 1.5; }
                
                /* Password Toggle UI */
                .lz-pass-wrapper { position: relative; }
                .lz-toggle-pass { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #999; font-size: 18px; padding: 5px; }
                .lz-toggle-pass:hover { color: #000; }
                
                /* Readonly / Badge */
                .lz-readonly { background: #f5f5f5 !important; color: #555; font-weight: 600; cursor: default; }
                .lz-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 800; text-transform: uppercase; }
                .lz-badge-green { background: #e6fcf5; color: #0ca678; }
                
                /* Diagnostics UI */
                .lz-diag-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 25px; }
                .lz-diag-btn { 
                    padding: 12px; border-radius: 10px; border: 1px solid #eee; background: #fff; cursor: pointer; font-weight: 600; font-size: 13px;
                    display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s;
                }
                .lz-diag-btn:hover { background: #f9f9f9; border-color: #ddd; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.05); }
                .lz-diag-btn.primary { background: #000; color: #fff; border: none; }
                .lz-diag-btn.primary:hover { background: #222; }
                
                .lz-log-viewer { 
                    background: #1a1a1a; color: #a9ffaf; padding: 20px; border-radius: 12px; font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
                    font-size: 12px; line-height: 1.6; height: 350px; overflow-y: auto; box-shadow: inset 0 2px 10px rgba(0,0,0,0.2); margin-top: 15px;
                }
                
                .lz-save-bar { position: sticky; bottom: 20px; background: rgba(255,255,255,0.9); backdrop-filter: blur(10px); padding: 15px 25px; border-radius: 15px; margin-top: 30px; border: 1px solid #ddd; display: flex; justify-content: flex-end; z-index: 100; box-shadow: 0 -5px 20px rgba(0,0,0,0.05); }
                .lz-save-btn { background: #000; color: #fff; border: none; padding: 12px 35px; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; transition: transform 0.2s; }
                .lz-save-btn:hover { background: #222; transform: scale(1.02); }
                .lz-save-btn:active { transform: scale(0.98); }
                
                /* Info section */
                .lz-info-box { background: #f8f9fa; border-left: 4px solid #000; padding: 20px; border-radius: 0 8px 8px 0; font-size: 14px; }
                .lz-info-box ol { padding-left: 20px; }
                .lz-info-box li { margin-bottom: 10px; }
                
                @media (max-width: 600px) {
                    .lz-accordion-header { padding: 15px; }
                    .lz-accordion-content { padding: 15px !important; }
                    .lz-diag-grid { grid-template-columns: 1fr; }
                }
            </style>

            <form method="post" action="options.php">
                <?php settings_fields( 'lz_wa_group' ); ?>
                
                <!-- SECTION 1: Numero di Telefono -->
                <div class="lz-accordion active">
                    <div class="lz-accordion-header">
                        <h3>📱 Numero di Telefono</h3>
                        <span class="lz-icon"></span>
                    </div>
                    <div class="lz-accordion-content">
                        <div class="lz-form-group">
                            <label>Numero di telefono selezionato (in uso)</label>
                            <div style="display:flex; gap:10px;">
                                <input type="text" name="lz_wa_active_number" id="lz_wa_active_number" class="lz-readonly" value="<?php echo esc_attr( get_option('lz_wa_active_number', 'Nessuno' ) ); ?>" readonly style="flex:1;" />
                                <button type="button" id="btn-refresh-number" class="lz-diag-btn" style="white-space:nowrap; margin:0;">🔄 Aggiorna da Meta</button>
                            </div>
                            <p class="description">Questo numero è quello attualmente collegato al tuo Phone Number ID.</p>
                        </div>
                        <div class="lz-form-group">
                            <label>Phone Number ID</label>
                            <input type="text" name="lz_wa_phone_number_id" value="<?php echo esc_attr( get_option('lz_wa_phone_number_id') ); ?>" placeholder="Es: 1092837465..." />
                            <p class="description">L'ID numerico univoco del tuo numero nel portale Meta Developers.</p>
                        </div>
                        <div class="lz-form-group">
                            <label>Access Token (Permanente)</label>
                            <div class="lz-pass-wrapper">
                                <input type="password" name="lz_wa_access_token" id="lz_wa_access_token" value="<?php echo esc_attr( get_option('lz_wa_access_token') ); ?>" />
                                <span class="lz-toggle-pass" onclick="togglePassword('lz_wa_access_token')">👁️</span>
                            </div>
                            <p class="description">Il System User Access Token generato nel Business Manager di Meta.</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION 2: Webhook -->
                <div class="lz-accordion">
                    <div class="lz-accordion-header">
                        <h3>🔗 Webhook</h3>
                        <span class="lz-icon"></span>
                    </div>
                    <div class="lz-accordion-content">
                        <div class="lz-form-group">
                            <label>URL del Webhook</label>
                            <input type="text" class="lz-readonly" value="<?php echo esc_url( get_rest_url( null, 'lz-wa/v1/cloud-gateway-handler' ) ); ?>" readonly />
                            <p class="description">Copia questo URL nel campo "Callback URL" nel portale Meta Developers -> WhatsApp -> Configuration.</p>
                        </div>
                        <div class="lz-form-group">
                            <label>Verify Token</label>
                            <input type="text" name="lz_wa_verify_token" value="<?php echo esc_attr( get_option('lz_wa_verify_token', 'lidia_zucaro_bot_2026') ); ?>" />
                            <p class="description">Deve corrispondere esattamente al "Verify Token" inserito su Meta.</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: Numero di telefono admin -->
                <div class="lz-accordion">
                    <div class="lz-accordion-header">
                        <h3>👩💼 Numero di telefono admin</h3>
                        <span class="lz-icon"></span>
                    </div>
                    <div class="lz-accordion-content">
                        <div class="lz-form-group">
                            <label>Numero whatsapp su cui ricevere le richieste</label>
                            <input type="text" name="lz_wa_admin_phone" value="<?php echo esc_attr( get_option('lz_wa_admin_phone') ); ?>" placeholder="Esempio: 393471234567" />
                            <p class="description">Inserisci il numero (con prefisso 39) dove riceverai le notifiche delle nuove prenotazioni.</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION 4: Template -->
                <div class="lz-accordion">
                    <div class="lz-accordion-header">
                        <h3>📝 Template WhatsApp</h3>
                        <span class="lz-icon"></span>
                    </div>
                    <div class="lz-accordion-content">
                        <div class="lz-form-group">
                            <label>Lingua Template (Codice ISO)</label>
                            <input type="text" name="lz_wa_template_lang" value="<?php echo esc_attr( get_option('lz_wa_template_lang', 'it') ); ?>" placeholder="it" />
                        </div>
                        <div class="lz-form-group">
                            <label>Nome Template: Appuntamento Confermato</label>
                            <input type="text" name="lz_wa_template_approved" value="<?php echo esc_attr( get_option('lz_wa_template_approved', 'appuntamento_confermato') ); ?>" />
                        </div>
                        <div class="lz-form-group">
                            <label>Nome Template: Appuntamento Rifiutato/Annullato</label>
                            <input type="text" name="lz_wa_template_rejected" value="<?php echo esc_attr( get_option('lz_wa_template_rejected', 'appuntamento_annullato') ); ?>" />
                        </div>
                        <div class="lz-form-group">
                            <label>Nome Template: Promemoria 24h</label>
                            <input type="text" name="lz_wa_template_reminder" value="<?php echo esc_attr( get_option('lz_wa_template_reminder', 'promemoria_domani') ); ?>" />
                        </div>
                        <div class="lz-form-group">
                            <label>Nome Template: Benvenuto (Nuova Cliente)</label>
                            <input type="text" name="lz_wa_template_welcome" value="<?php echo esc_attr( get_option('lz_wa_template_welcome', 'benvenuto_cliente') ); ?>" />
                        </div>
                        <div class="lz-form-group">
                            <label>Template Messaggi Rapidi (Pannello Cliente)</label>
                            <input type="text" name="lz_wa_adhoc_templates" value="<?php echo esc_attr( get_option('lz_wa_adhoc_templates') ); ?>" placeholder="comunicazione_generica, promemoria_rapido" />
                            <p class="description">Inserisci i nomi dei template separati da virgola.</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION 5: Strumenti di Diagnosi -->
                <div class="lz-accordion">
                    <div class="lz-accordion-header">
                        <h3>🛠️ Strumenti di Diagnosi</h3>
                        <span class="lz-icon"></span>
                    </div>
                    <div class="lz-accordion-content">
                        <div class="lz-diag-grid">
                            <button type="button" id="btn-test-webhook" class="lz-diag-btn">🔌 Test Webhook Interno</button>
                            <button type="button" id="btn-force-log" class="lz-diag-btn">📝 Scrivi Log di Test</button>
                            <button type="button" id="btn-test-template" class="lz-diag-btn">🧪 Test Invia Template</button>
                            <button type="button" id="btn-test-admin-notif" class="lz-diag-btn primary">🔔 Test Notifica Admin</button>
                            <button type="button" id="btn-simulate-msg" class="lz-diag-btn">👤 Simula Messaggio Cliente</button>
                        </div>
                        
                        <div id="webhook-test-result" style="padding: 10px; font-weight: 600; text-align: center; font-size: 13px;"></div>

                        <div style="margin-top:20px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
                                <label style="margin:0;">📋 Log delle Comunicazioni (Debug)</label>
                                <button type="button" id="btn-clear-logs" style="background:none; border:none; color:#d63638; cursor:pointer; font-size:12px; font-weight:700; text-decoration:underline;">Svuota Log</button>
                            </div>
                            <div id="wa-log-viewer" class="lz-log-viewer">
                                <?php echo nl2br( esc_html( $this->get_latest_logs() ) ); ?>
                            </div>
                            <p class="description">I log si aggiornano automaticamente ogni 5 secondi.</p>
                        </div>
                    </div>
                </div>

                <!-- SECTION 6: Informazioni Formattazione Template -->
                <div class="lz-accordion">
                    <div class="lz-accordion-header">
                        <h3>ℹ️ Informazioni Formattazione Template</h3>
                        <span class="lz-icon"></span>
                    </div>
                    <div class="lz-accordion-content">
                        <div class="lz-info-box">
                            <p>La nostra integrazione passa <strong>5 variabili dinamiche</strong> ai tuoi template:</p>
                            
                            <div style="margin-bottom:15px;">
                                <strong>Per Appuntamenti:</strong>
                                <ol>
                                    <li><code>{{1}}</code>: Nome Cliente</li>
                                    <li><code>{{2}}</code>: Salone (Lidia Zucaro Parrucchieri)</li>
                                    <li><code>{{3}}</code>: Trattamento</li>
                                    <li><code>{{4}}</code>: Data</li>
                                    <li><code>{{5}}</code>: Orario</li>
                                </ol>
                            </div>

                            <div>
                                <strong>Per Benvenuto:</strong>
                                <ol>
                                    <li><code>{{1}}</code>: Nome Cliente</li>
                                    <li><strong>Body Link</strong>: Il sistema aggiunge il parametro di login automatico all'URL del bottone.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lz-save-bar">
                    <?php submit_button('Salva Configurazioni', 'primary lz-save-btn', 'submit', false); ?>
                </div>
            </form>
        </div>

        <script>
        function togglePassword(id) {
            const input = document.getElementById(id);
            const btn = input.nextElementSibling;
            if (input.type === "password") {
                input.type = "text";
                btn.innerText = "🔒";
            } else {
                input.type = "password";
                btn.innerText = "👁️";
            }
        }

        jQuery(document).ready(function($) {
            // Accordion Logic
            $('.lz-accordion-header').click(function() {
                const parent = $(this).parent();
                const wasActive = parent.hasClass('active');
                
                // Opzionale: chiudi gli altri (se vuoi un vero accordion)
                // $('.lz-accordion').removeClass('active');
                
                if(!wasActive) {
                    parent.addClass('active');
                } else {
                    parent.removeClass('active');
                }
            });

            // Diagnostic logic (Existing)
            $('#btn-refresh-number').click(function() {
                var btn = $(this);
                btn.attr('disabled', true).text('⏳...');
                $.post(ajaxurl, {
                    action: 'lz_wa_refresh_number',
                    _ajax_nonce: '<?php echo wp_create_nonce("lz_wa_test_webhook"); ?>'
                }, function(response) {
                    btn.attr('disabled', false).text('🔄 Aggiorna da Meta');
                    if (response.success) {
                        $('#lz_wa_active_number').val(response.data);
                        alert('✅ Numero aggiornato con successo!');
                    } else {
                        alert('❌ Errore: ' + response.data);
                    }
                });
            });

            $('#btn-test-webhook').click(function() {
                var btn = $(this);
                var result = $('#webhook-test-result');
                btn.attr('disabled', true).text('Test in corso...');
                result.html('⏳ Controllo...');

                $.post(ajaxurl, {
                    action: 'lz_wa_test_webhook',
                    _ajax_nonce: '<?php echo wp_create_nonce("lz_wa_test_webhook"); ?>'
                }, function(response) {
                    btn.attr('disabled', false).text('🔌 Test Webhook Interno');
                    if (response.success) {
                        result.html('<span style="color:green;">✅ Webhook OK!</span> ' + response.data);
                    } else {
                        result.html('<span style="color:red;">❌ Errore:</span> ' + response.data);
                    }
                });
            });

            $('#btn-force-log').click(function() {
                var result = $('#webhook-test-result');
                $.post(ajaxurl, {
                    action: 'lz_wa_test_log',
                    _ajax_nonce: '<?php echo wp_create_nonce("lz_wa_test_webhook"); ?>'
                }, function(response) {
                    result.html('Log scritto. Controlla il riquadro nero sotto.');
                });
            });

            $('#btn-test-template').click(function() {
                if(!confirm('Vuoi inviare un template di prova al numero Admin?')) return;
                var btn = $(this);
                var result = $('#webhook-test-result');
                btn.attr('disabled', true).text('Invio...');
                result.html('⏳ Invio template...');

                $.post(ajaxurl, {
                    action: 'lz_wa_test_template',
                    _ajax_nonce: '<?php echo wp_create_nonce("lz_wa_test_webhook"); ?>'
                }, function(response) {
                    btn.attr('disabled', false).text('🧪 Test Invia Template');
                    if (response.success) {
                        result.html('<span style="color:green;">✅ Template inviato!</span>');
                    } else {
                        result.html('<span style="color:red;">❌ Errore:</span> ' + response.data);
                    }
                }, 'json');
            });

            $('#btn-test-admin-notif').click(function() {
                if(!confirm('Simulare una notifica admin?')) return;
                var btn = $(this);
                var result = $('#webhook-test-result');
                btn.attr('disabled', true).text('Invio...');

                $.post(ajaxurl, {
                    action: 'lz_wa_test_admin_notif',
                    _ajax_nonce: '<?php echo wp_create_nonce("lz_wa_test_webhook"); ?>'
                }, function(response) {
                    btn.attr('disabled', false).text('🔔 Test Notifica Admin');
                    if (response.success) {
                        result.html('<span style="color:green;">✅ Messaggio inviato!</span>');
                    } else {
                        result.html('<span style="color:red;">❌ Errore API:</span> ' + response.data);
                    }
                }, 'json');
            });

            $('#btn-simulate-msg').click(function() {
                var msg = prompt("Cosa scriverebbe il cliente?", "Buongiorno!");
                if(!msg) return;
                var result = $('#webhook-test-result');
                result.html('⏳ Simulazione...');

                $.post(ajaxurl, {
                    action: 'lz_wa_simulate_msg',
                    message: msg,
                    _ajax_nonce: '<?php echo wp_create_nonce("lz_wa_test_webhook"); ?>'
                }, function(response) {
                    if (response.success) {
                        result.html('<span style="color:green;">✅ Simulazione completata!</span>');
                    } else {
                        result.html('<span style="color:red;">❌ Errore simulazione:</span> ' + response.data);
                    }
                }, 'json');
            });

            $('#btn-clear-logs').click(function() {
                if(!confirm('Cancellare i log?')) return;
                $.post(ajaxurl, { action: 'lz_wa_clear_logs', _ajax_nonce: '<?php echo wp_create_nonce("lz_wa_test_webhook"); ?>' }, function() {
                    $('#wa-log-viewer').html('Logs svuotati.');
                });
            });
            
            setInterval(function() {
                if ($('#wa-log-viewer').is(':visible')) {
                    $.get(location.href, function(data) {
                        var newLog = $(data).find('#wa-log-viewer').html();
                        $('#wa-log-viewer').html(newLog);
                    });
                }
            }, 5000);
        });
        </script>
        <?php
    }

    /**
     * Helper per loggare su file dedicato
     */
    public static function lz_log($message) {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/wa-sync.log';
        $time = date('Y-m-d H:i:s');
        $msg = "[{$time}] {$message}\n";
        @file_put_contents($log_file, $msg, FILE_APPEND);
        // Backup su log di sistema per facilitare il debug se non trovo il file
        error_log("LZ_WA: " . $message);
    }

    /**
     * Logga una richiesta di supporto interna
     */
    public static function log_support_message($user_id, $message) {
        $user = get_userdata($user_id);
        if (!$user) return;
        $name = $user->display_name;
        $phone = get_user_meta($user_id, 'billing_phone', true) ?: 'N/D';
        $username = $user->user_login;
        
        $log_entry = "SUPPORT: User {$name} (@{$username}, Tel: {$phone}) ha richiesto assistenza: {$message}";
        self::lz_log($log_entry);
    }

    private function get_latest_logs() {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/wa-sync.log';
        if (!file_exists($log_file)) return "Nessun log presente in: " . $log_file;
        $content = @file_get_contents($log_file);
        if ($content === false) return "Errore nel leggere il file log (permessi?).";
        $lines = explode("\n", $content);
        return implode("\n", array_slice($lines, -50));
    }

    public function ajax_clear_logs() {
        check_ajax_referer('lz_wa_test_webhook');
        $upload_dir = wp_upload_dir();
        @unlink($upload_dir['basedir'] . '/wa-sync.log');
        wp_send_json_success();
    }



    public function ajax_test_template() {
        check_ajax_referer('lz_wa_test_webhook');
        $admin_phone = trim(get_option('lz_wa_admin_phone'));
        $template = trim(get_option('lz_wa_template_approved', 'appuntamento_confermato'));
        
        if (empty($admin_phone)) {
            wp_send_json(array('success' => false, 'data' => 'Numero Admin non configurato nelle impostazioni.'));
        }

        self::lz_log("AJAX: Manual template test triggered for {$admin_phone} with template '{$template}'");

        $res = self::send_whatsapp_template($admin_phone, $template, array(
            "Test Admin",
            "Lidia Zucaro Parrucchieri",
            "Trattamento Test",
            date('d/m/Y'),
            "12:00"
        ));

        if ($res['success']) {
            wp_send_json(array('success' => true, 'data' => 'Inviato!'));
        } else {
            wp_send_json(array('success' => false, 'data' => $res['error']));
        }
    }

    public function ajax_test_admin_notif() {
        check_ajax_referer('lz_wa_test_webhook');
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');

        $admin_phone = get_option('lz_wa_admin_phone');
        if (empty($admin_phone)) {
            wp_send_json(array('success' => false, 'data' => 'Numero Admin non presente nelle impostazioni.'));
        }

        $test_data = array(
            'client' => 'TEST DIAGONOSTICO',
            'service' => 'Test Sistema',
            'date' => date('d/m/Y'),
            'time' => date('H:i'),
            'sender_id' => '0000000000'
        );

        self::lz_log("AJAX: Manual Admin notification test triggered.");
        
        // Costruiamo il messaggio come farebbe il sistema
        $message = "TITOLO: Test Notifica Admin\n";
        $message .= "NOME: " . $test_data['client'] . "\n";
        $message .= "SERVIZIO: " . $test_data['service'] . "\n";
        $message .= "DATA: " . $test_data['date'] . "\n";
        $message .= "ORA: " . $test_data['time'];

        $res = self::send_whatsapp_text($admin_phone, $message);
        
        if ($res) {
            wp_send_json_success('Inviato con successo.');
        } else {
            wp_send_json(array('success' => false, 'data' => 'Errore nell\'invio tramite Meta API. Verifica se la finestra di 24h è aperta (manda un messaggio al bot).'));
        }
    }

    public function ajax_simulate_msg() {
        check_ajax_referer('lz_wa_test_webhook');
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');

        $text = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        if (empty($text)) wp_send_json_error('Messaggio vuoto.');

        self::lz_log("SIMULATION: Received message from dashboard: '{$text}'");

        // Prepariamo un payload simile a quello di Meta
        $mock_data = array(
            'entry' => array(
                array(
                    'changes' => array(
                        array(
                            'value' => array(
                                'contacts' => array(
                                    array('profile' => array('name' => 'SIMULATORE DASHBOARD'))
                                ),
                                'messages' => array(
                                    array(
                                        'from' => '390000000000',
                                        'type' => 'text',
                                        'text' => array('body' => $text)
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );

        // Creiamo una finta richiesta e simuliamo l'auto-reply
        self::lz_log("Webhook: Text='{$text}', ID='' from SIMULATORE DASHBOARD (390000000000)");
        
        // Simula la ricezione del messaggio e l'invio dell'auto-reply per testare il sistema
        self::lz_log("Webhook: Auto-reply triggered for 390000000000");
        $auto_reply = "Ciao! Questo numero è automatizzato e serve solo per l'invio delle conferme e dei promemoria degli appuntamenti. Puoi scrivermi al +39 388 378 7285";
        
        // Se vogliamo davvero inviare l'autorisposta al numero del simulatore tramite Meta:
        // self::send_whatsapp_text('390000000000', $auto_reply);
        
        // Per loggare l'uscita finta nel nostro sistema:
        $mock_out = array(
            'to' => '390000000000',
            'type' => 'text',
            'text' => array('body' => $auto_reply)
        );
        self::lz_log("WA_Sync Sending Body: " . wp_json_encode($mock_out));
        
        wp_send_json_success('Simulazione riuscita: Messaggio di test auto-risposta inviato e loggato.');
    }

    public function ajax_refresh_number() {
        check_ajax_referer('lz_wa_test_webhook');
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');

        $token = trim(get_option('lz_wa_access_token'));
        $phone_id = trim(get_option('lz_wa_phone_number_id'));

        if (empty($token) || empty($phone_id)) {
            wp_send_json_error('Configura prima Token e Phone ID.');
        }

        $url = "https://graph.facebook.com/v19.0/{$phone_id}";
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token)
        ));

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['display_phone_number'])) {
            $num = $body['display_phone_number'];
            update_option('lz_wa_active_number', $num);
            wp_send_json_success($num);
        } else {
            $err = $body['error']['message'] ?? 'Errore sconosciuto dalle API Meta.';
            wp_send_json_error($err);
        }
    }

    public function ajax_test_log() {
        check_ajax_referer('lz_wa_test_webhook');
        self::lz_log("MANUAL TEST LOG: Se leggi questo, il sistema di log funziona!");
        wp_send_json_success();
    }

    public function ajax_get_wa_messages() {
        if ( !current_user_can('edit_posts') ) wp_send_json_error('No permission');
        
        $logs = $this->get_formatted_message_logs();
        wp_send_json_success($logs);
    }

    private function get_formatted_message_logs() {
        $upload_dir = wp_upload_dir();
        $log_file = $upload_dir['basedir'] . '/wa-sync.log';
        if (!file_exists($log_file)) return array();

        $content = @file_get_contents($log_file);
        if (!$content) return array();

        $lines = explode("\n", $content);
        $formatted = array();

        $logs_by_user = array();

        // Leggiamo solo gli ultimi 150 log per performance
        $lines = array_slice($lines, -150);

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            // Pattern: [YYYY-MM-DD HH:MM:SS] Message
            if (preg_match('/^\[(.*?)\] (.*)$/', $line, $matches)) {
                $time = $matches[1];
                $msg = $matches[2];

                $type = 'system';
                $clean_msg = '';
                $phone = 'Sconosciuto';
                $user_name = '';

                if (strpos($msg, 'Webhook: Text=') !== false) {
                    $type = 'in';
                    // Estrazione: Webhook: Text='...', ID='...' from Name (Phone)
                    if (preg_match("/Text='(.*?)'.*from (.*?) \((.*?)\)/", $msg, $m)) {
                        $clean_msg = $m[1];
                        $user_name = $m[2];
                        $phone = $m[3];
                    }
                } elseif (strpos($msg, 'WA_Sync Sending Body:') !== false) {
                    $type = 'out';
                    $data = json_decode(str_replace('WA_Sync Sending Body: ', '', $msg), true);
                    if ($data) {
                        $to = $data['to'] ?? 'Sconosciuto';
                        $phone = $to;
                        if ($data['type'] === 'template') {
                            $clean_msg = "<i>Template: " . ($data['template']['name'] ?? '') . "</i>";
                        } else {
                            $clean_msg = $data['text']['body'] ?? '';
                        }
                    }
                } elseif (strpos($msg, 'SUPPORT:') !== false) {
                    $type = 'support';
                    // Expected format: SUPPORT: User Name (@user, Tel: 1234) ha richiesto assistenza: Messaggio
                    if (preg_match("/SUPPORT: User (.*?) \(.*Tel: (.*?)\) ha richiesto assistenza: (.*)/", $msg, $m)) {
                        $user_name = $m[1];
                        $phone = $m[2];
                        $clean_msg = "🆘 " . trim($m[3]);
                    } else {
                        $clean_msg = "🆘 " . str_replace('SUPPORT: ', '', $msg);
                        // Try to extract phone if possible, otherwise goes to 'Sistema'
                    }
                } elseif (strpos($msg, 'Created appointment post') !== false) {
                    $type = 'action';
                    $clean_msg = "📌 " . str_replace('Created appointment post ', 'Creato appuntamento ', $msg);
                }

                if ($clean_msg) {
                    $item = array(
                        'time' => date('H:i', strtotime($time)),
                        'date' => date('d/m', strtotime($time)),
                        'msg'  => $clean_msg,
                        'type' => $type,
                        'raw_time' => strtotime($time)
                    );

                    // Normalize phone (remove + if any for grouping)
                    $group_key = str_replace('+', '', $phone);
                    if ($group_key === 'Sconosciuto' && $type === 'action') {
                        $group_key = 'Sistema'; 
                    }

                    if (!isset($logs_by_user[$group_key])) {
                        $logs_by_user[$group_key] = array(
                            'phone' => $group_key,
                            'name' => $user_name ? $user_name : $group_key,
                            'messages' => array(),
                            'last_activity' => 0
                        );
                    }
                    
                    if ($user_name && $logs_by_user[$group_key]['name'] === $group_key) {
                        $logs_by_user[$group_key]['name'] = $user_name;
                    }

                    $logs_by_user[$group_key]['messages'][] = $item;
                    $logs_by_user[$group_key]['last_activity'] = max($logs_by_user[$group_key]['last_activity'], strtotime($time));
                }
            }
        }

        // Sort each conversation by time ascending (oldest to newest inside the chat)
        foreach ($logs_by_user as &$chat) {
            usort($chat['messages'], function($a, $b) {
                return $a['raw_time'] - $b['raw_time'];
            });
        }

        // Sort all conversations by last activity descending (newest chat first)
        usort($logs_by_user, function($a, $b) {
            return $b['last_activity'] - $a['last_activity'];
        });

        return $logs_by_user;
    }

    /**
     * Helper per validare e formattare il numero in standard internazionale (senza il +) 
     * richiesto dalle API di Meta (E.164 without plus)
     */
    public static function format_phone_number_for_wa($phone) {
        // Rimuove spazi, trattini, parentesi
        $clean = preg_replace('/[^\d]/', '', $phone);
        
        // Se il numero inizia per 3 ed è lungo 10, è probabile che sia un cellulare italiano senza prefisso prefisso
        if (strlen($clean) == 10 && substr($clean, 0, 1) === '3') {
            return '39' . $clean;
        }
        
        // Se comincia con 00 sostituiscilo senza il 00 (es 0039 -> 39)
        if (substr($clean, 0, 2) === '00') {
            return substr($clean, 2);
        }

        return $clean; // Fallback, si assume che sia già col prefisso o invalido (Meta darà errore 400)
    }

    /**
     * Helper Statico per inviare il messaggio tramite Template WA
     * 
     * @param string $phone Numero di telefono del destinatario
     * @param string $template_name Il nome del template approvato
     * @param array $components Array dei parametri da rimpiazzare nel template (es: servizio, data, ora)
     * @param string $language Il codice lingua del template, se null usa l'opzione salvata
     */
    public static function send_whatsapp_template( $phone, $template_name, $components_variables, $language = null, $button_parameters = array() ) {
        $token = trim(get_option('lz_wa_access_token'));
        $phone_id = trim(get_option('lz_wa_phone_number_id'));

        if ( empty($token) || empty($phone_id) || empty($phone) || empty($template_name) ) {
            self::lz_log("WA_Sync Error: Missing credentials, phone or template name.");
            return false;
        }

        if ($language === null) {
            $language = trim(get_option('lz_wa_template_lang', 'it'));
        }

        $template_name = trim($template_name);

        $formatted_phone = self::format_phone_number_for_wa($phone);

        $url = "https://graph.facebook.com/v19.0/{$phone_id}/messages";

        // Il template 'hello_world' di default è in inglese US
        if ( $template_name === 'hello_world' ) {
            $language = 'en_US';
        }

        // Costruiamo l'array dei parametri testuali da passare alle variabili del template {{1}}, {{2}}...
        $template_config = array(
            "name" => $template_name,
            "language" => array("code" => $language)
        );

        // Il template 'hello_world' di Meta non accetta variabili. Se inviate, restituisce errore 400.
        if ( $template_name !== 'hello_world' ) {
            $components = array();
            
            if ( !empty($components_variables) ) {
                $parameters = array();
                foreach($components_variables as $val) {
                    $parameters[] = array(
                        "type" => "text",
                        "text" => strval($val)
                    );
                }
                $components[] = array(
                    "type" => "body",
                    "parameters" => $parameters
                );
            }
            
            if ( !empty($button_parameters) ) {
                $idx = 0;
                foreach($button_parameters as $b_param) {
                    $components[] = array(
                        "type" => "button",
                        "sub_type" => "url",
                        "index" => strval($idx),
                        "parameters" => array(
                            array(
                                "type" => "text",
                                "text" => strval($b_param)
                            )
                        )
                    );
                    $idx++;
                }
            }
            
            if ( !empty($components) ) {
                $template_config["components"] = $components;
            }
        }

        $body = array(
            "messaging_product" => "whatsapp",
            "to" => $formatted_phone,
            "type" => "template",
            "template" => $template_config
        );

        $json_body = wp_json_encode( $body );
        self::lz_log("WA_Sync Sending Body: " . $json_body);

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'body' => $json_body
        ));

        if ( is_wp_error( $response ) ) {
            self::lz_log("WA_Sync Request Error: " . $response->get_error_message());
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code( $response );
        $res_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 || $code === 201 ) {
            self::lz_log("WA_Sync Success: Message sent to {$formatted_phone}. Response: " . json_encode($res_body));
            return array('success' => true, 'data' => $res_body);
        } else {
            $error_msg = $res_body['error']['message'] ?? 'Unknown Error';
            self::lz_log("WA_Sync API Error ({$code}) to {$formatted_phone}: " . $error_msg . " - Full Response: " . json_encode($res_body));
            return array('success' => false, 'error' => $error_msg, 'code' => $code);
        }
    }

    /**
     * Helper Statico per inviare un semplice messaggio di testo (solo se c'è una finestra di 24h aperta)
     */
    public static function send_whatsapp_text( $phone, $message ) {
        $token = trim(get_option('lz_wa_access_token'));
        $phone_id = trim(get_option('lz_wa_phone_number_id'));

        if ( empty($token) || empty($phone_id) || empty($phone) || empty($message) ) {
            return false;
        }

        $formatted_phone = self::format_phone_number_for_wa($phone);
        $url = "https://graph.facebook.com/v19.0/{$phone_id}/messages";

        $body = array(
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"                => $formatted_phone,
            "type"              => "text",
            "text"              => array(
                "body" => $message
            )
        );

        $json_body = wp_json_encode( $body );
        self::lz_log("WA_Sync Sending Text Body: " . $json_body);

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'body' => $json_body
        ));

        if ( is_wp_error( $response ) ) {
            self::lz_log("WA_Sync Text Request Error: " . $response->get_error_message());
            return array('success' => false, 'error' => $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code( $response );
        $res_body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code === 200 || $code === 201 ) {
            self::lz_log("WA_Sync Text Success to {$formatted_phone}: Message sent.");
            return array('success' => true, 'data' => $res_body);
        } else {
            $error_msg = $res_body['error']['message'] ?? 'Unknown Error';
            self::lz_log("WA_Sync Text API Error ({$code}) to {$formatted_phone}: " . $error_msg);
            return array('success' => false, 'error' => $error_msg, 'code' => $code);
        }
    }

    /**
     * Invia un messaggio interattivo (Buttons o List)
     * 
     * @param string $phone
     * @param array $interactive_config Configurazione dell'oggetto "interactive" di Meta
     */
    public static function send_whatsapp_interactive( $phone, $interactive_config ) {
        $token = get_option('lz_wa_access_token');
        $phone_id = get_option('lz_wa_phone_number_id');

        if ( empty($token) || empty($phone_id) || empty($phone) ) return false;

        $formatted_phone = self::format_phone_number_for_wa($phone);
        $url = "https://graph.facebook.com/v19.0/{$phone_id}/messages";

        $body = array(
            "messaging_product" => "whatsapp",
            "recipient_type"    => "individual",
            "to"                => $formatted_phone,
            "type"              => "interactive",
            "interactive"       => $interactive_config
        );

        $response = wp_remote_post( $url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'body' => wp_json_encode( $body )
        ));

        if ( is_wp_error( $response ) ) {
            self::lz_log("WA_Sync Interactive Error: " . $response->get_error_message());
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $res_body = wp_remote_retrieve_body( $response );
        self::lz_log("WA_Sync Interactive Response ({$code}): " . $res_body);

        return ( $code === 200 || $code === 201 );
    }

    /**
     * Intercetta il salvataggio o l'update manuale in dashboard di uno status
     */
    public function sync_appointment_to_wa($post_id, $new_status, $old_status) {
        $client_id = get_post_meta($post_id, '_lz_client_id', true);
        if(!$client_id) return;
        
        $client_info = get_userdata($client_id);
        if(!$client_info) return;

        // Recuperiamo il telefono come fallback se non è nello usermeta
        $phone = get_user_meta($client_id, 'billing_phone', true) ?: get_user_meta($client_id, 'phone', true);
        
        // Cerca di recuperarlo dal metadata dell'appuntamento o dal db usermeta di WP (spesso wp_usermeta -> billing_phone)
        if(empty($phone)) {
             $phone = get_post_meta($post_id, '_lz_client_phone', true); // Se fosse salvato sul post
             if(empty($phone)) $phone = get_user_meta($client_id, '_lz_phone', true);
        }

        if(empty($phone)) {
            self::lz_log("WA_Sync: No phone number available for client ID {$client_id}");
            return;
        }

        self::lz_log("WA_Sync: Triggered for post {$post_id}. Old: {$old_status}, New: {$new_status}, Phone: {$phone}");

        // Verifica se il cliente ha optato per ricevere le notifiche WA (se implementato, altrimenti di default sì)
        $wa_optin = get_user_meta($client_id, '_lz_wa_optin', true);
        if ($wa_optin === 'no') {
            return; // Cliente disiscritto
        }

        $full_name = trim($client_info->first_name . ' ' . $client_info->last_name);
        $client_name = !empty($full_name) ? $full_name : $client_info->display_name;

        $salon_name = "Lidia Zucaro Parrucchieri";
        $service = get_post_meta($post_id, '_lz_service', true) ?: "Servizio";
        $date = date('d/m/Y', strtotime(get_post_meta($post_id, '_lz_date', true)));
        $time = get_post_meta($post_id, '_lz_time', true) ?: "orario";

        $template_approved = get_option('lz_wa_template_approved');
        $template_rejected = get_option('lz_wa_template_rejected');

        $components = array($client_name, $salon_name, $service, $date, $time);

        if($new_status == 'approved' && $old_status != 'approved') {
            if(!empty($template_approved)) {
                self::lz_log("WA_Sync: Sending approved template '{$template_approved}' to {$phone}");
                $res = self::send_whatsapp_template($phone, $template_approved, $components);
                self::lz_log("WA_Sync: Result: " . print_r($res, true));
                return $res;
            } else {
                self::lz_log("WA_Sync: Approved template name is empty in settings.");
                return array('success' => false, 'error' => 'Template setting is empty');
            }
        } 
        elseif($new_status == 'rejected' && $old_status != 'rejected') {
            if(!empty($template_rejected)) {
                self::lz_log("WA_Sync: Sending rejected template '{$template_rejected}' to {$phone}");
                $res = self::send_whatsapp_template($phone, $template_rejected, $components);
                self::lz_log("WA_Sync: Result: " . print_r($res, true));
                return $res;
            } else {
                self::lz_log("WA_Sync: Rejected template name is empty in settings.");
                return array('success' => false, 'error' => 'Template setting is empty');
            }
        }
        
        return array('success' => false, 'error' => 'No action triggered');
    }

    /**
     * AJAX: Esegue un test di handshake locale per verificare se il webhook è raggiungibile
     */
    public function ajax_test_webhook() {
        check_ajax_referer('lz_wa_test_webhook');
        if (!current_user_can('manage_options')) wp_send_json_error('No permission');

        $verify_token = get_option('lz_wa_verify_token', 'lidia_zucaro_bot_2026');
        $webhook_url = get_rest_url( null, 'lz-wa/v1/webhook' );
        $test_url = add_query_arg(array(
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $verify_token,
            'hub_challenge' => '12345'
        ), $webhook_url);

        $response = wp_remote_get($test_url);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if ($code === 200 && trim($body) === '12345') {
            wp_send_json_success("L'endpoint risponde correttamente.");
        } else {
            wp_send_json_error("Risposta inattesa (Codice {$code}): " . substr(strip_tags($body), 0, 100));
        }
    }

    public function handle_webhook( $request ) {
        // --- LOG IMMEDIATO PER OGNI HIT ---
        $method = $request->get_method();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        self::lz_log("Webhook HIT: Method={$method}, IP={$ip}");

        // --- 1. VERIFICA HANDSHAKE (GET) ---
        if ( $request->get_method() === 'GET' ) {
            $mode      = $request->get_param('hub_mode');
            $token     = $request->get_param('hub_verify_token');
            $challenge = $request->get_param('hub_challenge');

            $stored_token = get_option('lz_wa_verify_token', 'lidia_zucaro_bot_2026');

            if ( $mode === 'subscribe' && $token === $stored_token ) {
                return new WP_REST_Response( intval($challenge), 200 );
            }
            return new WP_REST_Response( 'Forbidden', 403 );
        }

        // --- 2. RICEZIONE MESSAGGI (POST) ---
        $data = $request->get_json_params();

        // Logghiamo sempre per debug
        self::lz_log("Webhook Payload: " . json_encode($data));

        // Verifica se è un messaggio in entrata (ignora notifiche di consegna/lettura senza messaggi)
        if ( !isset($data['entry'][0]['changes'][0]['value']['messages'][0]) ) {
            self::lz_log("Webhook: No message content (likely status update).");
            return new WP_REST_Response( array('success' => true), 200 );
        }

        $message_data = $data['entry'][0]['changes'][0]['value']['messages'][0];
        $sender_id    = $message_data['from'] ?? '';
        $type         = $message_data['type'] ?? '';

        self::lz_log("Webhook: Message from {$sender_id} of type {$type}");
        
        $text      = '';
        $button_id = '';
        if ( $type === 'text' ) {
            $text = $message_data['text']['body'] ?? '';
        } elseif ( $type === 'button' ) {
            $text      = $message_data['button']['text'] ?? '';
            $button_id = $message_data['button']['payload'] ?? '';
        } elseif ( $type === 'interactive' ) {
            $text = $message_data['interactive']['button_reply']['title'] ?? 
                    $message_data['interactive']['list_reply']['title'] ?? '';
            $button_id = $message_data['interactive']['button_reply']['id'] ?? 
                         $message_data['interactive']['list_reply']['id'] ?? '';
        }

        $contacts = $data['entry'][0]['changes'][0]['value']['contacts'][0] ?? array();
        $wa_name  = $contacts['profile']['name'] ?? '';

        self::lz_log("Webhook: Text='{$text}', ID='{$button_id}' from {$wa_name} ({$sender_id})");

        if ( !empty($sender_id) && (!empty($text) || !empty($button_id)) ) {
            // INIZIO MODIFICA: Risposta Automatica di non presidio (Numero Send-Only)
            self::lz_log("Webhook: Auto-reply triggered for {$sender_id}");
            $auto_reply = "Ciao! Questo numero è automatizzato e serve solo per l'invio delle conferme e dei promemoria degli appuntamenti. Puoi scrivermi al +39 388 378 7285";
            self::send_whatsapp_text($sender_id, $auto_reply);
            
            // Ritorniamo 200 OK a Meta senza elaborare ulteriori appuntamenti finti
            return new WP_REST_Response( array('success' => true), 200 );
            // FINE MODIFICA
        } else {
            self::lz_log("Webhook: Failed to extract sender or content.");
        }

        return new WP_REST_Response( array('success' => true), 200 );
    }

    /**
     * Parser per estrarre dati da una richiesta di appuntamento testuale
     */
    private function parse_appointment_request($text, $sender_id, $wa_name) {
        $text_lower = mb_strtolower($text, 'UTF-8');
        self::lz_log("Parsing request from {$sender_id}: '{$text}'");

        // 1. Identificazione Cliente (Cerca per telefono)
        $client_name = $wa_name ?: $sender_id;
        $users = get_users(array(
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => 'billing_phone', 'value' => $sender_id, 'compare' => 'LIKE'),
                array('key' => 'phone', 'value' => $sender_id, 'compare' => 'LIKE'),
                array('key' => '_lz_phone', 'value' => $sender_id, 'compare' => 'LIKE'),
            ),
            'number' => 1
        ));

        $user_id = 0;
        if (!empty($users)) {
            $user = $users[0];
            $user_id = $user->ID;
            $full_name = trim($user->first_name . ' ' . $user->last_name);
            $client_name = !empty($full_name) ? $full_name : $user->display_name;
        }

        // 2. Estrazione Servizio
        $service = "da definire";
        $services_keywords = array(
            'piega' => 'Piega',
            'taglio' => 'Taglio',
            'colore' => 'Colore',
            'trattamento' => 'Trattamento',
            'sfumatura' => 'Sfumatura',
            'base' => 'Colore Base'
        );
        foreach ($services_keywords as $key => $val) {
            if (strpos($text_lower, $key) !== false) {
                $service = $val;
                break;
            }
        }

        // 3. Estrazione Data
        $date = "da definire";
        $days_map = array(
            'lunedì' => 'monday', 'lunedi' => 'monday',
            'martedì' => 'tuesday', 'martedi' => 'tuesday',
            'mercoledì' => 'wednesday', 'mercoledi' => 'wednesday',
            'giovedì' => 'thursday', 'giovedi' => 'thursday',
            'venerdì' => 'friday', 'venerdi' => 'friday',
            'sabato' => 'saturday',
            'domenica' => 'sunday',
            'oggi' => 'today',
            'domani' => 'tomorrow'
        );

        foreach ($days_map as $ita => $eng) {
            if (strpos($text_lower, $ita) !== false) {
                $date_eng = ($ita === 'oggi' || $ita === 'domani') ? $eng : "next $eng";
                $date = date('d/m/Y', strtotime($date_eng));
                break;
            }
        }

        // Regex per data esplicita (es: 10/03 o 10 marzo)
        if ($date === "da definire") {
             if (preg_match('/(\d{1,2})[\/\- ](\d{1,2}|gennaio|febbraio|marzo|aprile|maggio|giugno|luglio|agosto|settembre|ottobre|novembre|dicembre)/iu', $text_lower, $matches)) {
                 $day = $matches[1];
                 $month_raw = $matches[2];
                 $months_map = array('gennaio'=>'01','febbraio'=>'02','marzo'=>'03','aprile'=>'04','maggio'=>'05','giugno'=>'06','luglio'=>'07','agosto'=>'08','settembre'=>'09','ottobre'=>'10','novembre'=>'11','dicembre'=>'12');
                 $month = $months_map[$month_raw] ?? (is_numeric($month_raw) ? $month_raw : '??');
                 $date = sprintf("%02d/%02s/2026", $day, $month);
             }
        }

        // 4. Estrazione Ora
        $time = "da comunicare";
        // Cerca pattern come "alle 15", "15:30", "ore 15"
        if (preg_match('/(?:alle\s+)?(\d{1,2}[:\.]\d{2})|(\d{1,2})\s*(?:ore|h)/i', $text, $matches)) {
            $time = !empty($matches[1]) ? str_replace('.', ':', $matches[1]) : $matches[2] . ":00";
        }

        return array(
            'client' => $client_name,
            'user_id' => $user_id,
            'service' => $service,
            'date' => $date,
            'time' => $time,
            'sender_id' => $sender_id
        );
    }

    private function notify_admin_appointment_request($data) {
        $admin_phone = get_option('lz_wa_admin_phone');
        if (empty($admin_phone)) {
            self::lz_log("Admin notification failed: Admin phone not set.");
            return;
        }

        $message = "TITOLO: Richiesta appuntamento\n";
        $message .= "NOME: " . $data['client'] . "\n";
        $message .= "SERVIZIO: " . $data['service'] . "\n";
        $message .= "DATA: " . $data['date'] . "\n";
        $message .= "ORA: " . $data['time'];

        self::lz_log("Sending notification to admin {$admin_phone}...");
        $res = self::send_whatsapp_text($admin_phone, $message);
        
        if ($res) {
            self::lz_log("Admin notified successfully.");
        } else {
            self::lz_log("Admin notification failed (check Meta API 24h window).");
        }
    }

    /**
     * Crea l'appuntamento nel database (Custom Post Type lz_appointment)
     */
    private function create_appointment_from_parsed_data($data) {
        $client_name = $data['client'];
        $sender_id = $data['sender_id'];
        $user_id = $data['user_id'];

        $post_title = $client_name;
        if (!$user_id) {
            $post_title .= " (WhatsApp)";
        }

        $post_id = wp_insert_post(array(
            'post_title' => $post_title,
            'post_type'  => 'lz_appointment',
            'post_status' => 'publish'
        ));

        if ($post_id) {
            // Conversione data da DD/MM/YYYY a Y-m-d per il database
            $date_db = "";
            if ($data['date'] !== "da definire") {
                $d = DateTime::createFromFormat('d/m/Y', $data['date']);
                if ($d) $date_db = $d->format('Y-m-d');
            }

            update_post_meta($post_id, '_lz_service', $data['service']);
            update_post_meta($post_id, '_lz_date', $date_db);
            update_post_meta($post_id, '_lz_time', $data['time']);
            update_post_meta($post_id, '_lz_phone', $sender_id);
            update_post_meta($post_id, '_lz_client_id', $user_id);
            update_post_meta($post_id, '_lz_status', 'pending');

            self::lz_log("Created appointment post #{$post_id} for {$client_name}");
            return $post_id;
        }

        self::lz_log("Error: Failed to create appointment post.");
        return false;
    }

    /**
     * Notifica l'Admin quando un cliente prenota dalla WebApp
     */
    public function notify_admin_on_new_booking($post_id) {
        $service = get_post_meta($post_id, '_lz_service', true);
        $date = get_post_meta($post_id, '_lz_date', true);
        $time = get_post_meta($post_id, '_lz_time', true);
        $client_id = get_post_meta($post_id, '_lz_client_id', true);
        $client = get_userdata($client_id);
        
        $client_name = $client ? $client->display_name : get_the_title($post_id);
        $date_fmt = $date ? date('d/m/Y', strtotime($date)) : 'da definire';

        $time_display = ($time && $time !== 'Da definire') ? $time : 'Nessuna preferenza';
        $message = "🔔 *Nuova Richiesta Appuntamento*\n\n";
        $message .= "*CLIENTE:* " . $client_name . "\n";
        $message .= "*SERVIZIO:* " . $service . "\n";
        $message .= "*DATA:* " . $date_fmt . "\n";
        $message .= "*ORA:* " . $time_display . "\n\n";
        $message .= "👉 *Gestisci qui:* " . home_url('/gestione/agenda/?new_booking=' . $post_id);

        $admin_phone = get_option('lz_wa_admin_phone');
        if ($admin_phone) {
            self::lz_log("Notifying admin of new webapp booking #{$post_id}");
            self::send_whatsapp_text($admin_phone, $message);
        }
    }

    /**
     * Logica Conversazionale del Bot (State Machine) - DISATTIVATA
     */
    private function process_bot_interaction($sender_id, $text, $raw_data, $wa_name = '', $button_id = '') {
        // Metodo disattivato in favore del nuovo parser
    }

    /**
     * Gestisce le azioni cliccate dall'Admin su WhatsApp
     */
    private function handle_admin_action($admin_phone, $button_id) {
        // Formato button_id: adm_action_POSTID
        $parts = explode('_', $button_id);
        if (count($parts) < 3) return;

        $action = $parts[1];
        $post_id = intval($parts[2]);
        $client_phone = get_post_meta($post_id, '_lz_phone', true);

        self::lz_log("Admin Action: {$action} for Appt #{$post_id}");

        if ($action === 'confirm') {
            update_post_meta($post_id, '_lz_status', 'approved');
            
            // Messaggio di conferma all'Admin
            $this->send_wa_direct($admin_phone, array(
                "messaging_product" => "whatsapp", "to" => $admin_phone, "type" => "text",
                "text" => array("body" => "✅ Appuntamento #{$post_id} approvato. Notifica inviata al cliente.")
            ));

            // Notifica al Cliente (Tramite Template lz_wa_template_approved)
            $template = get_option('lz_wa_template_approved', 'appuntamento_confermato');
            $service = get_post_meta($post_id, '_lz_service', true);
            $date = date('d/m/Y', strtotime(get_post_meta($post_id, '_lz_date', true)));
            $time = get_post_meta($post_id, '_lz_time', true);

            // Recuperiamo il nome del cliente per il template
            $user_id = get_post_meta($post_id, '_lz_client_id', true);
            $customer_name = "Cliente";
            if ($user_id) {
                $user_info = get_userdata($user_id);
                $full_name = trim($user_info->first_name . ' ' . $user_info->last_name);
                $customer_name = !empty($full_name) ? $full_name : $user_info->display_name;
            } else {
                // Fallback se non è un utente registrato, usiamo il profilo WA salvato nel titolo (rimuovendo " (WhatsApp)")
                $customer_name = str_replace(' (WhatsApp)', '', get_the_title($post_id));
            }

            // Invio con le 5 variabili: {{1}} Nome, {{2}} Salone, {{3}} Servizio, {{4}} Data, {{5}} Ora
            self::send_whatsapp_template($client_phone, $template, array(
                $customer_name,
                "Lidia Zucaro Parrucchieri",
                $service,
                $date,
                $time
            ));

        } elseif ($action === 'reject') {
            update_post_meta($post_id, '_lz_status', 'cancelled');
            
            $this->send_wa_direct($admin_phone, array(
                "messaging_product" => "whatsapp", "to" => $admin_phone, "type" => "text",
                "text" => array("body" => "❌ Appuntamento #{$post_id} rifiutato.")
            ));

            // Notifica al Cliente (Tramite Template lz_wa_template_rejected)
            $template = get_option('lz_wa_template_rejected', 'appuntamento_annullato');
            $service = get_post_meta($post_id, '_lz_service', true);
            $date = date('d/m/Y', strtotime(get_post_meta($post_id, '_lz_date', true)));
            $time = get_post_meta($post_id, '_lz_time', true);

            // Recuperiamo il nome del cliente
            $user_id = get_post_meta($post_id, '_lz_client_id', true);
            $customer_name = "Cliente";
            if ($user_id) {
                $user_info = get_userdata($user_id);
                $full_name = trim($user_info->first_name . ' ' . $user_info->last_name);
                $customer_name = !empty($full_name) ? $full_name : $user_info->display_name;
            } else {
                $customer_name = str_replace(' (WhatsApp)', '', get_the_title($post_id));
            }
            
            self::send_whatsapp_template($client_phone, $template, array($customer_name, "Lidia Zucaro Parrucchieri", $service, $date, $time));

        } elseif ($action === 'modify') {
            $link = get_edit_post_link($post_id, 'url');
            $this->send_wa_direct($admin_phone, array(
                "messaging_product" => "whatsapp", "to" => $admin_phone, "type" => "text",
                "text" => array("body" => "🔗 Clicca qui per modificare l'appuntamento sul sito:\n" . $link)
            ));
        }
    }

    private function send_wa_direct($to, $body_array) {
        $token = get_option('lz_wa_access_token');
        $phone_id = get_option('lz_wa_phone_number_id');
        $url = "https://graph.facebook.com/v19.0/{$phone_id}/messages";
        wp_remote_post($url, array(
            'headers' => array('Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($body_array)
        ));
    }

    // Helper per lo stato del Bot (Transients)
    private function set_bot_state($sender_id, $data) {
        set_transient('lz_wa_state_' . $sender_id, $data, 2 * HOUR_IN_SECONDS);
    }

    private function get_bot_state($sender_id) {
        return get_transient('lz_wa_state_' . $sender_id);
    }

    /**
     * Funzione Cron: Invia i promemoria per gli appuntamenti di DOMANI
     */
    public function send_daily_reminders() {
        self::lz_log("Cron: Starting daily reminders scan...");

        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $template = get_option('lz_wa_template_reminder', 'promemoria_domani');
        
        if (empty($template)) {
            self::lz_log("Cron: Reminder template name is empty. Skipping.");
            return;
        }

        $args = array(
            'post_type'  => 'lz_appointment',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key'     => '_lz_date',
                    'value'   => $tomorrow,
                    'compare' => '='
                ),
                array(
                    'key'     => '_lz_status',
                    'value'   => 'approved',
                    'compare' => '='
                )
            )
        );

        $appointments = get_posts($args);
        self::lz_log("Cron: Found " . count($appointments) . " appointments for {$tomorrow}");

        foreach ($appointments as $appt) {
            $client_phone = get_post_meta($appt->ID, '_lz_phone', true);
            $user_id = get_post_meta($appt->ID, '_lz_client_id', true);
            $service = get_post_meta($appt->ID, '_lz_service', true);
            $time = get_post_meta($appt->ID, '_lz_time', true);

            if (empty($client_phone)) continue;

            $customer_name = "Cliente";
            if ($user_id) {
                $user_info = get_userdata($user_id);
                $full_name = trim($user_info->first_name . ' ' . $user_info->last_name);
                $customer_name = !empty($full_name) ? $full_name : $user_info->display_name;
            } else {
                $customer_name = str_replace(' (WhatsApp)', '', get_the_title($appt->ID));
            }

            self::lz_log("Cron: Sending reminder to {$customer_name} ({$client_phone})");
            self::send_whatsapp_template($client_phone, $template, array(
                $customer_name,
                "Lidia Zucaro Parrucchieri",
                $service,
                date('d/m/Y', strtotime($tomorrow)),
                $time
            ));
        }

        self::lz_log("Cron: Daily reminders scan completed.");
    }

    private function clear_bot_state($sender_id) {
        delete_transient('lz_wa_state_' . $sender_id);
    }
}

new LZ_WA_Sync();
