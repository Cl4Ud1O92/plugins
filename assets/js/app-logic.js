/**
 * Logica JavaScript Centrale per la Web App Lidia Zucaro
 */

// Stato Globale
var lzGlobalSubmitting = false;
var currentWeekOffset = 0;

jQuery(document).ready(function ($) {
    // Inizializzazione
    if ($('#lz-app-container').length) {
        initRouting();
        if ($('#view-clienti').length) searchClients('');
        if ($('#view-agenda').length) loadWeeklyAgenda();
        if ($('#view-messaggi').length) {
            refreshWAMessages();
            setInterval(refreshWAMessages, 10000);
        }
    }

    // Refresh automatico dashboard cliente se presente
    if ($('#lz-client-history-list').length) {
        refreshDashboardData();
        setInterval(refreshDashboardData, 10000);
    }

    // Inizializza container Toast se non esiste
    if (!$('#lz-toast-container').length) {
        $('body').append('<div id="lz-toast-container"></div>');
    }
    // Autocomplete Manual Booking
    $('#manual-customer-name').on('keyup', function () {
        var query = $(this).val();
        if (query.length > 1) {
            autocompleteSearch(query);
        } else {
            $('#manual-suggestions').hide();
        }
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.lz-autocomplete-wrapper').length) {
            $('#manual-suggestions').hide();
        }
    });

    // Form: Aggiunta Appuntamento Manuale
    $(document).on('submit', '#form-add-manual-booking', function (e) {
        e.preventDefault();
        var form = $(this);
        var btn = form.find('button[type="submit"]');

        if (lzGlobalSubmitting) return false;
        setBtnLoading(btn, true);
        lzGlobalSubmitting = true;

        jQuery.post(lzData.ajaxurl,
            form.serialize() + '&action=lz_submit_booking_manual&security=' + lzData.nonce,
            function (res) {
                setBtnLoading(btn, false);
                lzGlobalSubmitting = false;
                if (res.success) {
                    showToast('✅ Appuntamento aggiunto in Agenda!', 'success');
                    closeModal('modal-add-booking');
                    form[0].reset();
                    // Invece di reload() intero, potremmo ricaricare solo l'agenda se visibile
                    if ($('#view-agenda').hasClass('active')) loadWeeklyAgenda();
                } else {
                    showToast('❌ Errore durante l\'inserimento.', 'error');
                }
            }
        ).fail(function () {
            setBtnLoading(btn, false);
            lzGlobalSubmitting = false;
            showToast('❌ Errore di Rete.', 'error');
        });
        return false;
    });

    // Ensure old form submit is unbound or not conflicting
    jQuery(document).on('submit', '#form-edit-booking', function (e) { e.preventDefault(); });

    // Form: Aggiunta/Modifica Cliente (Admin)
    $(document).on('submit', '#form-add-client', function (e) {
        e.preventDefault();
        submitClientForm($(this));
        return false;
    });
});

/**
 * UI Feedback: Toasts & Loading
 */
// Initial states for edit booking to track changes
var lzInitialBookingData = {};

function initBookingEditTracking() {
    lzInitialBookingData = {
        service: jQuery('#edit-app-service').val(),
        date: jQuery('#edit-app-date').val(),
        time: jQuery('#edit-app-time').val()
    };

    // Reset button
    var btn = jQuery('#btn-submit-edit-booking');
    btn.text('Chiudi').data('has-changes', false);

    // Attach listeners
    jQuery('#edit-app-service, #edit-app-date, #edit-app-time').off('change input').on('change input', function () {
        var currentService = jQuery('#edit-app-service').val();
        var currentDate = jQuery('#edit-app-date').val();
        var currentTime = jQuery('#edit-app-time').val();

        var hasChanges = (currentService !== lzInitialBookingData.service ||
            currentDate !== lzInitialBookingData.date ||
            currentTime !== lzInitialBookingData.time);

        var btn = jQuery('#btn-submit-edit-booking');
        btn.data('has-changes', hasChanges);

        if (hasChanges) {
            btn.text('Comunica modifiche');
        } else {
            btn.text('Chiudi');
        }
    });
}

function handleEditBookingSubmit() {
    var form = jQuery('#form-edit-booking');
    var btn = jQuery('#btn-submit-edit-booking');

    if (!btn.data('has-changes')) {
        // No changes, just close
        closeModal('modal-booking-detail');
        return;
    }

    // Has changes, submit via AJAX
    if (lzGlobalSubmitting) return;
    setBtnLoading(btn, true);
    lzGlobalSubmitting = true;

    jQuery.post(lzData.ajaxurl,
        form.serialize() + '&action=lz_edit_booking&security=' + lzData.nonce,
        function (res) {
            setBtnLoading(btn, false);
            lzGlobalSubmitting = false;
            if (res.success) {
                showToast('✅ Modifiche salvate e notifica inviata!', 'success');
                closeModal('modal-booking-detail');
                loadWeeklyAgenda();
            } else {
                showToast('❌ Errore durante il salvataggio.', 'error');
            }
        }
    ).fail(function () {
        setBtnLoading(btn, false);
        lzGlobalSubmitting = false;
        showToast('❌ Errore di Rete.', 'error');
    });
}

function showToast(message, type = 'info') {
    var container = jQuery('#lz-toast-container');
    var id = 'toast-' + Date.now();
    var html = '<div id="' + id + '" class="lz-toast ' + type + '">' + message + '</div>';
    container.append(html);

    setTimeout(function () {
        jQuery('#' + id).fadeOut(400, function () { jQuery(this).remove(); });
    }, 3000);
}

function setBtnLoading(btn, isLoading) {
    var jQuerybtn = jQuery(btn);
    if (isLoading) {
        jQuerybtn.addClass('lz-btn-loading').data('old-text', jQuerybtn.text()).prop('disabled', true);
    } else {
        jQuerybtn.removeClass('lz-btn-loading').text(jQuerybtn.data('old-text')).prop('disabled', false);
    }
}

/**
 * Add to Calendar Functionality
 */
function addToCalendar(service, dateStr, timeStr) {
    // dateStr: "dd/mm/yyyy", timeStr: "hh:mm"
    var parts = dateStr.split('/');
    var timeParts = timeStr.split(':');
    if (timeParts.length < 2) timeParts = ["09", "00"];

    var startDate = new Date(parts[2], parts[1] - 1, parts[0], timeParts[0], timeParts[1]);
    var endDate = new Date(startDate.getTime() + 60 * 60 * 1000);

    var isoStart = startDate.toISOString().replace(/-|:|\.\d\d\d/g, "");
    var isoEnd = endDate.toISOString().replace(/-|:|\.\d\d\d/g, "");

    var title = encodeURIComponent("Appuntamento Lidia Zucaro: " + service);
    var details = encodeURIComponent("Trattamento: " + service + "\nLuogo: Lidia Zucaro Parrucchieri");
    var location = encodeURIComponent("Lidia Zucaro Parrucchieri");

    // Rileva se è un dispositivo Apple (iPhone/iPad/Mac)
    var isApple = /iPhone|iPad|iPod|Macintosh/i.test(navigator.userAgent);

    if (isApple) {
        // Per Apple, generiamo il file .ics e lo "apriamo" direttamente
        var icsContent = "BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nDTSTART:" + isoStart + "\nDTEND:" + isoEnd + "\nSUMMARY:" + "Lidia Zucaro: " + service + "\nDESCRIPTION:" + service + "\nLOCATION:Lidia Zucaro Parrucchieri\nEND:VEVENT\nEND:VCALENDAR";
        var blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
        var url = window.URL.createObjectURL(blob);

        // Su iOS, questo di solito apre direttamente l'app Calendario con la scheda dell'evento
        window.location.assign(url);
    } else {
        // Per Android/Altri, Google Calendar è la scelta più comune e diretta
        var googleUrl = "https://www.google.com/calendar/render?action=TEMPLATE&text=" + title + "&dates=" + isoStart + "/" + isoEnd + "&details=" + details + "&location=" + location;
        window.open(googleUrl, '_blank');
    }
}

/**
 * Gestione Navigazione e Tab
 */
function switchView(viewName, btn, skipPush = false) {
    jQuery('.lz-view').removeClass('active');
    jQuery('#view-' + viewName).addClass('active');
    jQuery('.nav-item').removeClass('active');

    if (!btn) {
        btn = jQuery('.nav-item').filter(function () {
            return jQuery(this).attr('onclick').includes(viewName);
        });
    }
    jQuery(btn).addClass('active');

    if (!skipPush && window.history.pushState) {
        var pathPart = (viewName === 'clienti') ? 'gestioneclienti' : viewName;
        var newUrl = '/gestione/' + pathPart + '/';
        history.pushState({ view: viewName }, "", newUrl);
    }

    if (viewName === 'messaggi') refreshWAMessages();
}

function switchTab(tabName, btn) {
    jQuery('.lz-tab-view').removeClass('active');
    jQuery('#tab-' + tabName).addClass('active');
    jQuery('.nav-item').removeClass('active');

    if (btn) {
        jQuery(btn).addClass('active');
    } else {
        jQuery('.nav-item').each(function () {
            if (jQuery(this).attr('onclick').includes(tabName)) jQuery(this).addClass('active');
        });
    }

    jQuery('.lz-app-content').scrollTop(0);
    if (tabName === 'home' || tabName === 'profilo' || tabName === 'fidelity') refreshDashboardData();
}

function initRouting() {
    var forced = lzData.forcedView || '';
    if (forced) {
        switchView(forced, null, true);
        return;
    }

    var currentPath = window.location.pathname;
    if (currentPath.includes('/gestione/gestioneclienti')) switchView('clienti', null, true);
    else if (currentPath.includes('/gestione/agenda')) switchView('agenda', null, true);
    else if (currentPath.includes('/gestione/fidelity')) switchView('fidelity', null, true);
    else if (currentPath.includes('/gestione/messaggi')) switchView('messaggi', null, true);
}

window.onpopstate = function (event) {
    if (event.state && event.state.view) {
        switchView(event.state.view, null, true);
    }
};

/**
 * Logica Clienti (Admin)
 */
function searchClients(query) {
    var container = jQuery('#lz-clients-list-container');
    jQuery.post(lzData.ajaxurl, {
        action: 'lz_search_clients',
        query: query,
        security: lzData.nonce
    }, function (res) {
        if (res.success && res.data.length > 0) {
            var html = '';
            res.data.forEach(function (c) {
                html += '<div class="lz-card client" onclick="openClientDetail(' + c.ID + ')">';
                html += '<div style="display:flex;justify-content:space-between;align-items:center;">';
                html += '<div><strong>' + c.display_name + '</strong>';
                if (c.wa_optin === 'yes') { html += ' <span style="color:#25d366;" title="WhatsApp Attivo">💬</span>'; }
                html += '<br><small>' + c.phone + '</small></div>';
                html += '<div style="font-size:20px;">ℹ️</div>';
                html += '</div></div>';
            });
            container.html(html);
        } else {
            container.html('<p style="text-align:center; padding: 20px;">Nessun risultato.</p>');
        }
    });
}

/**
 * Autocomplete Logic
 */
function autocompleteSearch(query) {
    var list = jQuery('#manual-suggestions');
    jQuery.post(lzData.ajaxurl, {
        action: 'lz_search_clients',
        query: query,
        security: lzData.nonce
    }, function (res) {
        if (res.success && res.data.length > 0) {
            var html = '';
            res.data.forEach(function (c) {
                html += '<div class="suggestion-item" onclick="selectClientForManual(\'' + c.display_name.replace(/'/g, "\\'") + '\', \'' + c.phone + '\')">';
                html += '<div class="info"><div class="name">' + c.display_name + '</div><div class="phone">' + c.phone + '</div></div>';
                html += '</div>';
            });
            list.html(html).show();
        } else {
            list.hide();
        }
    });
}

function selectClientForManual(name, phone) {
    jQuery('#manual-customer-name').val(name);
    jQuery('#manual-customer-phone').val(phone !== 'N/D' ? phone : '');
    jQuery('#manual-suggestions').hide();
}

/**
 * Booking Detail Logic (Agenda)
 */
function openBookingDetail(bookingId) {
    openModal('modal-booking-detail');
    jQuery('#view-app-client').text('Caricamento...');

    jQuery.post(lzData.ajaxurl, {
        action: 'lz_get_booking_details',
        booking_id: bookingId,
        security: lzData.nonce
    }, function (res) {
        if (res.success) {
            var b = res.data;
            jQuery('#edit-booking-id').val(b.id);
            jQuery('#view-app-client').text(b.client_name);
            jQuery('#edit-app-service').val(b.service);
            jQuery('#edit-app-date').val(b.date);
            jQuery('#edit-app-time').val(b.time);
            initBookingEditTracking();
        } else {
            showToast('❌ Errore caricamento appuntamento', 'error');
            closeModal('modal-booking-detail');
        }
    });
}

function deleteBookingFromDetail() {
    var id = jQuery('#edit-booking-id').val();
    if (confirm('Sei sicuro di voler eliminare questo appuntamento?')) {
        var btn = jQuery('#form-edit-booking .btn-no');
        setBtnLoading(btn, true);
        jQuery.post(lzData.ajaxurl, {
            action: 'lz_delete_booking',
            booking_id: id,
            security: lzData.nonce
        }, function (res) {
            setBtnLoading(btn, false);
            if (res.success) {
                showToast('✅ Appuntamento eliminato', 'success');
                closeModal('modal-booking-detail');
                loadWeeklyAgenda();
            } else {
                showToast('❌ Errore eliminazione', 'error');
            }
        });
    }
}

function openClientDetail(userId) {
    openModal('modal-client-detail');
    jQuery('#client-detail-body').html('<p style="text-align:center;padding:40px;">Caricamento...</p>');

    jQuery.post(lzData.ajaxurl, {
        action: 'lz_get_client_details',
        user_id: userId,
        security: lzData.nonce
    }, function (res) {
        if (res.success) renderClientDetail(res.data);
        else jQuery('#client-detail-body').html('<p style="padding:40px;">Errore caricamento dati.</p>');
    });
}

function managePoints(uid, type, btn) {
    var amt = jQuery('#pt-amount').val();
    if (!amt || isNaN(amt) || amt == 0) {
        showToast('⚠️ Inserisci una quantità valida', 'warning');
        return;
    }
    setBtnLoading(btn, true);
    jQuery.post(lzData.ajaxurl, {
        action: 'lz_manage_points',
        user_id: uid,
        amount: amt,
        type: type,
        security: lzData.nonce
    }, function (res) {
        setBtnLoading(btn, false);
        if (res.success) {
            showToast('✅ Punti aggiornati', 'success');
            openClientDetail(uid);
        } else {
            showToast('❌ Errore aggiornamento punti', 'error');
        }
    });
}

function renderClientDetail(data) {
    var html = '<div class="profile-banner"><div class="profile-title">' + data.name + ' <span style="color:#000; font-size:18px;">✔️</span></div><div class="profile-subtitle"><span>✉️ ' + data.email + '</span><span>📞 ' + data.phone + '</span></div></div>';
    html += '<div class="profile-tabs" style="padding: 0 10px;"><button class="p-tab active" onclick="switchInternalTab(\'dati\')">Profilo</button><button class="p-tab" onclick="switchInternalTab(\'fidelity\')">Fidelity</button><button class="p-tab" onclick="switchInternalTab(\'promo\')">Promozioni</button><button class="p-tab" onclick="switchInternalTab(\'storia\')">Esperienza</button></div>';

    // Contenuti Tab
    html += '<div id="p-tab-dati" class="p-tab-content"><div class="profile-row"><div class="p-label">Nome</div><div class="p-value">' + data.name + '</div></div><div class="profile-row"><div class="p-label">Email</div><div class="p-value">' + data.email + '</div></div><div class="profile-row"><div class="p-label">Telefono</div><div class="p-value">' + data.phone + '</div></div>';
    html += '<div class="profile-row" style="margin-top:20px; border-bottom:none;"><div style="flex:1;"><div style="font-weight:700; font-size:14px; margin-bottom:4px;">Notifiche WhatsApp</div><div style="font-size:12px; color:#999;">Permetti l\'invio di messaggi automatici.</div></div><label class="switch"><input type="checkbox" ' + (data.wa_optin === 'yes' ? 'checked' : '') + ' onchange="updateWaOptin(' + data.ID + ', this.checked)"><span class="slider"></span></label></div>';
    
    // Select WhatsApp Template
    var templates = lzData.adhoc_templates || [];
    html += '<div style="margin-top:30px;">';
    html += '<div style="font-weight:700; font-size:12px; color:#999; text-transform:uppercase; margin-bottom:12px; letter-spacing:1px;">Select WhatsApp Template</div>';
    html += '<select id="noti-template" class="p-input" style="width:100%; box-sizing:border-box; margin-bottom:12px; height:45px;">';
    html += '<option value="">-- Scegli Template --</option>';
    templates.forEach(function(t) {
        html += '<option value="' + t + '">' + t + '</option>';
    });
    html += '</select>';
    html += '<div id="template-preview-box" style="display:none; background:#f0f7ff; border:1px dashed #bcdbff; padding:15px; border-radius:10px; margin-bottom:20px;">';
    html += '<div style="font-size:11px; color:#007bff; font-weight:bold; text-transform:uppercase; margin-bottom:8px; letter-spacing:0.5px;">Anteprima Messaggio</div>';
    html += '<div id="template-preview-content" style="font-size:14px; color:#444; line-height:1.5;"></div>';
    html += '</div>';
    html += '</div>';
    html += '</div>';

    html += '<div id="p-tab-fidelity" class="p-tab-content" style="display:none;"><div class="p-stats-box"><div class="p-stat"><span class="p-stat-val">' + data.points + '</span><span class="p-stat-lbl">PUNTI</span></div><div class="p-stat"><span class="p-stat-val">' + (data.bookings_count || 0) + '</span><span class="p-stat-lbl">VISITE</span></div></div><div style="background:#f9f9f9; padding:20px; border-radius:15px; border:1px solid #eee;"><div style="font-weight:700; font-size:12px; color:#999; text-transform:uppercase; margin-bottom:15px; letter-spacing:1px;">Assegna / Togli Punti</div><div style="display:flex; gap:10px;"><input type="number" id="pt-amount" class="p-input" placeholder="Q.tà" style="flex:1;"><button class="btn-black" onclick="managePoints(' + data.ID + ', \'add\', this)" style="padding:10px 15px;">+</button><button class="btn-discard" onclick="managePoints(' + data.ID + ', \'remove\', this)" style="padding:10px 15px;">-</button></div></div></div>';

    html += '<div id="p-tab-promo" class="p-tab-content" style="display:none;">';
    if (data.promotions && data.promotions.length > 0) {
        data.promotions.forEach(function (p) {
            var statusLabel = p.seen ? 'Visto' : 'Inviato';
            var statusColor = p.seen ? '#25d366' : '#999';
            html += '<div class="profile-row"><div style="flex:1;"><div style="font-weight:700;">' + p.title + '</div><div style="font-size:12px; color:#999;">Invio: ' + p.sent_at + '</div></div><div style="font-size:11px; font-weight:800; text-transform:uppercase; color:' + statusColor + ';">' + statusLabel + (p.seen_at ? ' (' + p.seen_at + ')' : '') + '</div></div>';
        });
    } else html += '<p style="text-align:center; color:#999; margin-top:30px;">Nessuna promozione inviata.</p>';
    html += '</div>';

    html += '<div id="p-tab-storia" class="p-tab-content" style="display:none;">';
    if (data.bookings && data.bookings.length > 0) {
        data.bookings.forEach(function (b) {
            html += '<div class="profile-row"><div style="flex:1;"><div style="font-weight:700;">' + b.service + '</div><div style="font-size:12px; color:#999;">' + b.date_fmt + ' ore ' + b.time + '</div></div><div style="font-size:11px; font-weight:800; text-transform:uppercase; color:' + (b.status === 'approved' ? '#25d366' : '#ff9800') + ';">' + (b.status === 'approved' ? 'Eseguito' : 'Pendente') + '</div></div>';
        });
    } else html += '<p style="text-align:center; color:#999; margin-top:30px;">Nessuno storico presente.</p>';
    html += '</div>';

    html += '<div style="display:flex; justify-content:flex-end; gap:12px; margin-top:40px; padding-top:20px; border-top:1px solid #eee;"><button class="btn-discard" onclick="closeModal(\'modal-client-detail\')">Chiudi</button><button class="btn-black" onclick="sendNotification(' + data.ID + ')">Invia Messaggio</button></div>';
    html += '<div style="margin-top:30px; text-align:center; display:flex; justify-content:center; gap:20px;"><button onclick=\'openEditClient(' + JSON.stringify(data).replace(/'/g, "&apos;") + ')\' style="background:none; border:none; color:#bbb; font-size:11px; text-decoration:underline; cursor:pointer;">Modifica anagrafica</button><button onclick="deleteClient(' + data.ID + ', this)" style="background:none; border:none; color:#ff4d4d; font-size:11px; text-decoration:underline; cursor:pointer; font-weight:700;">Elimina Cliente</button></div>';

    jQuery('#client-detail-body').html(html);

    // Listener per anteprima template
    jQuery('#noti-template').on('change', function() {
        var tName = jQuery(this).val();
        var clientName = data.name;
        updateTemplatePreview(tName, clientName);
    });
}

function updateTemplatePreview(templateName, clientName) {
    var box = jQuery('#template-preview-box');
    var content = jQuery('#template-preview-content');

    if (!templateName) {
        box.hide();
        return;
    }

    var texts = {
        'meta_test': "Ciao **" + clientName + "**, questo è un messaggio di test dal nostro sistema di gestione per confermare la tua prenotazione. Ti preghiamo di ignorare questa notifica. Grazie!",
        'meta_review_test': "Hello **" + clientName + "**, this is a test message to verify our WhatsApp integration for the app review process. Thank you for your cooperation!",
        'promo_insite': "Ciao **" + clientName + "**! Abbiamo una sorpresa speciale per te da Lidia Zucaro Parrucchieri. ✨ Abbiamo riservato una promozione esclusiva per prenderci cura della bellezza dei tuoi capelli. Scoprila subito nella tua area riservata!",
        'registrazione': "Ciao **" + clientName + "**, benvenuto nel mondo di Lidia Zucaro! La tua registrazione è stata completata con successo. (Test: Questo messaggio attiverà il popup promozionale)",
        'appuntamento_confermato': "Ciao **" + clientName + "**, il tuo appuntamento è stato confermato con successo. Ti aspettiamo!",
        'appuntamento_annullato': "Ciao **" + clientName + "**, ti informiamo che il tuo appuntamento è stato annullato.",
        'benvenuto_fidelity': "Benvenuto **" + clientName + "** nel nostro programma Fidelity! Hai già ricevuto i tuoi primi punti."
    };

    var text = texts[templateName] || "Anteprima non disponibile per questo template, ma verrà inviato correttamente con il nome del cliente.";
    
    // Formattazione semplice (grassetto)
    var formattedText = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    content.html(formattedText);
    box.fadeIn();
}

function switchInternalTab(tabName) {
    jQuery('.p-tab').removeClass('active');
    jQuery('.p-tab[onclick*="' + tabName + '"]').addClass('active');
    jQuery('.p-tab-content').hide();
    jQuery('#p-tab-' + tabName).show();
}

function deleteClient(uid, btn) {
    if (confirm('Sei sicuro di voler eliminare questo cliente?')) {
        setBtnLoading(btn, true);
        jQuery.post(lzData.ajaxurl, {
            action: 'lz_delete_client',
            user_id: uid,
            security: lzData.nonce
        }, function (res) {
            setBtnLoading(btn, false);
            if (res.success) {
                showToast('✅ Cliente eliminato', 'success');
                closeModal('modal-client-detail'); // Changed from modal-add-client
                searchClients('');
            } else {
                showToast('❌ Errore durante l\'eliminazione', 'error');
            }
        });
    }
}

/**
 * Agenda Logic (Admin)
 */
function loadWeeklyAgenda() {
    var container = jQuery('#weekly-grid-container');
    var label = jQuery('#current-week-label');
    jQuery.post(lzData.ajaxurl, {
        action: 'lz_get_weekly_agenda',
        week_offset: currentWeekOffset,
        security: lzData.nonce
    }, function (res) {
        if (res.success) {
            container.html(res.data.grid_html);
            label.text(res.data.week_label);
        }
    });
}

function changeWeek(offset) {
    currentWeekOffset += offset;
    loadWeeklyAgenda();
}

function filterAgenda(type, btn) {
    jQuery('.agenda-list').hide();
    jQuery('#list-' + type).show();
    jQuery('.sub-tab').removeClass('active');
    if (btn) jQuery(btn).addClass('active');

    var controls = jQuery('#weekly-nav-controls');
    if (type === 'weekly') {
        controls.css('display', 'flex');
        loadWeeklyAgenda();
    } else controls.hide();
}

// This code block seems to be a form submission handler, not part of loadWeeklyAgenda.
// Assuming it should be a new function or part of an existing form handler.
// Placing it here as a new function for client form submission.
function submitClientForm(form) { // Assuming 'form' is passed as a jQuery object
    var btn = form.find('button[type="submit"]');
    var userId = jQuery('#edit-user-id').val();
    var action = userId ? 'lz_edit_client' : 'lz_add_client';

    if (lzGlobalSubmitting) return false;
    setBtnLoading(btn, true);
    lzGlobalSubmitting = true;

    // Recupera i dati del form come oggetto
    var formData = form.serializeArray();
    formData.push({ name: 'action', value: action });
    formData.push({ name: 'security', value: lzData.nonce });

    console.log('Sending client data:', formData);

    jQuery.ajax({
        url: lzData.ajaxurl,
        type: 'POST',
        data: formData,
        timeout: 30000, // 30 secondi
        success: function (res) {
            setBtnLoading(btn, false);
            lzGlobalSubmitting = false;
            if (res.success) {
                showToast('✅ Operazione completata!', 'success');
                closeModal('modal-add-client');
                if (userId) openClientDetail(userId);
                form[0].reset();
                searchClients('');
            } else {
                showToast('❌ Errore: ' + res.data, 'error');
            }
        },
        error: function (xhr, status, error) {
            setBtnLoading(btn, false);
            lzGlobalSubmitting = false;
            var errDetail = 'Status: ' + status + ', Error: ' + error + ', HTML Code: ' + xhr.status;
            console.error('AJAX Error Detail:', errDetail, xhr);

            var msg = '❌ Errore di Rete';
            if (status === 'timeout') msg = '❌ Errore: Timeout della richiesta (Il server è lento)';
            else if (xhr.status) msg += ' (Cod: ' + xhr.status + ')';

            showToast(msg + '. Riprova.', 'error');
        }
    });
    return false;
}


/**
 * WhatsApp Messaging (Admin)
 */
function refreshWAMessages() {
    if (!jQuery('#view-messaggi').hasClass('active')) return;
    jQuery.post(lzData.ajaxurl, {
        action: 'lz_get_wa_messages',
        security: lzData.nonce
    }, function (res) {
        if (res.success) {
            let html = '';
            if (!res.data || res.data.length === 0) {
                html = '<p style="text-align:center; padding:40px; color:#999;">Nessun messaggio recente.</p>';
            } else {
                res.data.forEach(chat => {
                    html += `<div class="lz-chat-thread" style="background:#fff; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 15px rgba(0,0,0,0.05); overflow:hidden;">
                        <div class="lz-chat-header" style="background:#fafafa; padding:15px 20px; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center;">
                            <h4 style="margin:0; font-size:16px;">👤 ${chat.name} <span style="font-size:12px; color:#999; font-weight:normal; margin-left:10px;">${chat.phone}</span></h4>
                            <span style="font-size:12px; color:#999;">Ultima: ${new Date(chat.last_activity * 1000).toLocaleString('it-IT', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' })}</span>
                        </div>
                        <div class="lz-chat-messages" style="padding:20px; max-height:400px; overflow-y:auto; display:flex; flex-direction:column; gap:10px;">`;
                    chat.messages.forEach(m => {
                        let label = 'SISTEMA', alignClass = 'wa-msg-sys';
                        if (m.type === 'in' || m.type === 'support') { label = 'RICEVUTO'; alignClass = 'wa-msg-in'; }
                        if (m.type === 'out') { label = 'INVIATO'; alignClass = 'wa-msg-out'; }
                        if (m.type === 'action') { label = 'AZZIONE'; alignClass = 'wa-msg-sys'; }
                        html += `<div class="wa-msg-item ${alignClass}"><div class="wa-msg-header"><span>${m.date} - ${m.time}</span></div><div class="wa-msg-body">${m.msg}</div><span class="wa-msg-badge">${label}</span></div>`;
                    });
                    let disabled = (chat.phone === 'Sistema') ? 'disabled' : '';
                    html += `</div><div class="lz-chat-reply" style="padding:15px 20px; border-top:1px solid #eee; display:flex; gap:10px;">
                        <input type="text" id="reply-to-${chat.phone}" placeholder="${disabled ? 'Impossibile rispondere' : 'Scrivi...'}" style="flex:1; padding:10px; border:1px solid #ddd; border-radius:8px; outline:none;" ${disabled} onkeydown="if(event.keyCode===13){event.preventDefault();sendAdminReply('${chat.phone}');}">
                        <button onclick="sendAdminReply('${chat.phone}')" style="background:#1bd95e; color:#fff; border:none; border-radius:8px; padding:0 20px; font-weight:bold; cursor:pointer;" ${disabled}>Invia</button>
                    </div></div>`;
                });
            }
            jQuery('#wa-messages-list').html(html);
        }
    });
}

function sendAdminReply(phone) {
    let input = jQuery('#reply-to-' + phone);
    let msg = input.val().trim();
    if (!msg) return;
    let btn = input.next('button'), old = btn.text();
    btn.prop('disabled', true).text('...');
    jQuery.post(lzData.ajaxurl, { action: 'lz_wa_admin_reply', phone: phone, message: msg, security: lzData.nonce }, function (res) {
        btn.prop('disabled', false).text(old);
        if (res.success) { input.val(''); refreshWAMessages(); } else alert("Errore: " + res.data);
    });
}

/**
 * Login & Auth
 */
function revealLogin() {
    document.getElementById('btn-reveal-login').style.display = 'none';
    document.getElementById('lz-login-form').style.display = 'block';
    document.getElementById('log_u').focus();
}

function submitLogin(e) {
    e.preventDefault();
    var u = document.getElementById('log_u').value, p = document.getElementById('log_p').value;
    var err = document.querySelector('.lz-login-error'), btn = document.querySelector('#lz-login-form button');
    btn.innerText = 'ATTENDI...'; btn.disabled = true; err.style.display = 'none';
    var data = new FormData();
    data.append('action', 'lz_frontend_login'); data.append('log', u); data.append('pwd', p); data.append('security', lzData.loginNonce);
    fetch(lzData.ajaxurl, { method: 'POST', body: data }).then(res => res.json()).then(res => {
        if (res.success) window.location.reload();
        else { err.innerText = res.data; err.style.display = 'block'; btn.innerText = 'ACCEDI'; btn.disabled = false; }
    }).catch(err => { console.error(err); err.innerText = 'Errore connessione.'; err.style.display = 'block'; btn.innerText = 'ACCEDI'; btn.disabled = false; });
}

/**
 * Cliente Dashboard Logic
 */
function refreshDashboardData() {
    jQuery.post(lzData.ajaxurl, { action: 'lz_get_my_dashboard_data', security: lzData.nonce }, function (res) {
        if (res.success && res.data) {
            if (res.data.history) jQuery('#lz-client-history-list').html(res.data.history);
            if (res.data.next) jQuery('#lz-next-app-container').html(res.data.next);
            if (res.data.fidelity) jQuery('#lz-fidelity-card-container').html(res.data.fidelity);
        }
    });
}

function changePassword(btn) {
    const pwd = jQuery('#new_pwd').val(), confirm = jQuery('#confirm_pwd').val(), msg = jQuery('#pwd-msg');
    if (!pwd || pwd.length < 6) { msg.text('❌ Password troppo breve.').css('color', 'red').show(); return; }
    if (pwd !== confirm) { msg.text('❌ Le password non coincidono.').css('color', 'red').show(); return; }
    jQuery(btn).prop('disabled', true).text('ATTENDERE...');
    jQuery.post(lzData.ajaxurl, { action: 'lz_change_password', pwd: pwd, pwd_confirm: confirm, security: lzData.nonce }, function (res) {
        jQuery(btn).prop('disabled', false).text('AGGIORNA PASSWORD');
        if (res.success) { msg.text('✅ ' + res.data).css('color', 'green').show(); jQuery('#new_pwd, #confirm_pwd').val(''); jQuery('#lz-password-reminder').fadeOut(); }
        else msg.text('❌ ' + res.data).css('color', 'red').show();
    });
}

function sendSupportRequest(btn) {
    const msg = jQuery('#support-message').val().trim(), resDiv = jQuery('#support-res');
    if (!msg) return;
    const old = jQuery(btn).text(); jQuery(btn).prop('disabled', true).text('...');
    jQuery.post(lzData.ajaxurl, { action: 'lz_send_support_request', message: msg, security: lzData.nonce }, function (res) {
        jQuery(btn).prop('disabled', false).text(old);
        if (res.success) { resDiv.text('✅ Inviato!').css('color', 'green').show(); setTimeout(closeSupportModal, 2000); }
        else resDiv.text('❌ Errore: ' + res.data).css('color', 'red').show();
    });
}

/**
 * Booking Status Updates (Agenda)
 */
function updateBooking(id, status, el) {
    if (!confirm('Confermi l\'operazione?')) return;
    var card = jQuery(el).closest('.lz-card');
    jQuery.post(lzData.ajaxurl, {
        action: 'lz_update_status',
        post_id: id,
        new_status: status,
        security: lzData.nonce
    }, function (response) {
        if (response.success) {
            card.fadeOut(300, function () { jQuery(this).remove(); });
            showToast('✅ Stato aggiornato', 'success');

            // Aggiorna counter badge
            var badge = jQuery('#lz-pending-badge-count');
            if (badge.length) {
                var currentCount = parseInt(badge.text()) || 0;
                var newCount = Math.max(0, currentCount - 1);
                badge.text(newCount);
                if (newCount <= 0) {
                    badge.hide();
                }
            }
        } else {
            showToast('❌ ' + (response.data || 'Errore'), 'error');
        }
    });
}

/**
 * Utils
 */
function openModal(id) { if (document.getElementById(id)) document.getElementById(id).style.display = 'block'; }
function closeModal(id) { if (document.getElementById(id)) document.getElementById(id).style.display = 'none'; }

function handleExcelImport(event) {
    var file = event.target.files[0]; if (!file) return;
    var reader = new FileReader();
    reader.onload = function (e) {
        var data = new Uint8Array(e.target.result), workbook = XLSX.read(data, { type: 'array' });
        var rows = XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], { header: 1 });
        if (rows.length > 0) rows.shift();
        var clients = [];
        rows.forEach(function (row) {
            var nome = row[0] ? row[0].toString().trim() : '', cognome = row[1] ? row[1].toString().trim() : '', phone = row[3] ? row[3].toString().trim() : '';
            if (!nome || !phone) return;
            clients.push({ first_name: nome, last_name: cognome, phone: phone, username: (nome + cognome).toLowerCase().replace(/\s+/g, ''), email: '' });
        });
        if (clients.length === 0) { alert('Nessun dato valido.'); return; }
        if (confirm('Trovati ' + clients.length + ' clienti. Importare?')) importClientsToServer(clients);
    };
    reader.readAsArrayBuffer(file);
    event.target.value = '';
}

function importClientsToServer(clients) {
    jQuery('#lz-app-container').append('<div id="lz-import-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);z-index:999999;display:flex;align-items:center;justify-content:center;flex-direction:column;"><h3>⏳ Importazione in corso...</h3></div>');
    jQuery.post(lzData.ajaxurl, { action: 'lz_import_clients', clients: JSON.stringify(clients), security: lzData.nonce }, function (res) {
        jQuery('#lz-import-overlay').remove();
        if (res.success) { alert('✅ ' + res.data); searchClients(''); }
        else alert('❌ Errore: ' + res.data);
    }).fail(function () { jQuery('#lz-import-overlay').remove(); alert('❌ Errore critico.'); });
}
function openNewClientModal() {
    openModal('modal-add-client');
    jQuery('#edit-user-id').val('');
    jQuery('#client-modal-title').text('Nuovo Cliente');
    jQuery('#username-pass-fields').show();
    jQuery('#btn-save-client').text('Crea Cliente');
    jQuery('#form-add-client')[0].reset();
}

let gmRecipients = []; // Global recipients for group message

function openGroupMessageModal() {
    gmRecipients = [];
    jQuery('#gm-recipients-pills').empty();
    jQuery('#gm-custom-text').val('');
    jQuery('#gm-search-input').val('');
    jQuery('input[name="gm_mode"]').prop('checked', false);
    jQuery('#gm-preview-birthday').hide();
    jQuery('#gm-step-custom').hide();
    jQuery('#btn-gm-proceed').prop('disabled', true).text('Procedi');
    openModal('modal-group-message');
}

function switchGroupMode() {
    const mode = jQuery('input[name="gm_mode"]:checked').val();
    if (mode === 'birthday') {
        jQuery('#gm-preview-birthday').fadeIn();
        jQuery('#gm-step-custom').hide();
        jQuery('#btn-gm-proceed').prop('disabled', false).text('Procedi');
    } else if (mode === 'custom') {
        jQuery('#gm-preview-birthday').hide();
        jQuery('#gm-step-custom').fadeIn();
        updateGmButton();
    }
}

function groupSearchClients(query) {
    if (query.length < 2) { jQuery('#gm-suggestions').hide(); return; }
    jQuery.post(lzData.ajaxurl, { action: 'lz_search_clients', query: query, security: lzData.nonce }, function (res) {
        if (res.success && res.data.length > 0) {
            let html = '';
            res.data.forEach(c => {
                // Avoid suggesting already added recipients
                if (!gmRecipients.some(r => r.id == c.ID)) {
                    html += `<div class="suggestion-item" onclick="addRecipient(${c.ID}, '${c.display_name.replace(/'/g, "\\'")}')">
                        <span class="name">${c.display_name}</span>
                        <span class="phone">${c.phone}</span>
                    </div>`;
                }
            });
            if (html) { jQuery('#gm-suggestions').html(html).show(); }
            else { jQuery('#gm-suggestions').hide(); }
        } else {
            jQuery('#gm-suggestions').hide();
        }
    });
}

function addRecipient(id, name) {
    if (gmRecipients.some(r => r.id == id)) return;
    gmRecipients.push({ id: id, name: name });
    renderGmPills();
    jQuery('#gm-search-input').val('');
    jQuery('#gm-suggestions').hide();
    updateGmButton();
}

function removeRecipient(id) {
    gmRecipients = gmRecipients.filter(r => r.id != id);
    renderGmPills();
    updateGmButton();
}

function renderGmPills() {
    let html = '';
    gmRecipients.forEach(r => {
        html += `<div class="gm-recipient-pill">
            <span>${r.name}</span>
            <span class="remove" onclick="removeRecipient(${r.id})">&times;</span>
        </div>`;
    });
    jQuery('#gm-recipients-pills').html(html);
}

function updateGmButton() {
    const mode = jQuery('input[name="gm_mode"]:checked').val();
    if (mode === 'custom') {
        const template = jQuery('#gm-template-select').val();
        const text = jQuery('#gm-custom-text').val().trim();
        
        const hasRecipients = gmRecipients.length > 0;
        const hasContent = (template !== 'custom_text' || text.length > 0);

        if (hasRecipients && hasContent) {
            jQuery('#btn-gm-proceed').prop('disabled', false).text('Invia (' + gmRecipients.length + ')');
        } else {
            jQuery('#btn-gm-proceed').prop('disabled', true).text('Invia');
        }
    }
}

function toggleCustomMessageArea() {
    const template = jQuery('#gm-template-select').val();
    if (template === 'custom_text') {
        jQuery('#gm-custom-text-area').fadeIn();
    } else {
        jQuery('#gm-custom-text-area').fadeOut();
    }
    updateGmButton();
}

function submitGroupMessage() {
    const mode = jQuery('input[name="gm_mode"]:checked').val();
    const btn = jQuery('#btn-gm-proceed');
    let data = {
        action: 'lz_send_group_message',
        mode: mode,
        security: lzData.nonce
    };

    if (mode === 'custom') {
        const template = jQuery('#gm-template-select').val();
        const text = jQuery('#gm-custom-text').val().trim();
        
        if (gmRecipients.length === 0) { showToast('⚠️ Seleziona almeno un destinatario', 'warning'); return; }
        
        if (template === 'custom_text') {
            if (!text) { showToast('⚠️ Scrivi un messaggio!', 'warning'); return; }
            data.message = text;
        } else {
            data.template = template;
        }
        data.recipients = JSON.stringify(gmRecipients.map(r => r.id));
    }

    if (!confirm('Sei sicuro di voler procedere con l\'invio massivo?')) return;

    btn.prop('disabled', true).text('Invio in corso...');
    jQuery.post(lzData.ajaxurl, data, function (res) {
        btn.prop('disabled', false).text(mode === 'custom' ? 'Invia' : 'Procedi');
        if (res.success) {
            showToast('✅ ' + res.data, 'success');
            closeModal('modal-group-message');
        } else {
            showToast('❌ Errore: ' + res.data, 'error');
        }
    });
}

function openEditClient(data) {
    if (typeof data === 'string') data = JSON.parse(data);
    openModal('modal-add-client');
    jQuery('#edit-user-id').val(data.ID);
    jQuery('#client-modal-title').text('Modifica Cliente: ' + data.name);
    jQuery('#username-pass-fields').hide();
    jQuery('#btn-save-client').text('Salva Modifiche');

    jQuery('#edit-first-name').val(data.first_name);
    jQuery('#edit-last-name').val(data.last_name);
    jQuery('#edit-phone').val(data.phone);
    jQuery('#edit-email').val(data.email);
    jQuery('#edit-wa-optin').prop('checked', data.wa_optin === 'yes');
}

function sendWelcomeMessages() {
    if (!confirm('Vuoi inviare il messaggio di benvenuto a tutti i nuovi clienti che non lo hanno ancora ricevuto?')) return;
    var btn = jQuery('#btn-send-welcome');
    setBtnLoading(btn, true);
    jQuery.post(lzData.ajaxurl, { action: 'lz_send_welcome_messages', security: lzData.nonce }, function (res) {
        setBtnLoading(btn, false);
        if (res.success) showToast('✅ ' + res.data, 'success');
        else showToast('❌ Errore: ' + res.data, 'error');
    });
}

function updateWaOptin(uid, checked) {
    jQuery.post(lzData.ajaxurl, {
        action: 'lz_update_wa_optin',
        user_id: uid,
        optin: checked ? 'yes' : 'no',
        security: lzData.nonce
    });
}

function sendNotification(uid) {
    var msg = jQuery('#noti-template').val();
    if (!msg) { showToast('⚠️ Seleziona un template!', 'warning'); return; }
    var btn = jQuery('.btn-black:contains("Invia Messaggio")');
    setBtnLoading(btn, true);
    jQuery.post(lzData.ajaxurl, {
        action: 'lz_send_notification',
        user_id: uid,
        message: msg,
        security: lzData.nonce
    }, function (res) {
        setBtnLoading(btn, false);
        if (res.success) {
            showToast('✅ Messaggio inviato!', 'success');
            jQuery('#noti-template').val('');
        } else showToast('❌ Errore: ' + res.data, 'error');
    });
}
// Update required logic
jQuery(document).ready(function ($) {
    $('#lz-no-pref-time').on('change', function () {
        if ($(this).is(':checked')) {
            $('#lz-time-input').prop('required', false);
        } else {
            $('#lz-time-input').prop('required', true);
        }
    });
});

/**
 * Profile Tab: Accordion
 */
function toggleAccordion(id) {
    var body = document.getElementById('body-' + id);
    var chevron = document.getElementById('chevron-' + id);
    if (!body) return;

    if (body.style.display === 'none' || body.style.display === '') {
        body.style.display = 'block';
        if (chevron) chevron.classList.add('open');
    } else {
        body.style.display = 'none';
        if (chevron) chevron.classList.remove('open');
    }
}

/**
 * Profile Tab: Save Email
 */
function saveUserEmail(btn) {
    var email = jQuery('#new-email-input').val().trim();
    var msgDiv = jQuery('#email-modal-msg');

    if (!email || !email.includes('@')) {
        msgDiv.text('❌ Inserisci un indirizzo email valido.').css('color', 'red').show();
        return;
    }

    var old = jQuery(btn).text();
    jQuery(btn).prop('disabled', true).text('Salvataggio...');

    jQuery.post(lzData.ajaxurl, {
        action: 'lz_save_user_email',
        email: email,
        security: lzData.nonce
    }, function (res) {
        jQuery(btn).prop('disabled', false).text(old);
        if (res.success) {
            msgDiv.text('✅ Email salvata!').css('color', 'green').show();
            setTimeout(function () {
                closeModal('modal-add-email');
                window.location.reload();
            }, 1000);
        } else {
            msgDiv.text('❌ ' + (res.data || 'Errore durante il salvataggio.')).css('color', 'red').show();
        }
    }).fail(function () {
        jQuery(btn).prop('disabled', false).text(old);
        msgDiv.text('❌ Errore di rete.').css('color', 'red').show();
    });
}

/**
 * Profile Tab: Save Birthday
 */
function saveUserBirthday(btn) {
    var birthday = jQuery('#new-birthday-input').val().trim();
    var msgDiv = jQuery('#birthday-modal-msg');

    if (!birthday) {
        msgDiv.text('❌ Inserisci una data valida.').css('color', 'red').show();
        return;
    }

    var old = jQuery(btn).text();
    jQuery(btn).prop('disabled', true).text('Salvataggio...');

    jQuery.post(lzData.ajaxurl, {
        action: 'lz_save_user_birthday',
        birthday: birthday,
        security: lzData.nonce
    }, function (res) {
        jQuery(btn).prop('disabled', false).text(old);
        if (res.success) {
            msgDiv.text('✅ Data salvata!').css('color', 'green').show();
            setTimeout(function () {
                closeModal('modal-add-birthday');
                window.location.reload();
            }, 1000);
        } else {
            msgDiv.text('❌ ' + (res.data || 'Errore durante il salvataggio.')).css('color', 'red').show();
        }
    }).fail(function () {
        jQuery(btn).prop('disabled', false).text(old);
        msgDiv.text('❌ Errore di rete.').css('color', 'red').show();
    });
}

/**
 * Manage Next Appointment 
 */
function openManageModal(id, service, date, time) {
    // Fill the hidden id and the form values
    jQuery('#edit-app-id').val(id);
    jQuery('#edit-app-service').val(service);
    jQuery('#edit-app-date').val(date);
    jQuery('#edit-app-time').val(time);

    // Clear messages
    jQuery('#edit-app-msg').hide();
    jQuery('#cancel-app-msg').hide();

    // Show default view
    switchManageView('default');

    // Open modal
    openModal('modal-manage-appointment');
}

function switchManageView(view) {
    jQuery('#manage-app-default').hide();
    jQuery('#manage-app-edit').hide();
    jQuery('#manage-app-cancel').hide();
    jQuery('#manage-app-' + view).fadeIn(200);
}

function submitManageEdit(btn) {
    var id = jQuery('#edit-app-id').val();
    var service = jQuery('#edit-app-service').val();
    var date = jQuery('#edit-app-date').val();
    var time = jQuery('#edit-app-time').val();
    var msgDiv = jQuery('#edit-app-msg');

    if (!date || !time) {
        msgDiv.text('❌ Compila data e ora.').css('color', 'red').show();
        return;
    }

    var old = jQuery(btn).text();
    jQuery(btn).prop('disabled', true).text('Attendere...');

    jQuery.post(lzData.ajaxurl, {
        action: 'lz_request_edit_booking',
        booking_id: id,
        service: service,
        date: date,
        time: time,
        security: lzData.nonce
    }, function (res) {
        jQuery(btn).prop('disabled', false).text(old);
        if (res.success) {
            msgDiv.text('✅ Modifica salvata.').css('color', 'green').show();
            setTimeout(function () { window.location.reload(); }, 1000);
        } else {
            msgDiv.text('❌ Errore: ' + (res.data || 'Impossibile modificare.')).css('color', 'red').show();
        }
    }).fail(function () {
        jQuery(btn).prop('disabled', false).text(old);
        msgDiv.text('❌ Errore di connessione.').css('color', 'red').show();
    });
}

function submitManageCancel(btn) {
    var id = jQuery('#edit-app-id').val();
    var msgDiv = jQuery('#cancel-app-msg');

    var old = jQuery(btn).text();
    jQuery(btn).prop('disabled', true).text('Attendere...');

    jQuery.post(lzData.ajaxurl, {
        action: 'lz_request_delete_booking',
        booking_id: id,
        security: lzData.nonce
    }, function (res) {
        jQuery(btn).prop('disabled', false).text(old);
        if (res.success) {
            msgDiv.text('✅ Appuntamento annullato.').css('color', 'green').show();
            setTimeout(function () { window.location.reload(); }, 1000);
        } else {
            msgDiv.text('❌ Errore: ' + (res.data || 'Impossibile annullare.')).css('color', 'red').show();
        }
    }).fail(function () {
        jQuery(btn).prop('disabled', false).text(old);
        msgDiv.text('❌ Errore di connessione.').css('color', 'red').show();
    });
}
